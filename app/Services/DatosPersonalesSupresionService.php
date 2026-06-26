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

    private const LEGAL_EVIDENCE_KEYWORDS = [
        'accidente',
        'acta',
        'auditoria',
        'capacitación',
        'capacitacion',
        'charla',
        'comite',
        'comité',
        'entrega',
        'epp',
        'firma',
        'inspeccion',
        'inspección',
        'karin',
        'ley karin',
        'prevencion',
        'prevención',
        'riesgo',
        'sst',
        'vehiculo',
        'vehículo',
    ];

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

            $resultado['acciones']['respuestas'] = $this->anonimizarRespuestas($user, $solicitud);
            $resultado['acciones']['firmas'] = $this->anonimizarFirmas($user);
            $resultado['acciones']['ley_karin_anonimizadas'] = $this->anonimizarLeyKarin($user);
            $resultado['acciones']['archivos_adjuntos'] = $this->eliminarArchivosAdjuntosUsuario($user);

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
            $resultado['advertencias'][] = 'No se suprimen automaticamente evidencias legales de SST, capacitaciones, charlas, actas, EPP, accidentes o firmas que deban conservarse por obligacion legal, cumplimiento laboral, investigacion o defensa de derechos. Esos registros quedan sujetos a bloqueo/restriccion de usos no obligatorios.';
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

    public function ejecutarParaSolicitudPublica(SolicitudArco $solicitud, User $responsable): array
    {
        $resultado = [
            'titular_externo' => [
                'nombre' => $solicitud->titular_nombre,
                'email_hash' => $this->hashIdentifier($solicitud->titular_email),
                'rut_hash' => $this->hashIdentifier($solicitud->titular_rut),
                'contexto' => $solicitud->titular_contexto,
            ],
            'solicitud' => $solicitud->numero_solicitud,
            'ejecutado_por' => $responsable->id,
            'ejecutado_at' => now()->toIso8601String(),
            'acciones' => [],
            'advertencias' => [],
            'encargados_externos' => $this->externalProcessorChecklist(),
        ];

        if (!$this->hasExternalIdentifier($solicitud)) {
            $resultado['advertencias'][] = 'La solicitud pública no contiene email ni RUT suficiente para buscar registros internos de forma automatizada.';
            return $resultado;
        }

        $postulantes = $this->postulantesPorTitular($solicitud);
        $resultado['acciones']['postulantes_coincidentes'] = $postulantes->count();
        $resultado['acciones']['postulantes_anonimizados'] = 0;

        foreach ($postulantes as $postulante) {
            $this->ejecutarParaPostulante($postulante, $solicitud, $responsable);
            $resultado['acciones']['postulantes_anonimizados']++;
        }

        $resultado['acciones']['ley_karin_anonimizadas'] = $this->anonimizarLeyKarinExterna($solicitud);

        if ($resultado['acciones']['postulantes_coincidentes'] === 0 && $resultado['acciones']['ley_karin_anonimizadas'] === 0) {
            $resultado['advertencias'][] = 'No se encontraron registros automatizables por email/RUT. Revisar manualmente otros módulos o archivos externos asociados al titular.';
        }

        $resultado['advertencias'][] = 'Revisar manualmente copias fuera de la base de datos principal: SharePoint, correo, Kizeo, Google Drive, respaldos y expedientes administrativos.';

        RegistroTratamientoDatos::registrar(
            'supresion_publica_ejecutada',
            'solicitudes_arco',
            $solicitud->id,
            'personal',
            "Supresión pública ejecutada para {$solicitud->numero_solicitud}",
            null,
            $resultado
        );

        return $resultado;
    }

    private function anonimizarRespuestas(User $user, SolicitudArco $solicitud): array
    {
        $resultado = [
            'anonimizadas' => 0,
            'preservadas_por_conservacion_legal' => 0,
        ];

        Respuesta::withTrashed()
            ->with('formulario.categoria')
            ->where('usuario_id', $user->id)
            ->chunkById(100, function ($respuestas) use (&$resultado, $solicitud) {
                foreach ($respuestas as $respuesta) {
                    if ($this->debePreservarRespuesta($respuesta)) {
                        $resultado['preservadas_por_conservacion_legal']++;
                        continue;
                    }

                    $datos = json_decode($respuesta->datos_json ?? '{}', true);
                    $datosAnonimizados = $this->anonimizarDatosJson($datos);

                    $respuesta->forceFill([
                        'usuario_id' => null,
                        'talana_trabajador_id' => null,
                        'datos_json' => json_encode($datosAnonimizados, JSON_UNESCAPED_UNICODE),
                        'comentario_solicitante' => self::MARCADOR . ' ' . $solicitud->numero_solicitud,
                    ])->save();

                    $resultado['anonimizadas']++;
                }
            });

        return $resultado;
    }

    private function anonimizarFirmas(User $user): array
    {
        $resultado = [
            'anonimizadas' => 0,
            'preservadas_por_conservacion_legal' => 0,
        ];

        $query = FirmaElectronica::where('firmante_id', $user->id);

        if ($user->email) {
            $query->orWhere('firmante_email', $user->email);
        }
        if ($user->rut) {
            $query->orWhere('firmante_rut', $user->rut);
        }

        $query->chunkById(100, function ($firmas) use (&$resultado) {
            foreach ($firmas as $firma) {
                if ($this->debePreservarFirma($firma)) {
                    $resultado['preservadas_por_conservacion_legal']++;
                    continue;
                }

                $firma->forceFill([
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
                ])->save();

                $resultado['anonimizadas']++;
            }
        });

        return $resultado;
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

    private function anonimizarLeyKarinExterna(SolicitudArco $solicitud): int
    {
        $query = LeyKarin::withTrashed()->where(function ($q) use ($solicitud) {
            if ($solicitud->titular_email) {
                $q->where('denunciante_email', strtolower(trim($solicitud->titular_email)));
            }

            foreach ($this->rutVariants($solicitud->titular_rut) as $rut) {
                $q->orWhere('denunciante_rut', $rut);
            }
        });

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

    private function postulantesPorTitular(SolicitudArco $solicitud)
    {
        return PostulanteContratacion::withTrashed()
            ->where(function ($q) use ($solicitud) {
                if ($solicitud->titular_email) {
                    $q->where('email', strtolower(trim($solicitud->titular_email)));
                }

                foreach ($this->rutVariants($solicitud->titular_rut) as $rut) {
                    $q->orWhere('rut', $rut);
                }
            })
            ->get();
    }

    private function hasExternalIdentifier(SolicitudArco $solicitud): bool
    {
        return filled($solicitud->titular_email) || filled($solicitud->titular_rut);
    }

    private function rutVariants(?string $rut): array
    {
        if (!$rut) {
            return [];
        }

        $plain = strtoupper(preg_replace('/[^0-9Kk]/', '', $rut));
        if (strlen($plain) < 2) {
            return [trim($rut)];
        }

        $dv = substr($plain, -1);
        $number = substr($plain, 0, -1);
        $formatted = number_format((int) $number, 0, '', '.') . '-' . $dv;

        return array_values(array_unique([
            trim($rut),
            $plain,
            $number . '-' . $dv,
            $formatted,
        ]));
    }

    private function externalProcessorChecklist(): array
    {
        return collect(config('proteccion_datos.external_processors', []))
            ->map(fn (array $processor) => [
                'nombre' => $processor['nombre'] ?? 'Encargado externo',
                'accion_requerida' => $processor['accion_supresion'] ?? 'Revisión manual',
            ])
            ->values()
            ->all();
    }

    private function eliminarArchivosAdjuntosUsuario(User $user): array
    {
        $resultado = [
            'eliminados' => 0,
            'preservados_por_conservacion_legal' => 0,
        ];

        ArchivoAdjunto::where('subido_por', $user->id)
            ->chunkById(100, function ($archivos) use (&$resultado) {
                foreach ($archivos as $archivo) {
                    if ($this->debePreservarArchivo($archivo)) {
                        $resultado['preservados_por_conservacion_legal']++;
                        continue;
                    }

                    Storage::disk('local')->delete($archivo->ruta);
                    Storage::disk('public')->delete($archivo->ruta);
                    $archivo->delete();
                    $resultado['eliminados']++;
                }
            });

        return $resultado;
    }

    private function debePreservarRespuesta(Respuesta $respuesta): bool
    {
        $formulario = $respuesta->formulario;
        if (!$formulario) {
            return false;
        }

        return $this->textoIndicaEvidenciaLegal(implode(' ', array_filter([
            $formulario->codigo ?? null,
            $formulario->nombre ?? null,
            $formulario->descripcion ?? null,
            $formulario->categoria?->nombre ?? null,
            $formulario->categoria?->descripcion ?? null,
        ])));
    }

    private function debePreservarFirma(FirmaElectronica $firma): bool
    {
        if ($this->textoIndicaEvidenciaLegal($firma->entidad_tipo . ' ' . $firma->proposito)) {
            return true;
        }

        if ($firma->entidad_tipo === 'respuesta') {
            $respuesta = Respuesta::withTrashed()->with('formulario.categoria')->find($firma->entidad_id);
            return $respuesta ? $this->debePreservarRespuesta($respuesta) : false;
        }

        return false;
    }

    private function debePreservarArchivo(ArchivoAdjunto $archivo): bool
    {
        if ($this->textoIndicaEvidenciaLegal($archivo->entidad_tipo . ' ' . $archivo->campo_formulario . ' ' . $archivo->nombre_original)) {
            return true;
        }

        if ($archivo->entidad_tipo === 'respuesta') {
            $respuesta = Respuesta::withTrashed()->with('formulario.categoria')->find($archivo->entidad_id);
            return $respuesta ? $this->debePreservarRespuesta($respuesta) : false;
        }

        return false;
    }

    private function textoIndicaEvidenciaLegal(?string $texto): bool
    {
        $texto = Str::lower($texto ?? '');

        foreach (self::LEGAL_EVIDENCE_KEYWORDS as $keyword) {
            if (Str::contains($texto, $keyword)) {
                return true;
            }
        }

        return false;
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
