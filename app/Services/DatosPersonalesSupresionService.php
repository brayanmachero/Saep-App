<?php

namespace App\Services;

use App\Models\ArchivoAdjunto;
use App\Models\ConsentimientoDatos;
use App\Models\FirmaElectronica;
use App\Models\LeyKarin;
use App\Models\PostulanteContratacion;
use App\Models\RegistroTratamientoDatos;
use App\Models\Respuesta;
use App\Models\SolicitudArco;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatosPersonalesSupresionService
{
    private const MARCADOR = '[suprimido por solicitud ARCO]';

    /**
     * Ejecuta una supresión autorizada conservando evidencia mínima de auditoría.
     *
     * El servicio anonimiza registros que pueden mantenerse por obligación legal,
     * elimina archivos adjuntos controlados por la aplicación y deja advertencias
     * para campos narrativos donde puede existir texto libre con identificadores.
     */
    public function ejecutarParaUsuario(User $user, SolicitudArco $solicitud, User $responsable): array
    {
        $resultado = [
            'titular_id' => $user->id,
            'solicitud' => $solicitud->numero_solicitud,
            'ejecutado_por' => $responsable->id,
            'ejecutado_at' => now()->toIso8601String(),
            'acciones' => [],
            'advertencias' => [],
        ];

        DB::transaction(function () use ($user, $solicitud, $responsable, &$resultado) {
            $resultado['acciones']['consentimientos_revocados'] = ConsentimientoDatos::where('user_id', $user->id)
                ->where('vigente', true)
                ->update([
                    'vigente' => false,
                    'fecha_revocacion' => now(),
                ]);

            $resultado['acciones']['respuestas_anonimizadas'] = $this->anonimizarRespuestas($user, $solicitud);
            $resultado['acciones']['firmas_anonimizadas'] = $this->anonimizarFirmas($user);
            $resultado['acciones']['ley_karin_anonimizadas'] = $this->anonimizarLeyKarin($user);
            $resultado['acciones']['archivos_adjuntos_eliminados'] = $this->eliminarArchivosAdjuntosUsuario($user);

            if ($user->foto_perfil) {
                Storage::disk('public')->delete($user->foto_perfil);
                $resultado['acciones']['foto_perfil_eliminada'] = true;
            } else {
                $resultado['acciones']['foto_perfil_eliminada'] = false;
            }

            $snapshot = [
                'id' => $user->id,
                'email_hash' => $this->hashIdentifier($user->email),
                'rut_hash' => $this->hashIdentifier($user->rut),
                'campos_suprimidos' => [
                    'azure_oid',
                    'talana_id',
                    'name',
                    'apellido_paterno',
                    'apellido_materno',
                    'email',
                    'rut',
                    'telefono',
                    'fecha_nacimiento',
                    'nacionalidad',
                    'sexo',
                    'estado_civil',
                    'fecha_ingreso',
                    'foto_perfil',
                    'password',
                    'remember_token',
                ],
            ];

            $user->forceFill([
                'azure_oid' => null,
                'talana_id' => null,
                'name' => 'Usuario suprimido ' . $solicitud->numero_solicitud,
                'apellido_paterno' => null,
                'apellido_materno' => null,
                'email' => sprintf('suprimido+%d@saep.invalid', $user->id),
                'rut' => null,
                'telefono' => null,
                'fecha_nacimiento' => null,
                'nacionalidad' => null,
                'sexo' => null,
                'estado_civil' => null,
                'fecha_ingreso' => null,
                'foto_perfil' => null,
                'activo' => false,
                'acepta_politica_datos' => false,
                'fecha_aceptacion_politica' => null,
                'password' => Hash::make(Str::random(48)),
                'remember_token' => null,
            ])->save();

            if (!$user->trashed()) {
                $user->delete();
            }

            RegistroTratamientoDatos::registrar(
                'supresion_ejecutada',
                'users',
                $user->id,
                'personal',
                "Supresión autorizada ejecutada para {$solicitud->numero_solicitud}",
                $snapshot,
                [
                    'email' => sprintf('suprimido+%d@saep.invalid', $user->id),
                    'activo' => false,
                    'deleted_at' => now()->toIso8601String(),
                ]
            );

            $resultado['acciones']['usuario_anonimizado'] = true;
            $resultado['advertencias'][] = 'Revisar manualmente textos libres en denuncias, formularios y observaciones: pueden contener nombres, RUT u otros identificadores escritos por usuarios.';
            $resultado['advertencias'][] = 'Validar si existen copias externas en SharePoint, correo, Kizeo, Google Drive u otros encargados de tratamiento; la aplicación sólo ejecutó supresión en su base de datos y storage configurado.';
        });

        return $resultado;
    }

    public function ejecutarParaPostulante(PostulanteContratacion $postulante, SolicitudArco $solicitud, User $responsable): array
    {
        $resultado = [
            'postulante_id' => $postulante->id,
            'folio' => $postulante->folio,
            'solicitud' => $solicitud->numero_solicitud,
            'ejecutado_por' => $responsable->id,
            'ejecutado_at' => now()->toIso8601String(),
            'acciones' => [],
            'advertencias' => [],
        ];

        DB::transaction(function () use ($postulante, $solicitud, &$resultado) {
            $camposDocs = [
                'carnet_frontal',
                'carnet_reverso',
                'certificado_afp',
                'certificado_fonasa',
                'licencia_conducir_frontal',
                'licencia_conducir_reverso',
            ];

            $eliminados = 0;
            foreach ($camposDocs as $campo) {
                if ($postulante->{$campo}) {
                    Storage::disk('public')->delete($postulante->{$campo});
                    $postulante->{$campo} = null;
                    $eliminados++;
                }
            }

            $snapshot = [
                'id' => $postulante->id,
                'folio' => $postulante->folio,
                'email_hash' => $this->hashIdentifier($postulante->email),
                'rut_hash' => $this->hashIdentifier($postulante->rut),
                'campos_suprimidos' => [
                    'nombre',
                    'rut',
                    'email',
                    'google_id',
                    'google_name',
                    'google_avatar',
                    'observaciones',
                    'documentos_adjuntos',
                ],
            ];

            $postulante->forceFill([
                'nombre' => 'Postulante suprimido ' . $solicitud->numero_solicitud,
                'rut' => 'SUPRIMIDO-' . $postulante->id,
                'email' => sprintf('postulante-suprimido+%d@saep.invalid', $postulante->id),
                'google_id' => null,
                'google_name' => null,
                'google_avatar' => null,
                'observaciones' => null,
                'anonimizado_at' => now(),
            ])->save();

            if (!$postulante->trashed()) {
                $postulante->delete();
            }

            RegistroTratamientoDatos::registrar(
                'supresion_ejecutada',
                'postulantes_contratacion',
                $postulante->id,
                'personal',
                "Supresión de postulante ejecutada para {$solicitud->numero_solicitud}",
                $snapshot,
                ['anonimizado_at' => now()->toIso8601String()]
            );

            $resultado['acciones']['documentos_eliminados'] = $eliminados;
            $resultado['acciones']['postulante_anonimizado'] = true;
            $resultado['advertencias'][] = 'Validar eliminación manual de copias ya enviadas a SharePoint, correo u otros repositorios externos.';
        });

        return $resultado;
    }

    private function anonimizarRespuestas(User $user, SolicitudArco $solicitud): int
    {
        $count = 0;

        Respuesta::withTrashed()
            ->where('usuario_id', $user->id)
            ->chunkById(100, function ($respuestas) use (&$count, $solicitud) {
                foreach ($respuestas as $respuesta) {
                    $datos = json_decode($respuesta->datos_json ?? '{}', true);
                    $datosAnonimizados = $this->anonimizarDatosJson($datos);

                    $respuesta->forceFill([
                        'usuario_id' => null,
                        'talana_trabajador_id' => null,
                        'datos_json' => json_encode($datosAnonimizados, JSON_UNESCAPED_UNICODE),
                        'comentario_solicitante' => self::MARCADOR . ' ' . $solicitud->numero_solicitud,
                    ])->save();

                    $count++;
                }
            });

        return $count;
    }

    private function anonimizarFirmas(User $user): int
    {
        $query = FirmaElectronica::where('firmante_id', $user->id);

        if ($user->email) {
            $query->orWhere('firmante_email', $user->email);
        }
        if ($user->rut) {
            $query->orWhere('firmante_rut', $user->rut);
        }

        return $query->update([
                'firmante_id' => null,
                'firmante_nombre' => self::MARCADOR,
                'firmante_rut' => null,
                'firmante_email' => null,
                'firmante_cargo' => null,
                'firma_imagen' => '',
                'ip_address' => null,
                'user_agent' => null,
                'latitud' => null,
                'longitud' => null,
                'geolocalizacion' => null,
            ]);
    }

    private function anonimizarLeyKarin(User $user): int
    {
        $query = LeyKarin::withTrashed()->where('denunciante_id', $user->id);

        if ($user->email) {
            $query->orWhere('denunciante_email', $user->email);
        }
        if ($user->rut) {
            $query->orWhere('denunciante_rut', $user->rut);
        }

        return $query->update([
                'denunciante_id' => null,
                'denunciante_nombre' => self::MARCADOR,
                'denunciante_rut' => null,
                'denunciante_email' => null,
                'denunciante_latitud' => null,
                'denunciante_longitud' => null,
                'consentimiento_geolocalizacion' => false,
            ]);
    }

    private function eliminarArchivosAdjuntosUsuario(User $user): int
    {
        $count = 0;

        ArchivoAdjunto::where('subido_por', $user->id)
            ->chunkById(100, function ($archivos) use (&$count) {
                foreach ($archivos as $archivo) {
                    Storage::disk('local')->delete($archivo->ruta);
                    Storage::disk('public')->delete($archivo->ruta);
                    $archivo->delete();
                    $count++;
                }
            });

        return $count;
    }

    private function anonimizarDatosJson(mixed $value): mixed
    {
        if (!is_array($value)) {
            return is_scalar($value) && $value !== '' ? self::MARCADOR : $value;
        }

        if (isset($value['path'])) {
            Storage::disk('public')->delete($value['path']);
            Storage::disk('local')->delete($value['path']);

            return [
                'name' => self::MARCADOR,
                'path' => null,
                'mime' => $value['mime'] ?? null,
                'size' => null,
            ];
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = $this->anonimizarDatosJson($item);
        }

        return $result;
    }

    private function hashIdentifier(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return hash('sha256', strtolower(trim($value)));
    }
}
