<?php

namespace App\Services;

use App\Models\Configuracion;

class MailAutomationService
{
    public const GLOBAL_KEY = 'notificaciones_email';
    public const CUSTOM_MAIL_KEY = '__saep_mail_key';

    /**
     * Catalogo de correos automaticos controlables desde el monitor.
     */
    public function registry(): array
    {
        return [
            'BienvenidaUsuarioMail' => [
                'label' => 'Bienvenida de usuario',
                'category' => 'Usuarios',
                'description' => 'Credenciales iniciales y bienvenida al crear o reactivar usuarios.',
                'default' => true,
                'critical' => true,
            ],
            'PasswordResetMail' => [
                'label' => 'Restablecimiento de clave',
                'category' => 'Usuarios',
                'description' => 'Enlaces para recuperar acceso a la plataforma.',
                'default' => true,
                'critical' => true,
            ],
            'ContratacionAcuseReciboMail' => [
                'label' => 'Acuse postulante',
                'category' => 'Contratacion',
                'description' => 'Confirmacion enviada al postulante al recibir su postulacion.',
                'default' => true,
                'critical' => false,
            ],
            'ContratacionNuevoPostulanteMail' => [
                'label' => 'Nuevo postulante RRHH',
                'category' => 'Contratacion',
                'description' => 'Aviso interno a RRHH cuando ingresa un nuevo postulante.',
                'default' => true,
                'critical' => false,
            ],
            'ComercialCotizacionMail' => [
                'label' => 'Cotizacion comercial',
                'category' => 'Comercial',
                'description' => 'Envio manual de cotizaciones comerciales con PDF adjunto.',
                'default' => true,
                'critical' => false,
            ],
            'RespuestaCreadaMail' => [
                'label' => 'Formulario pendiente',
                'category' => 'Formularios',
                'description' => 'Notificacion a aprobadores cuando una respuesta requiere revision.',
                'default' => true,
                'critical' => false,
            ],
            'RespuestaFormularioMail' => [
                'label' => 'Confirmacion formulario',
                'category' => 'Formularios',
                'description' => 'Copia o reenvio de respuesta de formulario con adjuntos.',
                'default' => true,
                'critical' => false,
            ],
            'RespuestaAprobadaMail' => [
                'label' => 'Resultado aprobacion',
                'category' => 'Formularios',
                'description' => 'Aviso al solicitante cuando una respuesta se aprueba o rechaza.',
                'default' => true,
                'critical' => false,
            ],
            'LeyKarinAcuseReciboMail' => [
                'label' => 'Acuse Ley Karin',
                'category' => 'Ley Karin',
                'description' => 'Confirmacion de recepcion de denuncia al trabajador.',
                'default' => true,
                'critical' => true,
            ],
            'LeyKarinDenunciaMail' => [
                'label' => 'Nueva denuncia Ley Karin',
                'category' => 'Ley Karin',
                'description' => 'Aviso interno cuando se registra una denuncia.',
                'default' => true,
                'critical' => true,
            ],
            'LeyKarinResolucionMail' => [
                'label' => 'Resolucion Ley Karin',
                'category' => 'Ley Karin',
                'description' => 'Notificacion de cierre o resolucion de denuncia.',
                'default' => true,
                'critical' => true,
            ],
            'KanbanTareaAsignadaMail' => [
                'label' => 'Tarea Kanban asignada',
                'category' => 'Kanban',
                'description' => 'Aviso al responsable cuando se asigna una tarea.',
                'default' => true,
                'critical' => false,
            ],
            'KanbanVencimientoMail' => [
                'label' => 'Vencimiento Kanban',
                'category' => 'Kanban',
                'description' => 'Recordatorio automatico de tareas proximas a vencer.',
                'default' => true,
                'critical' => false,
            ],
            'SstActividadAlertaMail' => [
                'label' => 'Alertas SST',
                'category' => 'Prevencion SST',
                'description' => 'Recordatorios y alertas de actividades SST.',
                'default' => true,
                'critical' => false,
            ],
            'CharlaTrackingReporteMail' => [
                'label' => 'Reporte charlas SST',
                'category' => 'Prevencion SST',
                'description' => 'Reporte periodico de cumplimiento de charlas.',
                'default' => true,
                'critical' => false,
            ],
            'StopReporteMail' => [
                'label' => 'Reporte STOP',
                'category' => 'Prevencion SST',
                'description' => 'Reportes semanales o mensuales de Tarjeta STOP.',
                'default' => true,
                'critical' => false,
            ],
            'VehiculoEntregaMail' => [
                'label' => 'Acta entrega vehiculo',
                'category' => 'Kizeo / Vehiculos',
                'description' => 'Acta de entrega de vehiculo enviada desde Kizeo.',
                'default' => true,
                'critical' => false,
            ],
            'VehiculoDevolucionMail' => [
                'label' => 'Acta devolucion vehiculo',
                'category' => 'Kizeo / Vehiculos',
                'description' => 'Acta de devolucion de vehiculo enviada desde Kizeo.',
                'default' => true,
                'critical' => false,
            ],
        ];
    }

    public function ensureDefaults(): void
    {
        $this->ensureConfig(
            self::GLOBAL_KEY,
            'true',
            'Interruptor global de envio de correos automaticos.',
            'notificaciones'
        );

        foreach ($this->registry() as $key => $definition) {
            $this->ensureConfig(
                $this->configKey($key),
                $definition['default'] ? 'true' : 'false',
                'Automatizacion email: '.$definition['label'],
                'mail_automations'
            );
        }
    }

    public function all(): array
    {
        $this->ensureDefaults();

        $items = [];
        foreach ($this->registry() as $key => $definition) {
            $items[] = [
                'key' => $key,
                'config_key' => $this->configKey($key),
                'label' => $definition['label'],
                'category' => $definition['category'],
                'description' => $definition['description'],
                'critical' => (bool) $definition['critical'],
                'enabled' => $this->isEnabledKey($key),
            ];
        }

        return $items;
    }

    public function configKey(string $key): string
    {
        return 'mail_auto_'.$key.'_enabled';
    }

    public function isGlobalEnabled(): bool
    {
        return $this->toBool(Configuracion::get(self::GLOBAL_KEY, 'true'));
    }

    public function setGlobalEnabled(bool $enabled): void
    {
        $this->ensureDefaults();
        Configuracion::where('clave', self::GLOBAL_KEY)
            ->update(['valor' => $enabled ? 'true' : 'false', 'updated_at' => now()]);
    }

    public function setEnabled(string $key, bool $enabled): void
    {
        if (! array_key_exists($key, $this->registry())) {
            return;
        }

        $this->ensureDefaults();
        Configuracion::where('clave', $this->configKey($key))
            ->update(['valor' => $enabled ? 'true' : 'false', 'updated_at' => now()]);
    }

    public function isEnabledFor(?string $key): bool
    {
        if (! $this->isGlobalEnabled()) {
            return false;
        }

        if (! $key || ! array_key_exists($key, $this->registry())) {
            return true;
        }

        return $this->isEnabledKey($key);
    }

    public function disabledReason(?string $key): string
    {
        if (! $this->isGlobalEnabled()) {
            return 'Envio global de correos automaticos desactivado desde el Monitor de Correos.';
        }

        $label = $key && isset($this->registry()[$key])
            ? $this->registry()[$key]['label']
            : ($key ?: 'correo sin clasificar');

        return "Automatizacion de email desactivada: {$label}.";
    }

    public function resolveKeyFromData(array $data): ?string
    {
        $mailableClass = $data['__laravel_mailable'] ?? null;
        if ($mailableClass) {
            return class_basename($mailableClass);
        }

        $customKey = $data[self::CUSTOM_MAIL_KEY] ?? null;
        if ($customKey) {
            return class_basename($customKey);
        }

        return null;
    }

    private function isEnabledKey(string $key): bool
    {
        $definition = $this->registry()[$key] ?? null;
        if (! $definition) {
            return true;
        }

        return $this->toBool(
            Configuracion::get($this->configKey($key), $definition['default'] ? 'true' : 'false')
        );
    }

    private function ensureConfig(string $clave, string $valor, string $descripcion, string $categoria): void
    {
        $config = Configuracion::firstOrNew(['clave' => $clave]);

        if (! $config->exists) {
            $config->valor = $valor;
        }

        $config->tipo = 'BOOLEAN';
        $config->categoria = $categoria;
        $config->descripcion = $descripcion;
        $config->editable = true;
        $config->save();
    }

    private function toBool(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'si', 'sí', 'yes', 'on'], true);
    }
}
