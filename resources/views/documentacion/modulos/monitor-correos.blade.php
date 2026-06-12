@extends('layouts.app')

@section('title', 'Documentación — Monitor de Correos')

@section('content')
<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading">
                <i class="bi bi-envelope-check-fill" style="color:var(--primary-color);"></i>
                Monitor de Correos
            </h2>
            <p class="page-subheading">Sistema de registro, seguimiento y auditoría de todos los correos transaccionales de la plataforma</p>
        </div>
        <a href="{{ route('documentacion.index') }}" class="btn-ghost">
            <i class="bi bi-arrow-left"></i> Documentación
        </a>
    </div>

    {{-- Nav interna --}}
    <div class="glass-card" style="margin-bottom:1.5rem;padding:1rem 1.25rem;">
        <strong style="font-size:.85rem;color:var(--text-muted);display:block;margin-bottom:.5rem;">Contenido</strong>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
            <a href="#arquitectura" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Arquitectura</a>
            <a href="#tabla" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Tabla mail_logs</a>
            <a href="#listener" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Listener automático</a>
            <a href="#fallos" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Registro de fallos</a>
            <a href="#rutas" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Rutas y vistas</a>
            <a href="#funciones" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">Funcionalidades</a>
        </div>
    </div>

    <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem;align-items:center;">
        <span class="badge success">v1.0 — Activo</span>
        <span style="font-size:.8rem;color:var(--text-muted);">Acceso: <code>modulo:configuracion</code></span>
        <span style="font-size:.8rem;color:var(--text-muted);">Ruta: <code>/configuracion/mail-logs</code></span>
        <span style="font-size:.8rem;color:var(--text-muted);">Commits: <code>1292b40</code> / <code>c940b5b</code></span>
    </div>

    {{-- 1. Arquitectura --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="arquitectura">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">1</span>
                Arquitectura del Sistema
            </h3>
        </div>
        <p style="margin:0 0 1rem;font-size:.875rem;line-height:1.6;">
            El monitoreo funciona de forma completamente transparente para el resto de la plataforma. No requiere cambiar ningún
            Mailable existente. El sistema captura los correos en dos vías complementarias:
        </p>
        <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:1.25rem;">
            @foreach([
                ['bi-broadcast','#3b82f6','<strong>Automático (enviados):</strong> El listener <code>LogMailSent</code> escucha el evento <code>Illuminate\Mail\Events\MessageSent</code> que Laravel dispara para <em>todos</em> los correos enviados exitosamente.'],
                ['bi-slash-circle-fill','#f59e0b','<strong>Automático (bloqueados):</strong> El listener <code>BlockDisabledMailAutomation</code> escucha <code>MessageSending</code>, revisa los switches del monitor y puede cancelar el envío antes del SMTP.'],
                ['bi-bug-fill','#ef4444','<strong>Manual (fallidos):</strong> En los bloques <code>catch</code> de los controladores, se llama a <code>MailLog::recordFailed()</code> para registrar los errores de envío.'],
            ] as [$icono,$color,$texto])
            <div style="display:flex;align-items:flex-start;gap:.75rem;padding:.75rem;background:var(--surface-bg);border-radius:.5rem;">
                <i class="bi {{ $icono }}" style="color:{{ $color }};font-size:1.2rem;flex-shrink:0;margin-top:.05rem;"></i>
                <span style="font-size:.875rem;line-height:1.6;">{!! $texto !!}</span>
            </div>
            @endforeach
        </div>

        {{-- Diagrama de flujo --}}
        <div style="background:var(--surface-bg);border-radius:.5rem;padding:1.25rem;">
            <strong style="font-size:.8rem;color:var(--text-muted);display:block;margin-bottom:1rem;">FLUJO DE REGISTRO</strong>
            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;font-size:.8rem;">
                <div style="background:var(--primary-color);color:#fff;padding:.4rem .8rem;border-radius:.4rem;white-space:nowrap;">Controlador llama Mail::send()</div>
                <i class="bi bi-arrow-right" style="color:var(--text-muted);"></i>
                <div style="background:rgba(59,130,246,0.12);border:1px solid rgba(59,130,246,0.3);padding:.4rem .8rem;border-radius:.4rem;white-space:nowrap;">MessageSending</div>
                <i class="bi bi-arrow-right" style="color:var(--text-muted);"></i>
                <div style="background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.3);padding:.4rem .8rem;border-radius:.4rem;white-space:nowrap;">Switches email</div>
                <i class="bi bi-arrow-right" style="color:var(--text-muted);"></i>
                <div style="background:rgba(59,130,246,0.12);border:1px solid rgba(59,130,246,0.3);padding:.4rem .8rem;border-radius:.4rem;white-space:nowrap;">SMTP / Mailer</div>
                <i class="bi bi-arrow-right" style="color:var(--text-muted);"></i>
                <div style="display:flex;flex-direction:column;gap:.3rem;">
                    <div style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);padding:.35rem .75rem;border-radius:.4rem;white-space:nowrap;font-size:.75rem;">✅ OK → MessageSent → LogMailSent → mail_logs (status: sent)</div>
                    <div style="background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.3);padding:.35rem .75rem;border-radius:.4rem;white-space:nowrap;font-size:.75rem;">Bloqueado → BlockDisabledMailAutomation → mail_logs (status: blocked)</div>
                    <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);padding:.35rem .75rem;border-radius:.4rem;white-space:nowrap;font-size:.75rem;">❌ Error → catch block → MailLog::recordFailed() → mail_logs (status: failed)</div>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. Tabla --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="tabla">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">2</span>
                Tabla <code>mail_logs</code>
            </h3>
        </div>
        <p style="margin:0 0 1rem;font-size:.875rem;color:var(--text-muted);">
            Migración: <code>2026_04_27_200000_create_mail_logs_table</code>
        </p>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.8rem;">
                <thead>
                    <tr style="background:var(--surface-bg);">
                        <th style="text-align:left;padding:.5rem .75rem;border-bottom:1px solid var(--surface-border);">Campo</th>
                        <th style="text-align:left;padding:.5rem .75rem;border-bottom:1px solid var(--surface-border);">Tipo</th>
                        <th style="text-align:left;padding:.5rem .75rem;border-bottom:1px solid var(--surface-border);">Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach([
                        ['id','bigint (PK)','Clave primaria autoincremental'],
                        ['mailable','varchar(255) nullable','Clase PHP del Mailable (ej: App\\Mail\\BienvenidaUsuarioMail)'],
                        ['subject','varchar(500) nullable','Asunto del correo'],
                        ['to_email','varchar(255)','Dirección de destino'],
                        ['to_name','varchar(255) nullable','Nombre del destinatario'],
                        ['status','enum(sent,failed,blocked)','Estado del envío o bloqueo preventivo'],
                        ['error_message','text nullable','Mensaje de error o motivo de bloqueo'],
                        ['body_html','longtext nullable','Cuerpo HTML del correo (para preview)'],
                        ['sent_at','timestamp nullable','Timestamp del intento de envío'],
                        ['created_at / updated_at','timestamps','Timestamps de Laravel'],
                    ] as [$campo,$tipo,$desc])
                    <tr style="border-bottom:1px solid var(--surface-border);">
                        <td style="padding:.5rem .75rem;"><code style="font-size:.75rem;">{{ $campo }}</code></td>
                        <td style="padding:.5rem .75rem;color:var(--text-muted);white-space:nowrap;">{{ $tipo }}</td>
                        <td style="padding:.5rem .75rem;color:var(--text-muted);">{{ $desc }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- 3. Listener --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="listener">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">3</span>
                Listeners Automáticos — <code>BlockDisabledMailAutomation</code> / <code>LogMailSent</code>
            </h3>
        </div>
        <div style="display:flex;flex-direction:column;gap:.875rem;">
            <div>
                <strong style="font-size:.875rem;display:block;margin-bottom:.4rem;">Ubicación</strong>
                <code style="font-size:.8rem;">app/Listeners/BlockDisabledMailAutomation.php</code><br>
                <code style="font-size:.8rem;">app/Listeners/LogMailSent.php</code>
            </div>
            <div>
                <strong style="font-size:.875rem;display:block;margin-bottom:.4rem;">Eventos que escucha</strong>
                <code style="font-size:.8rem;">Illuminate\Mail\Events\MessageSending</code>
                <span style="display:block;font-size:.8rem;color:var(--text-muted);margin-top:.25rem;">Permite bloquear un correo antes de pasar al transporte SMTP.</span>
                <code style="font-size:.8rem;display:inline-block;margin-top:.5rem;">Illuminate\Mail\Events\MessageSent</code>
                <span style="display:block;font-size:.8rem;color:var(--text-muted);margin-top:.25rem;">Laravel lo dispara automáticamente después de cada envío exitoso.</span>
            </div>
            <div>
                <strong style="font-size:.875rem;display:block;margin-bottom:.4rem;">Registro automático</strong>
                <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;font-family:monospace;font-size:.78rem;line-height:1.8;overflow-x:auto;">
                    Laravel descubre estos listeners por la firma del metodo <code>handle()</code>.<br>
                    No registrar manualmente con <code>Event::listen()</code>, porque se duplicaria la bitacora.
                </div>
            </div>
            <div>
                <strong style="font-size:.875rem;display:block;margin-bottom:.4rem;">Qué captura el listener</strong>
                <ul style="margin:.25rem 0;padding-left:1.25rem;font-size:.875rem;line-height:2;color:var(--text-muted);">
                    <li>Clase del Mailable (<code>mailable</code>) desde el atributo de la instancia</li>
                    <li>Asunto del mensaje (<code>subject</code>)</li>
                    <li>Email y nombre del primer destinatario (<code>to_email</code>, <code>to_name</code>)</li>
                    <li>HTML del body del correo (<code>body_html</code>) para preview</li>
                    <li>Timestamp actual (<code>sent_at</code>)</li>
                    <li>Status siempre <code>sent</code> (ya que el evento solo se dispara en éxito)</li>
                    <li>Intentos bloqueados con status <code>blocked</code> y motivo de configuración</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- 4. Registro de fallos --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="fallos">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">4</span>
                Registro Manual de Fallos — <code>MailLog::recordFailed()</code>
            </h3>
        </div>
        <p style="margin:0 0 1rem;font-size:.875rem;line-height:1.6;">
            Cuando un envío falla (excepción capturada), se debe registrar manualmente el fallo
            usando el método estático <code>recordFailed()</code> del modelo <code>MailLog</code>.
        </p>
        <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;font-family:monospace;font-size:.78rem;line-height:1.8;overflow-x:auto;margin-bottom:1rem;">
            <span style="color:#6b7280;">// Firma del método</span><br>
            MailLog::<span style="color:#3b82f6;">recordFailed</span>(<br>
            &nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#10b981;">string</span> $toEmail,<br>
            &nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#10b981;">string</span> $subject,<br>
            &nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#10b981;">string</span> $errorMessage,<br>
            &nbsp;&nbsp;&nbsp;&nbsp;?<span style="color:#10b981;">string</span> $mailable = <span style="color:#f59e0b;">null</span><br>
            );<br><br>
            <span style="color:#6b7280;">// Ejemplo de uso en controlador</span><br>
            <span style="color:#8b5cf6;">try</span> {<br>
            &nbsp;&nbsp;&nbsp;&nbsp;Mail::<span style="color:#3b82f6;">to</span>($postulante->email)-><span style="color:#3b82f6;">send</span>(<span style="color:#8b5cf6;">new</span> ContratacionAcuseReciboMail($postulante));<br>
            } <span style="color:#8b5cf6;">catch</span> (\Exception $e) {<br>
            &nbsp;&nbsp;&nbsp;&nbsp;MailLog::<span style="color:#3b82f6;">recordFailed</span>($postulante->email, 'Acuse de recibo', $e->getMessage(), ContratacionAcuseReciboMail::class);<br>
            &nbsp;&nbsp;&nbsp;&nbsp;Log::<span style="color:#3b82f6;">error</span>('Error enviando acuse: ' . $e->getMessage());<br>
            }
        </div>
        <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);border-radius:.5rem;padding:.875rem;font-size:.85rem;">
            <strong><i class="bi bi-exclamation-diamond-fill" style="color:#ef4444;"></i> Importante:</strong>
            <span style="color:var(--text-muted);"> El listener automático solo registra envíos <em>exitosos</em>. Para que los fallos queden en el monitor, siempre agregar <code>recordFailed()</code> en los catch blocks de envíos de correo.</span>
        </div>
    </div>

    {{-- 5. Rutas --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="rutas">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">5</span>
                Rutas del Módulo
            </h3>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.8rem;">
                <thead>
                    <tr style="background:var(--surface-bg);">
                        <th style="text-align:left;padding:.5rem .75rem;border-bottom:1px solid var(--surface-border);">Método</th>
                        <th style="text-align:left;padding:.5rem .75rem;border-bottom:1px solid var(--surface-border);">Ruta</th>
                        <th style="text-align:left;padding:.5rem .75rem;border-bottom:1px solid var(--surface-border);">Acción del Controlador</th>
                        <th style="text-align:left;padding:.5rem .75rem;border-bottom:1px solid var(--surface-border);">Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach([
                        ['GET','POST','mail-logs.index','Lista de logs con filtros y stats'],
                        ['GET','POST','mail-logs.show','Obtener HTML body de un log (para preview modal)'],
                        ['DELETE','POST','mail-logs.destroy','Eliminar un registro de log'],
                        ['POST','POST','mail-logs.limpiar','Eliminar logs en rango de fechas'],
                    ] as [$metodo,$tipo,$name,$desc])
                    <tr style="border-bottom:1px solid var(--surface-border);">
                        <td style="padding:.5rem .75rem;">
                            <span style="background:{{ $metodo==='GET' ? 'rgba(16,185,129,0.1)' : ($metodo==='DELETE' ? 'rgba(239,68,68,0.1)' : 'rgba(245,158,11,0.1)') }};color:{{ $metodo==='GET' ? '#10b981' : ($metodo==='DELETE' ? '#ef4444' : '#f59e0b') }};padding:.2rem .5rem;border-radius:.25rem;font-size:.75rem;font-weight:600;">{{ $metodo }}</span>
                        </td>
                        <td style="padding:.5rem .75rem;"><code style="font-size:.75rem;">{{ $name }}</code></td>
                        <td style="padding:.5rem .75rem;"><code style="font-size:.75rem;">MailLogController</code></td>
                        <td style="padding:.5rem .75rem;color:var(--text-muted);">{{ $desc }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- 6. Funcionalidades vista --}}
    <div class="glass-card" style="margin-bottom:1.5rem;" id="funciones">
        <div style="border-bottom:1px solid var(--surface-border);padding-bottom:.75rem;margin-bottom:1.25rem;">
            <h3 style="margin:0;font-size:1.1rem;display:flex;align-items:center;gap:.5rem;">
                <span style="background:var(--primary-color);color:#fff;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.85rem;">6</span>
                Funcionalidades de la Vista
            </h3>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:.875rem;">
            @foreach([
                ['bi-bar-chart-fill','#3b82f6','Estadísticas rápidas','Total de correos, enviados, fallidos, bloqueados y actividad del día.'],
                ['bi-toggles','#f59e0b','Switches de automatización','Encendido global y control por tipo de correo desde el monitor.'],
                ['bi-funnel-fill','#8b5cf6','Filtros','Buscar por email de destino, asunto del correo, tipo de mail y estado (todos / enviados / fallidos / bloqueados).'],
                ['bi-table','#10b981','Tabla paginada','Lista ordenada por fecha descendente con columnas: estado, asunto, destinatario, clase Mailable y fecha/hora.'],
                ['bi-eye-fill','#f59e0b','Preview HTML','Modal que renderiza el HTML del correo dentro de un <code>&lt;iframe&gt;</code> sandboxed para previsualizar el contenido.'],
                ['bi-trash3-fill','#ef4444','Limpiar registros','Modal de confirmación para eliminar logs de un rango de fechas. Protegido con confirmación antes de borrar.'],
                ['bi-patch-check-fill','#6366f1','Sin dependencia de CSS externo','El modal "Limpiar" usa <code>style="display:none"</code> inline. La plataforma no usa Tailwind, no existe clase <code>.hidden</code>.'],
            ] as [$icono,$color,$titulo,$desc])
            <div style="background:var(--surface-bg);border-radius:.5rem;padding:1rem;">
                <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.5rem;">
                    <i class="bi {{ $icono }}" style="color:{{ $color }};font-size:1.1rem;"></i>
                    <strong style="font-size:.875rem;">{{ $titulo }}</strong>
                </div>
                <p style="margin:0;font-size:.8rem;line-height:1.6;color:var(--text-muted);">{!! $desc !!}</p>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Nota técnica --}}
    <div style="background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.25);border-radius:.75rem;padding:1.25rem;font-size:.875rem;">
        <strong style="display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem;">
            <i class="bi bi-lightbulb-fill" style="color:#6366f1;"></i>
            Lección técnica — CSS de la plataforma
        </strong>
        <p style="margin:0;line-height:1.6;color:var(--text-muted);">
            La plataforma usa <strong>CSS personalizado</strong>, NO Tailwind. Por tanto, la clase <code>.hidden</code> no existe.
            Para ocultar elementos se debe usar siempre <code>style="display:none"</code> inline, y para mostrar
            <code>style="display:flex"</code> o <code>style="display:block"</code> desde JavaScript.
            Esta lección fue aprendida al desarrollar el modal de "Limpiar" (commit <code>c940b5b</code>).
        </p>
    </div>

</div>
@endsection
