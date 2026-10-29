<?php

namespace App\Services;

use App\Models\ReservaVehiculo;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoActa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VehiculoActaService
{
    public const PARTES = ['carroceria' => 'Carrocería', 'neumaticos' => 'Neumáticos', 'parabrisas' => 'Parabrisas', 'accesorios' => 'Accesorios'];
    public const KIT = ['extintor' => 'Extintor vigente', 'repuesto' => 'Neumático de repuesto', 'gata' => 'Gata', 'llave' => 'Llave de ruedas', 'triangulos' => 'Triángulos reflectantes', 'chaleco' => 'Chaleco reflectante', 'botiquin' => 'Botiquín'];

    public function guardar(ReservaVehiculo $reserva, string $tipo, array $data, Request $request): VehiculoActa
    {
        $archivos = [];
        try {
            return DB::transaction(function () use ($reserva, $tipo, $data, $request, &$archivos) {
                // Same lock order as finalization: reservation, then vehicle.
                $reserva = ReservaVehiculo::lockForUpdate()->findOrFail($reserva->id);
                $vehiculo = Vehiculo::lockForUpdate()->findOrFail($reserva->vehiculo_id);
                $this->validarOperacion($reserva, $tipo);
                $acta = $reserva->actasSaep()->where('tipo', $tipo)->first();
                if ($acta && $acta->estado !== 'BORRADOR') {
                    $this->error('El acta está bloqueada para firma. Reabre el borrador antes de corregirla.');
                }
                if ($acta && (int) $data['revision'] !== $acta->revision) {
                    $this->error('Otra persona actualizó el borrador. Recarga la página antes de guardar.');
                }
                $entrega = $reserva->actasSaep()->where('tipo', 'entrega')->where('estado', 'FIRMADA')->first();
                if ($tipo === 'devolucion' && $data['kilometraje'] < $entrega->datos['kilometraje']) {
                    $this->error('El kilometraje de devolución no puede ser menor al registrado en la entrega.', 'kilometraje');
                }
                if ($tipo === 'devolucion' && strtotime($data['fecha']) < strtotime($entrega->datos['fecha'])) {
                    $this->error('La devolución no puede ser anterior a la entrega.', 'fecha');
                }
                $uuid = $acta?->uuid ?? (string) Str::uuid();
                $fotos = $acta?->evidencias ?? [];
                if ($request->hasFile('fotos')) {
                    $fotos = [];
                    foreach ($request->file('fotos') as $foto) {
                        // Re-encode image: strip EXIF/location and reject active formats.
                        $bytes = $this->imagen(file_get_contents($foto->getRealPath()));
                        $path = "vehiculos/actas/{$uuid}/".Str::uuid().'.png';
                        $this->escribir($path, $bytes);
                        $archivos[] = $path;
                        $fotos[] = ['path' => $path, 'sha256' => hash('sha256', $bytes)];
                    }
                }
                $preparar = $data['accion'] === 'preparar';
                if ($preparar && count($fotos) < 1) {
                    $this->error('Adjunta al menos una fotografía del vehículo antes de solicitar la firma.', 'fotos');
                }
                $firmaPath = null;
                if ($preparar) {
                    $firma = $this->firma($data['firma_encargado'] ?? '', 'firma_encargado');
                    $firmaPath = "vehiculos/actas/{$uuid}/encargado-".Str::uuid().'.png';
                    $this->escribir($firmaPath, $firma);
                    $archivos[] = $firmaPath;
                }
                $datos = collect($data)->only(['conductor_nombre', 'conductor_email', 'fecha', 'kilometraje', 'combustible', 'condiciones', 'kit', 'observaciones', 'danos_nuevos'])->all();
                $datos['ubicacion'] = $data['ubicacion'] ?? null;
                $datos['ubicacion_motivo'] = $datos['ubicacion'] ? null : ($data['ubicacion_motivo'] ?? null);
                $datos['croquis'] = !empty($data['quitar_croquis']) ? null : ($acta?->datos['croquis'] ?? null);
                if (!empty($data['croquis'])) {
                    $bytes = $this->firma($data['croquis'], 'croquis');
                    $path = "vehiculos/actas/{$uuid}/croquis-".Str::uuid().'.png';
                    $this->escribir($path, $bytes); $archivos[] = $path;
                    $datos['croquis'] = ['path' => $path, 'sha256' => hash('sha256', $bytes)];
                }
                // Identity and notification address come from the reservation, never the handset payload.
                $datos['conductor_nombre'] = $tipo === 'devolucion' ? $entrega->datos['conductor_nombre'] : $reserva->solicitante_nombre;
                $datos['conductor_email'] = strtolower(trim($tipo === 'devolucion' ? $entrega->datos['conductor_email'] : $reserva->solicitante_email));
                if (!filter_var($datos['conductor_email'], FILTER_VALIDATE_EMAIL) || blank($datos['conductor_nombre'])) {
                    $this->error('Corrige el nombre y correo del responsable en la reserva antes de continuar.', 'conductor_email');
                }
                $datos['vehiculo'] = ['id' => $vehiculo->id, 'patente' => $vehiculo->patente, 'nombre' => $vehiculo->nombre_operativo];
                $datos['reserva'] = ['codigo' => $reserva->codigo, 'inicio' => $reserva->inicio->toIso8601String(), 'termino' => $reserva->termino->toIso8601String(), 'destino' => $reserva->destino];
                $datos['encargado'] = ['id' => $request->user()->id, 'nombre' => $request->user()->nombre_completo, 'email' => $request->user()->email];
                $datos['entrega_original'] = $entrega ? ['uuid' => $entrega->uuid, 'kilometraje' => $entrega->datos['kilometraje'], 'fecha' => $entrega->datos['fecha'], 'documento_hash' => $entrega->documento_hash] : null;
                $acuerdo = file_get_contents(resource_path('text/vehiculos-responsabilidad.txt'));
                $values = [
                    'uuid' => $uuid, 'tipo' => $tipo, 'estado' => $preparar ? 'PENDIENTE_FIRMA' : 'BORRADOR',
                    'revision' => ($acta?->revision ?? 0) + 1, 'datos' => $datos, 'evidencias' => $fotos,
                    'acuerdo' => $acuerdo, 'acuerdo_version' => config('vehiculos_actas.agreement_version'),
                    'firma_encargado_path' => $firmaPath, 'preparada_por' => $request->user()->id,
                    'preparada_at' => $preparar ? now() : null,
                    'contenido_hash' => $preparar ? hash('sha256', json_encode([$datos, $fotos, $acuerdo, hash_file('sha256', Storage::disk(config('vehiculos_actas.disk'))->path($firmaPath))], JSON_UNESCAPED_UNICODE)) : null,
                ];
                $acta = $reserva->actasSaep()->updateOrCreate(['tipo' => $tipo], $values);
                $this->evento($acta, $request->user(), $preparar ? 'ACTA_SAEP_PREPARADA' : 'ACTA_SAEP_BORRADOR', 'Acta de '.$tipo.($preparar ? ' preparada para firma.' : ' guardada como borrador.'));
                return $acta;
            });
        } catch (\Throwable $e) {
            Storage::disk(config('vehiculos_actas.disk'))->delete($archivos);
            throw $e;
        }
    }

    public function firmar(VehiculoActa $acta, array $data, Request $request, array $identidad): VehiculoActa
    {
        $path = null;
        try {
            return DB::transaction(function () use ($acta, $data, $request, $identidad, &$path) {
                $reserva = ReservaVehiculo::lockForUpdate()->findOrFail($acta->reserva_vehiculo_id);
                $vehiculo = Vehiculo::lockForUpdate()->findOrFail($reserva->vehiculo_id);
                $acta = VehiculoActa::lockForUpdate()->findOrFail($acta->id);
                if ($acta->estado !== 'PENDIENTE_FIRMA' || !hash_equals((string) $acta->contenido_hash, $data['contenido_hash'])) {
                    $this->error('Esta versión ya no está disponible para firmar. Recarga y revisa el acta.');
                }
                $this->validarOperacion($reserva, $acta->tipo);
                if ($reserva->vehiculo_id !== $acta->datos['vehiculo']['id']) {
                    $this->error('El vehículo cambió. Reabre y revisa el borrador.');
                }
                if ($acta->tipo === 'entrega') {
                    if ($vehiculo->estado !== 'DISPONIBLE' || !$vehiculo->reservas_habilitadas) {
                        $this->error('El vehículo no está habilitado para entrega.');
                    }
                    $ocupado = ReservaVehiculo::where('vehiculo_id', $vehiculo->id)->whereKeyNot($reserva->id)
                        ->whereNotNull('entregada_at')->whereNull('devuelta_at')->whereIn('estado', ['EN_USO', 'VENCIDA'])->exists();
                    if ($ocupado) $this->error('El vehículo aún está en custodia de otro conductor. Registra primero su devolución.');
                }
                $firma = $this->firma($data['firma_conductor'], 'firma_conductor');
                $path = 'vehiculos/actas/'.$acta->uuid.'/conductor-'.Str::uuid().'.png';
                $this->escribir($path, $firma);
                $auditoria = [
                    'modalidad' => $identidad['modalidad'], 'identidad' => $identidad['email'],
                    'nombre_declarado' => $data['nombre_firmante'], 'operador_id' => $request->user()?->id,
                    'ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                    'aceptado_at' => now()->toIso8601String(), 'firma_sha256' => hash('sha256', $firma),
                    'acuerdo_version' => $acta->acuerdo_version, 'contenido_hash' => $acta->contenido_hash,
                ];
                $acta->update([
                    'estado' => 'FIRMADA', 'firma_conductor_path' => $path, 'firmada_at' => now(),
                    'auditoria_firma' => $auditoria,
                    'documento_hash' => hash('sha256', $acta->contenido_hash.json_encode($auditoria, JSON_UNESCAPED_UNICODE)),
                    'email_estado' => config('vehiculos_actas.mail_enabled') ? 'PENDIENTE' : 'DESACTIVADO',
                ]);
                $reserva->update($acta->tipo === 'entrega'
                    ? ['estado' => 'EN_USO', 'entregada_at' => $acta->datos['fecha']]
                    : ['estado' => 'DEVUELTA', 'devuelta_at' => $acta->datos['fecha']]);
                $this->evento($acta, $request->user(), 'ACTA_SAEP_FIRMADA', 'Acta de '.$acta->tipo.' firmada en SAEP.');
                return $acta->fresh();
            });
        } catch (\Throwable $e) {
            if ($path) Storage::disk(config('vehiculos_actas.disk'))->delete($path);
            throw $e;
        }
    }

    public function reabrir(VehiculoActa $acta, User $user): void
    {
        DB::transaction(function () use ($acta, $user) {
            ReservaVehiculo::lockForUpdate()->findOrFail($acta->reserva_vehiculo_id);
            $acta = VehiculoActa::lockForUpdate()->findOrFail($acta->id);
            if ($acta->estado !== 'PENDIENTE_FIRMA') $this->error('Solo puede reabrirse un acta que aún no ha firmado el conductor.');
            $this->evento($acta, $user, 'ACTA_SAEP_REABIERTA', 'Se invalidó la preparación y la firma del encargado antes de corregir el acta.');
            $acta->update(['estado' => 'BORRADOR', 'revision' => $acta->revision + 1, 'firma_encargado_path' => null, 'contenido_hash' => null, 'preparada_at' => null]);
        });
    }

    public function validarOperacion(ReservaVehiculo $reserva, string $tipo): void
    {
        if ($tipo === 'entrega' && ($reserva->estado !== 'CONFIRMADA' || $reserva->entregada_at)) {
            $this->error('La entrega requiere una reserva confirmada sin entrega previa.');
        }
        if ($tipo === 'devolucion' && (!in_array($reserva->estado, ['EN_USO', 'VENCIDA'], true)
            || !$reserva->actasSaep()->where('tipo', 'entrega')->where('estado', 'FIRMADA')->exists())) {
            $this->error('La devolución requiere una entrega firmada en SAEP y una reserva en uso o vencida.');
        }
    }

    public function imagen(string $bytes): string
    {
        $size = @getimagesizefromstring($bytes);
        if (!$size || $size[0] * $size[1] > 16000000 || !in_array($size[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            $this->error('Utiliza imágenes JPG o PNG de hasta 16 megapíxeles.', 'fotos');
        }
        $im = @imagecreatefromstring($bytes);
        if (!$im) $this->error('No fue posible leer la imagen.', 'fotos');
        imagesavealpha($im, true);
        ob_start(); imagepng($im); $clean = ob_get_clean(); imagedestroy($im);
        return $clean;
    }

    public function firma(string $data, string $campo): string
    {
        if (!str_starts_with($data, 'data:image/png;base64,') || strlen($data) > 700000) {
            $this->error('Dibuja una firma válida antes de continuar.', $campo);
        }
        $bytes = base64_decode(substr($data, 22), true);
        $size = $bytes ? @getimagesizefromstring($bytes) : false;
        if (!$size || $size[2] !== IMAGETYPE_PNG || $size[0] > 1600 || $size[1] > 600) {
            $this->error('La firma no es una imagen PNG válida.', $campo);
        }
        $im = @imagecreatefromstring($bytes);
        $ink = 0;
        for ($y = 0; $im && $y < imagesy($im); $y += 2) {
            for ($x = 0; $x < imagesx($im); $x += 2) {
                $c = imagecolorsforindex($im, imagecolorat($im, $x, $y));
                if ($c['alpha'] < 100 && min($c['red'], $c['green'], $c['blue']) < 180) $ink++;
            }
        }
        if ($im) imagedestroy($im);
        if ($ink < 25) $this->error('La firma está vacía o es demasiado breve. Vuelve a dibujarla.', $campo);
        return $this->imagen($bytes);
    }

    private function escribir(string $path, string $bytes): void
    {
        if (!Storage::disk(config('vehiculos_actas.disk'))->put($path, $bytes)) {
            throw new \RuntimeException('No se pudo guardar la evidencia. El acta no fue finalizada.');
        }
    }

    private function evento(VehiculoActa $acta, ?User $user, string $accion, string $resumen): void
    {
        $acta->reserva->eventos()->create([
            'user_id' => $user?->id, 'actor_email' => $user?->email ?? $acta->datos['conductor_email'],
            'actor_nombre' => $user?->nombre_completo ?? $acta->datos['conductor_nombre'],
            'accion' => $accion, 'resumen' => $resumen,
            'cambios' => ['acta_uuid' => $acta->uuid, 'revision' => $acta->revision, 'contenido_hash' => $acta->contenido_hash, 'documento_hash' => $acta->documento_hash],
        ]);
    }

    private function error(string $message, string $campo = 'acta'): never
    {
        throw ValidationException::withMessages([$campo => $message]);
    }
}
