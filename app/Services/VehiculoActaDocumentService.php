<?php

namespace App\Services;

use App\Models\VehiculoActa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class VehiculoActaDocumentService
{
    public function imagen(?string $path): ?string
    {
        if (!$path) return null;
        return 'data:image/png;base64,'.base64_encode(Storage::disk(config('vehiculos_actas.disk'))->get($path));
    }

    public function fotoPdf(string $path): string
    {
        // Only the PDF copy is optimized. Original evidence and its signed hash stay intact.
        $source = imagecreatefromstring(Storage::disk(config('vehiculos_actas.disk'))->get($path));
        if (!$source) throw new \RuntimeException('No se pudo leer la fotografía del acta.');
        $scale = min(1, 1600 / max(imagesx($source), imagesy($source)));
        $width = max(1, (int) round(imagesx($source) * $scale));
        $height = max(1, (int) round(imagesy($source) * $scale));
        $copy = imagecreatetruecolor($width, $height);
        imagefill($copy, 0, 0, imagecolorallocate($copy, 255, 255, 255));
        imagecopyresampled($copy, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        ob_start(); imagejpeg($copy, null, 78); $bytes = ob_get_clean();
        imagedestroy($copy); imagedestroy($source);
        return 'data:image/jpeg;base64,'.base64_encode($bytes);
    }

    public function pdf(VehiculoActa $acta): string
    {
        return Pdf::loadView('pdf.vehiculo_acta_saep', ['acta' => $acta, 'documentos' => $this])
            ->setPaper('a4')->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans'])->output();
    }

    public function paqueteKizeo(VehiculoActa $acta): array
    {
        $d = $acta->datos;
        $entrega = $acta->tipo === 'entrega';
        $values = [
            'codigo_de_reserva_saep' => $d['reserva']['codigo'],
            'gestion' => $entrega ? 'Entrega a Conductor' : 'Devolución a Base',
            $entrega ? 'fecha_y_hora' : 'fecha_y_hora1' => $d['fecha'],
            $entrega ? 'kilometraje_de_entrega' : 'kilometraje_de_devolucion1' => $d['kilometraje'],
            'observaciones_adicionales' => $d['observaciones'] ?? '',
        ];
        if (!$entrega) {
            $values['el_vehiculo_presenta_danos_nu'] = $d['danos_nuevos'];
            $values['se_devuelve_el_kit_de_herrami1'] = in_array('Faltante', $d['kit'], true) ? 'NO' : 'SI';
        } else {
            $values['he_leido_comprendo_y_acepto_l'] = true;
            $values['kit_de_seguridad_y_emergencia'] = array_values(VehiculoActaService::PARTES);
        }
        $medias = [];
        foreach ($acta->evidencias as $i => $foto) $medias[] = ['field' => $entrega ? 'foto' : 'fotos', 'name' => 'foto-'.($i + 1).'.png', 'sha256' => $foto['sha256'], 'data_url' => $this->imagen($foto['path'])];
        foreach (['conductor', 'encargado'] as $rol) {
            $field = $rol === 'conductor' ? ($entrega ? 'firma' : 'firma1') : ($entrega ? 'firma_encargado' : 'firma_encargado1');
            $medias[] = ['field' => $field, 'name' => $rol.'.png', 'data_url' => $this->imagen($acta->{'firma_'.$rol.'_path'})];
        }
        return [
            'schema' => 'saep.vehicle-act.v1', 'idempotency_key' => $acta->uuid,
            'status' => 'PENDIENTE_VALIDACION_API', 'form_id' => config('vehiculos_actas.form_id'),
            'warning' => 'Paquete interno SAEP, no es un contrato de escritura Kizeo validado. No se ha enviado a Kizeo. Requiere confirmar endpoint de guardado final y carga de medios; resolver códigos de listas de conductor y patente y atributos adicionales antes de enviar.',
            'fields' => collect($values)->map(fn ($value) => ['value' => $value])->all(),
            'saep_snapshot' => $d, 'agreement' => $acta->acuerdo, 'agreement_version' => $acta->acuerdo_version,
            'audit' => $acta->auditoria_firma, 'document_sha256' => $acta->documento_hash, 'medias' => $medias,
        ];
    }
}
