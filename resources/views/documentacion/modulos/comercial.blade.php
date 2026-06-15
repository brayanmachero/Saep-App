@extends('layouts.app')

@section('title', 'Documentación — Comercial y Cotizador')

@section('content')
@php
    $comercialDocs = $comercialDocs ?? [];
    $stats = $comercialDocs['stats'] ?? [];
    $estados = $comercialDocs['estados'] ?? [];
    $categorias = $comercialDocs['categorias_parametros'] ?? [];
    $ultimaActualizacion = $comercialDocs['ultima_actualizacion'] ?? null;
    $ultimaCotizacion = $comercialDocs['ultima_cotizacion'] ?? null;
    $api = $comercialDocs['api'] ?? [];
    $auditorias = $comercialDocs['auditorias'] ?? collect();
    $money = fn ($value) => '$'.number_format((float) $value, 0, ',', '.');
    $number = fn ($value) => number_format((int) $value, 0, ',', '.');
    $estadoColor = [
        'vigente' => '#10b981',
        'aprobada' => '#2563eb',
        'en_cotizacion' => '#f59e0b',
        'rechazada' => '#ef4444',
        'cancelada' => '#64748b',
    ];
@endphp

<div class="page-container">

    <div class="page-header">
        <div>
            <h2 class="page-heading">
                <i class="bi bi-calculator-fill" style="color:var(--primary-color);"></i>
                Comercial y Cotizador
            </h2>
            <p class="page-subheading">Documentación viva del proceso comercial, cálculo EST/SUB, mantenedor, despliegue, API y operación</p>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;justify-content:flex-end;">
            @if(auth()->user()->tieneAcceso('comercial'))
            <a href="{{ route('comercial.cotizaciones.index') }}" class="btn-ghost">
                <i class="bi bi-calculator"></i> Cotizador
            </a>
            @endif
            <a href="{{ route('documentacion.index') }}" class="btn-ghost">
                <i class="bi bi-arrow-left"></i> Documentación
            </a>
        </div>
    </div>

    <div class="glass-card" style="margin-bottom:1.25rem;padding:1rem 1.25rem;">
        <strong style="font-size:.85rem;color:var(--text-muted);display:block;margin-bottom:.5rem;">Contenido</strong>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;">
            @foreach([
                ['#resumen','Resumen'],
                ['#proceso','Proceso'],
                ['#indicadores','Indicadores'],
                ['#mantenedor','Mantenedor'],
                ['#rutas','Rutas'],
                ['#api','API / Excel'],
                ['#tecnologia','Tecnología'],
                ['#scripts','Scripts'],
                ['#azure','Azure'],
                ['#correos','Correos'],
            ] as [$href,$label])
            <a href="{{ $href }}" class="btn-ghost" style="font-size:.8rem;padding:.35rem .75rem;">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    @if(!empty($comercialDocs['error']))
    <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.28);border-radius:.75rem;padding:1rem 1.25rem;margin-bottom:1.25rem;display:flex;gap:.75rem;align-items:flex-start;">
        <i class="bi bi-exclamation-triangle-fill" style="color:#f59e0b;margin-top:.12rem;"></i>
        <div>
            <strong style="display:block;color:#92400e;">Indicadores dinámicos no disponibles</strong>
            <span style="font-size:.86rem;color:var(--text-muted);line-height:1.55;">
                {{ $comercialDocs['error'] }} La documentación está disponible igualmente; revise conexión a base de datos, migraciones o logs de Azure si el problema persiste.
            </span>
        </div>
    </div>
    @endif

    <div style="display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,.65fr);gap:1rem;margin-bottom:1.25rem;" id="resumen">
        <div class="glass-card" style="padding:0;overflow:hidden;">
            <div style="background:linear-gradient(135deg,#111827 0%,#172554 58%,#fb6b32 100%);color:white;padding:1.4rem 1.5rem;">
                <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap;">
                    <div>
                        <span style="display:inline-flex;align-items:center;gap:.4rem;background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.18);border-radius:999px;padding:.28rem .65rem;font-size:.72rem;font-weight:700;text-transform:uppercase;">
                            <i class="bi bi-broadcast"></i> Documentación dinámica
                        </span>
                        <h3 style="font-size:1.65rem;margin:.8rem 0 .35rem;">Módulo Comercial SAEP</h3>
                        <p style="margin:0;max-width:760px;line-height:1.65;color:rgba(255,255,255,.84);">
                            Centraliza cotizaciones comerciales EST/SUB, parametrización, aprobación, PDF, envío por email y consulta externa de tarifas aprobadas para procesos de prefacturación o análisis.
                        </p>
                    </div>
                    <div style="min-width:180px;text-align:right;">
                        <span style="display:block;font-size:.72rem;color:rgba(255,255,255,.7);text-transform:uppercase;font-weight:700;">Ambiente</span>
                        <strong style="font-size:1.05rem;">Producción</strong>
                        <span style="display:block;font-size:.8rem;color:rgba(255,255,255,.8);">app.saep.cl</span>
                    </div>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0;border-top:1px solid var(--surface-border);">
                @foreach([
                    ['Cotizador EST/SUB','bi-diagram-3','#2563eb','Calcula valores comerciales con reglas centralizadas.'],
                    ['Mantenedor editable','bi-sliders','#7c3aed','Gobierno, tasas, márgenes, fórmulas y uniformes.'],
                    ['Auditoría completa','bi-shield-check','#10b981','Registra usuario, fecha, origen y valor anterior/nuevo.'],
                    ['API de tarifas','bi-cloud-arrow-down','#fb6b32','Consulta de valores aprobados desde Excel o sistemas externos.'],
                ] as [$titulo,$icono,$color,$desc])
                <div style="padding:1rem;border-right:1px solid var(--surface-border);min-height:128px;">
                    <i class="bi {{ $icono }}" style="font-size:1.35rem;color:{{ $color }};"></i>
                    <strong style="display:block;margin:.6rem 0 .25rem;font-size:.92rem;">{{ $titulo }}</strong>
                    <span style="display:block;font-size:.8rem;line-height:1.45;color:var(--text-muted);">{{ $desc }}</span>
                </div>
                @endforeach
            </div>
        </div>

        <div class="glass-card" style="display:flex;flex-direction:column;gap:.85rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;">
                <h3 style="margin:0;font-size:1rem;">Estado operativo</h3>
                <span class="badge success">v{{ $meta['version'] }}</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:.55rem;">
                @foreach([
                    ['Base de datos', ($comercialDocs['available'] ?? false) ? 'Disponible' : 'Sin migraciones', ($comercialDocs['available'] ?? false) ? 'success' : 'warning'],
                    ['API comercial', ($api['enabled'] ?? false) ? 'Activa' : 'Desactivada', ($api['enabled'] ?? false) ? 'success' : 'warning'],
                    ['Token API', ($api['token_configurado'] ?? false) ? 'Configurado' : 'Pendiente', ($api['token_configurado'] ?? false) ? 'success' : 'warning'],
                    ['Token por URL', ($api['query_token'] ?? false) ? 'Permitido' : 'Bloqueado', ($api['query_token'] ?? false) ? 'warning' : 'success'],
                ] as [$label,$value,$badge])
                <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;background:var(--surface-bg);border-radius:.5rem;padding:.65rem .75rem;">
                    <span style="font-size:.84rem;color:var(--text-muted);">{{ $label }}</span>
                    <span class="badge {{ $badge }}" style="font-size:.72rem;">{{ $value }}</span>
                </div>
                @endforeach
            </div>
            <div style="border-top:1px solid var(--surface-border);padding-top:.85rem;">
                <span style="display:block;font-size:.74rem;text-transform:uppercase;color:var(--text-muted);font-weight:700;">Última actividad</span>
                <strong style="display:block;margin-top:.25rem;">
                    {{ $ultimaCotizacion?->updated_at?->format('d/m/Y H:i') ?? 'Sin cotizaciones registradas' }}
                </strong>
                @if($ultimaCotizacion)
                    <span style="font-size:.8rem;color:var(--text-muted);">Cotización {{ $ultimaCotizacion->numero }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="glass-card" style="margin-bottom:1.25rem;" id="indicadores">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem;flex-wrap:wrap;">
            <h3 style="margin:0;font-size:1.05rem;display:flex;align-items:center;gap:.5rem;">
                <i class="bi bi-speedometer2"></i> Indicadores del módulo
            </h3>
            <span style="font-size:.8rem;color:var(--text-muted);">Datos leídos en tiempo real desde tablas comerciales</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.75rem;">
            @foreach([
                ['Cotizaciones', $number($stats['cotizaciones'] ?? 0), 'bi-file-earmark-text', '#2563eb'],
                ['Vigentes', $number($stats['vigentes'] ?? 0), 'bi-check-circle', '#10b981'],
                ['Aprobadas', $number($stats['aprobadas'] ?? 0), 'bi-patch-check', '#7c3aed'],
                ['Clientes', $number($stats['clientes'] ?? 0), 'bi-building', '#fb6b32'],
                ['Centros', $number($stats['centros'] ?? 0), 'bi-diagram-3', '#0891b2'],
                ['Parámetros', $number($stats['parametros'] ?? 0), 'bi-sliders', '#64748b'],
            ] as [$label,$value,$icon,$color])
            <div style="background:var(--surface-bg);border:1px solid var(--surface-border);border-radius:.65rem;padding:.9rem;display:flex;align-items:center;gap:.75rem;">
                <div style="width:42px;height:42px;border-radius:.65rem;background:{{ $color }}18;color:{{ $color }};display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0;">
                    <i class="bi {{ $icon }}"></i>
                </div>
                <div style="min-width:0;">
                    <strong style="display:block;font-size:1.1rem;line-height:1;">{{ $value }}</strong>
                    <span style="font-size:.78rem;color:var(--text-muted);">{{ $label }}</span>
                </div>
            </div>
            @endforeach
        </div>
        <div style="margin-top:.75rem;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.22);border-radius:.65rem;padding:.85rem 1rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
            <div>
                <strong style="display:block;">Valor total en tarifas aprobadas/vigentes</strong>
                <span style="font-size:.82rem;color:var(--text-muted);">Suma de `precio_venta` para cotizaciones aprobadas o vigentes.</span>
            </div>
            <strong style="font-size:1.35rem;color:#047857;">{{ $money($stats['precio_vigente'] ?? 0) }}</strong>
        </div>
    </div>

    <div class="glass-card" style="margin-bottom:1.25rem;" id="proceso">
        <h3 style="margin:0 0 1rem;font-size:1.05rem;display:flex;align-items:center;gap:.5rem;">
            <i class="bi bi-diagram-3"></i> Flujo funcional del proceso
        </h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.75rem;">
            @foreach([
                ['1','Crear base','El usuario selecciona o crea cliente y centro de costo desde el cotizador.','bi-building','#2563eb'],
                ['2','Elegir modalidad','EST o SUB define qué parámetros rápidos se muestran y qué fórmula aplica.','bi-toggle2-on','#7c3aed'],
                ['3','Calcular','El sistema genera remuneraciones, descuentos, seguros, provisiones, gastos, margen y horas.','bi-calculator','#fb6b32'],
                ['4','Guardar versión','La cotización queda con número, estado, detalle, totales y datos de cálculo persistidos.','bi-save','#0891b2'],
                ['5','Aprobar o rechazar','Un perfil autorizado puede aprobar, hacer vigente, rechazar o cancelar.','bi-patch-check','#10b981'],
                ['6','Publicar valor','La cotización aprobada se usa para PDF, email y API de tarifas.','bi-cloud-check','#0f172a'],
            ] as [$num,$title,$desc,$icon,$color])
            <div style="position:relative;background:var(--surface-bg);border:1px solid var(--surface-border);border-radius:.75rem;padding:1rem;min-height:150px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.8rem;">
                    <span style="width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:{{ $color }};color:white;font-weight:700;font-size:.82rem;">{{ $num }}</span>
                    <i class="bi {{ $icon }}" style="color:{{ $color }};font-size:1.25rem;"></i>
                </div>
                <strong style="display:block;margin-bottom:.35rem;">{{ $title }}</strong>
                <span style="display:block;font-size:.82rem;line-height:1.55;color:var(--text-muted);">{{ $desc }}</span>
            </div>
            @endforeach
        </div>
    </div>

    <div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,.8fr);gap:1rem;margin-bottom:1.25rem;">
        <div class="glass-card" id="mantenedor">
            <h3 style="margin:0 0 1rem;font-size:1.05rem;display:flex;align-items:center;gap:.5rem;">
                <i class="bi bi-sliders"></i> Mantenedor y parámetros
            </h3>
            <p style="margin:0 0 1rem;font-size:.88rem;line-height:1.65;color:var(--text-muted);">
                El mantenedor permite administrar reglas de cálculo sin tocar código. Los campos muestran unidad visual: moneda, porcentaje, UF, entero, decimal u horas.
            </p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.6rem;">
                @forelse($categorias as $categoria => $total)
                <div style="background:var(--surface-bg);border-radius:.55rem;padding:.7rem .8rem;border:1px solid var(--surface-border);">
                    <strong style="display:block;font-size:.82rem;">{{ str_replace('_', ' ', (string) $categoria) }}</strong>
                    <span style="font-size:.78rem;color:var(--text-muted);">{{ $number($total) }} parámetro(s)</span>
                </div>
                @empty
                <div style="background:var(--surface-bg);border-radius:.55rem;padding:.7rem .8rem;border:1px solid var(--surface-border);">
                    <strong style="display:block;font-size:.82rem;">Sin lectura de parámetros</strong>
                    <span style="font-size:.78rem;color:var(--text-muted);">Valide migraciones y seeder comercial.</span>
                </div>
                @endforelse
            </div>
            <div style="margin-top:.9rem;border-top:1px solid var(--surface-border);padding-top:.9rem;display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
                <div>
                    <span style="display:block;font-size:.74rem;text-transform:uppercase;color:var(--text-muted);font-weight:700;">Última actualización de parámetros</span>
                    <strong>{{ $ultimaActualizacion?->updated_at?->format('d/m/Y H:i') ?? 'Sin registros' }}</strong>
                    @if($ultimaActualizacion)
                    <span style="display:block;font-size:.8rem;color:var(--text-muted);">
                        {{ $ultimaActualizacion->nombre }} · {{ $ultimaActualizacion->actualizadoPor?->name ?? 'Sistema' }}
                    </span>
                    @endif
                </div>
                @if(auth()->user()->tieneAcceso('comercial', 'puede_editar'))
                <a href="{{ route('comercial.parametros.index') }}" class="btn-primary" style="align-self:center;text-decoration:none;">
                    <i class="bi bi-sliders"></i> Abrir mantenedor
                </a>
                @endif
            </div>
        </div>

        <div class="glass-card">
            <h3 style="margin:0 0 1rem;font-size:1.05rem;display:flex;align-items:center;gap:.5rem;">
                <i class="bi bi-activity"></i> Bitácora reciente
            </h3>
            <div style="display:flex;flex-direction:column;gap:.65rem;">
                @forelse($auditorias as $audit)
                <div style="display:flex;gap:.65rem;align-items:flex-start;background:var(--surface-bg);border-radius:.55rem;padding:.7rem .8rem;">
                    <i class="bi bi-clock-history" style="color:var(--primary-color);margin-top:.12rem;"></i>
                    <div style="min-width:0;">
                        <strong style="display:block;font-size:.82rem;">{{ $audit->accion }}</strong>
                        <span style="display:block;font-size:.78rem;color:var(--text-muted);line-height:1.45;">
                            {{ $audit->cotizacion?->numero ?? 'Sin cotización' }} · {{ $audit->usuario?->name ?? 'Sistema' }} · {{ $audit->created_at?->format('d/m H:i') }}
                        </span>
                    </div>
                </div>
                @empty
                <div style="background:var(--surface-bg);border-radius:.55rem;padding:.85rem;color:var(--text-muted);font-size:.85rem;">
                    No hay eventos recientes para mostrar.
                </div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="glass-card" style="margin-bottom:1.25rem;" id="rutas">
        <h3 style="margin:0 0 1rem;font-size:1.05rem;display:flex;align-items:center;gap:.5rem;">
            <i class="bi bi-signpost-split"></i> Rutas principales
        </h3>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:.84rem;">
                <thead>
                    <tr style="background:var(--surface-bg);">
                        <th style="text-align:left;padding:.65rem .75rem;border-bottom:1px solid var(--surface-border);">Área</th>
                        <th style="text-align:left;padding:.65rem .75rem;border-bottom:1px solid var(--surface-border);">Ruta</th>
                        <th style="text-align:left;padding:.65rem .75rem;border-bottom:1px solid var(--surface-border);">Uso</th>
                        <th style="text-align:left;padding:.65rem .75rem;border-bottom:1px solid var(--surface-border);">Acceso</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach([
                        ['Cotizador','/comercial/cotizaciones','Listado y gestión de cotizaciones','modulo:comercial'],
                        ['Nueva cotización','/comercial/cotizaciones/create','Formulario de cálculo EST/SUB','puede_crear'],
                        ['Mantenedor','/comercial/mantenedor/parametros','Parámetros, uniformes y auditoría','puede_editar'],
                        ['Clientes','/comercial/clientes','Clientes comerciales simples','modulo:comercial'],
                        ['Centros','/comercial/centros-costo','Centros de costo comerciales','modulo:comercial'],
                        ['PDF','/comercial/cotizaciones/{id}/pdf','Descarga de cotización formal','modulo:comercial'],
                        ['Email','/comercial/cotizaciones/{id}/enviar-email','Envío manual con PDF adjunto','puede_editar'],
                        ['Monitor','/mail-logs','Bitácora y switches de correos','modulo:configuracion'],
                    ] as [$area,$ruta,$uso,$acceso])
                    <tr style="border-bottom:1px solid var(--surface-border);">
                        <td style="padding:.65rem .75rem;font-weight:700;">{{ $area }}</td>
                        <td style="padding:.65rem .75rem;"><code>{{ $ruta }}</code></td>
                        <td style="padding:.65rem .75rem;color:var(--text-muted);">{{ $uso }}</td>
                        <td style="padding:.65rem .75rem;"><span class="badge" style="font-size:.72rem;">{{ $acceso }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="glass-card" style="margin-bottom:1.25rem;" id="api">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
            <div>
                <h3 style="margin:0;font-size:1.05rem;display:flex;align-items:center;gap:.5rem;">
                    <i class="bi bi-cloud-arrow-down"></i> API de tarifas aprobadas
                </h3>
                <p style="margin:.35rem 0 0;font-size:.86rem;color:var(--text-muted);line-height:1.55;">
                    Diseñada para que Excel/Power Query consulte tarifas por cliente, usando token por header y sin abrir sesión web.
                </p>
            </div>
            <span class="badge {{ ($api['enabled'] ?? false) ? 'success' : 'warning' }}">
                {{ ($api['enabled'] ?? false) ? 'API activa' : 'API desactivada' }}
            </span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:.75rem;">
            @foreach([
                ['GET','/comercial/api/clientes','Catálogo de clientes activos.'],
                ['GET','/comercial/api/tarifas-cotizadas','Tarifas vigentes/aprobadas por cliente.'],
            ] as [$method,$path,$desc])
            <div style="background:var(--surface-bg);border:1px solid var(--surface-border);border-radius:.65rem;padding:.9rem;">
                <span style="display:inline-block;background:#111827;color:white;border-radius:.3rem;padding:.12rem .38rem;font-size:.7rem;font-weight:800;">{{ $method }}</span>
                <code style="display:block;margin:.55rem 0;font-size:.82rem;">{{ $path }}</code>
                <span style="font-size:.8rem;color:var(--text-muted);">{{ $desc }}</span>
            </div>
            @endforeach
        </div>
        <div style="margin-top:.9rem;background:#0f172a;color:#dbeafe;border-radius:.65rem;padding:1rem;overflow-x:auto;">
<pre style="margin:0;font-size:.78rem;line-height:1.7;"><code>let
    Source = Json.Document(
        Web.Contents(
            "https://app.saep.cl",
            [
                RelativePath = "comercial/api/tarifas-cotizadas",
                Query = [ cliente = "Cliente Ejemplo", estado = "vigente,aprobada" ],
                Headers = [ Authorization = "Bearer TU_TOKEN_API" ]
            ]
        )
    ),
    Data = Source[data],
    Tabla = Table.FromRecords(Data)
in
    Tabla</code></pre>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1rem;margin-bottom:1.25rem;">
        <div class="glass-card" id="tecnologia">
            <h3 style="margin:0 0 1rem;font-size:1.05rem;display:flex;align-items:center;gap:.5rem;">
                <i class="bi bi-cpu"></i> Tecnología y almacenamiento
            </h3>
            <div style="display:flex;flex-direction:column;gap:.55rem;">
                @foreach([
                    ['Hosting','Azure App Service Linux · PHP 8.3'],
                    ['Base de datos','Azure MySQL Flexible Server · MySQL 8.0'],
                    ['Código en servidor','/home/site/wwwroot'],
                    ['Web root','/home/site/wwwroot/public'],
                    ['Storage público','Azure Blob Storage · saep-files'],
                    ['PDF','barryvdh/laravel-dompdf'],
                    ['Excel/reportes','phpoffice/phpspreadsheet'],
                    ['Deploy','GitHub Actions → Azure Web Apps Deploy'],
                ] as [$label,$value])
                <div style="display:flex;justify-content:space-between;gap:1rem;border-bottom:1px solid var(--surface-border);padding:.45rem 0;">
                    <span style="font-size:.8rem;color:var(--text-muted);">{{ $label }}</span>
                    <strong style="font-size:.8rem;text-align:right;">{{ $value }}</strong>
                </div>
                @endforeach
            </div>
        </div>

        <div class="glass-card" id="scripts">
            <h3 style="margin:0 0 1rem;font-size:1.05rem;display:flex;align-items:center;gap:.5rem;">
                <i class="bi bi-terminal"></i> Scripts y ejecución
            </h3>
            <p style="margin:0 0 .85rem;font-size:.85rem;color:var(--text-muted);line-height:1.6;">
                El script de arranque se ejecuta al reiniciar el App Service después del deploy.
            </p>
            <div style="background:var(--surface-bg);border-radius:.65rem;padding:.85rem;display:flex;flex-direction:column;gap:.45rem;font-size:.8rem;">
                <code>startup.sh</code>
                <code>cd /home/site/wwwroot</code>
                <code>php artisan migrate --force</code>
                <code>php artisan db:seed --class=App\\Modules\\Comercial\\database\\seeders\\ComercialSeeder --force</code>
                <code>php artisan route:cache</code>
                <code>php artisan view:cache</code>
            </div>
            <div style="margin-top:.85rem;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.25);border-radius:.6rem;padding:.75rem;font-size:.82rem;line-height:1.55;color:var(--text-muted);">
                En Azure, todo dato fuera de <code>/home</code> no persiste. El código productivo se revisa desde SSH/Kudu en <code>/home/site/wwwroot</code>.
            </div>
        </div>
    </div>

    <div class="glass-card" style="margin-bottom:1.25rem;" id="azure">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
            <div>
                <h3 style="margin:0;font-size:1.05rem;display:flex;align-items:center;gap:.5rem;">
                    <i class="bi bi-cloud-fill"></i> Ubicación y revisión en Azure
                </h3>
                <p style="margin:.35rem 0 0;font-size:.86rem;color:var(--text-muted);line-height:1.55;">
                    Enlaces operativos para revisar infraestructura, despliegues, logs y archivos. Requieren sesión con permisos en Azure/GitHub; no contienen credenciales ni tokens.
                </p>
            </div>
            <span class="badge" style="font-size:.72rem;">Resource Group: saep-rg</span>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:.75rem;">
            @foreach([
                ['Portal Azure','Entrada general al portal Microsoft Azure.','https://portal.azure.com/','bi-microsoft','#0078d4','portal.azure.com'],
                ['Resource Group','Contenedor de recursos SAEP: App Service, MySQL y Storage.','https://portal.azure.com/#@/resource/subscriptions/dc9fbc10-208d-4e64-89d8-c3f176438bb2/resourceGroups/saep-rg/overview','bi-folder2-open','#0078d4','saep-rg'],
                ['App Service','Aplicación productiva Linux PHP 8.3. Revisar Overview, Restart, Deployment Center y Configuration.','https://portal.azure.com/#@/resource/subscriptions/dc9fbc10-208d-4e64-89d8-c3f176438bb2/resourceGroups/saep-rg/providers/Microsoft.Web/sites/saep-app/overview','bi-window-stack','#2563eb','saep-app'],
                ['Log stream','Revisión de logs runtime: App Service > Monitoring > Log stream.','https://portal.azure.com/#@/resource/subscriptions/dc9fbc10-208d-4e64-89d8-c3f176438bb2/resourceGroups/saep-rg/providers/Microsoft.Web/sites/saep-app/overview','bi-activity','#10b981','Monitoring > Log stream'],
                ['SSH / Kudu','Consola del contenedor para revisar /home/site/wwwroot y ejecutar comandos Artisan.','https://saep-app-gah2azercshxb0ey.scm.chilecentral-01.azurewebsites.net/webssh/host','bi-terminal-fill','#111827','/home/site/wwwroot'],
                ['Kudu Advanced Tools','Herramientas SCM del App Service: procesos, consola, archivos y diagnóstico.','https://saep-app-gah2azercshxb0ey.scm.chilecentral-01.azurewebsites.net/','bi-tools','#475569','Advanced Tools'],
                ['GitHub Actions','Workflow de build/deploy automático hacia Azure App Service.','https://github.com/brayanmachero/saep-platform/actions/workflows/main_saep-app.yml','bi-github','#111827','main_saep-app.yml'],
                ['Repositorio productivo','Código fuente productivo y commits desplegados.','https://github.com/brayanmachero/saep-platform','bi-git','#fb6b32','brayanmachero/saep-platform'],
                ['Azure MySQL','Base de datos productiva: host saep-mysql.mysql.database.azure.com.','https://portal.azure.com/#@/resource/subscriptions/dc9fbc10-208d-4e64-89d8-c3f176438bb2/resourceGroups/saep-rg/providers/Microsoft.DBforMySQL/flexibleServers/saep-mysql/overview','bi-database-fill','#0ea5e9','saep-mysql'],
                ['Azure Storage','Storage Account donde se alojan blobs públicos del sistema.','https://portal.azure.com/#@/resource/subscriptions/dc9fbc10-208d-4e64-89d8-c3f176438bb2/resourceGroups/saep-rg/providers/Microsoft.Storage/storageAccounts/saepplatformstorage/overview','bi-hdd-fill','#0078d4','saepplatformstorage'],
                ['Contenedor Blob','URL base pública del contenedor saep-files.','https://saepplatformstorage.blob.core.windows.net/saep-files','bi-box-seam','#0891b2','saep-files'],
                ['Dominio productivo','Aplicación visible para usuarios finales.','https://app.saep.cl','bi-globe2','#10b981','app.saep.cl'],
            ] as [$titulo,$desc,$url,$icon,$color,$metaAzure])
            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
               style="text-decoration:none;color:inherit;background:var(--surface-bg);border:1px solid var(--surface-border);border-radius:.7rem;padding:.9rem;display:flex;gap:.75rem;align-items:flex-start;transition:transform .15s,box-shadow .15s;"
               onmouseenter="this.style.transform='translateY(-1px)';this.style.boxShadow='0 8px 20px rgba(0,0,0,.12)'"
               onmouseleave="this.style.transform='none';this.style.boxShadow='none'">
                <div style="width:38px;height:38px;border-radius:.6rem;background:{{ $color }}18;color:{{ $color }};display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.1rem;">
                    <i class="bi {{ $icon }}"></i>
                </div>
                <div style="min-width:0;">
                    <strong style="display:flex;align-items:center;gap:.4rem;font-size:.86rem;">
                        {{ $titulo }} <i class="bi bi-box-arrow-up-right" style="font-size:.72rem;color:var(--text-muted);"></i>
                    </strong>
                    <code style="display:block;margin:.22rem 0;font-size:.72rem;color:var(--primary-color);word-break:break-word;">{{ $metaAzure }}</code>
                    <span style="display:block;font-size:.78rem;color:var(--text-muted);line-height:1.45;">{{ $desc }}</span>
                </div>
            </a>
            @endforeach
        </div>

        <div style="margin-top:.9rem;background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.22);border-radius:.65rem;padding:.8rem .95rem;font-size:.82rem;color:var(--text-muted);line-height:1.55;">
            <strong style="color:#2563eb;">Ruta operativa:</strong> Azure Portal > Resource groups > <code>saep-rg</code> > <code>saep-app</code>. Para revisar código desplegado: SSH/Kudu > <code>cd /home/site/wwwroot</code>.
        </div>
    </div>

    <div class="glass-card" style="margin-bottom:1.25rem;" id="correos">
        <h3 style="margin:0 0 1rem;font-size:1.05rem;display:flex;align-items:center;gap:.5rem;">
            <i class="bi bi-envelope-check"></i> Correos comerciales y automatizaciones
        </h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:.75rem;">
            @foreach([
                ['Template','resources/views/emails/comercial_cotizacion.blade.php','bi-filetype-html','#2563eb'],
                ['Llave','ComercialCotizacionMail','bi-key','#7c3aed'],
                ['Adjunto','PDF generado al momento del envío','bi-paperclip','#fb6b32'],
                ['Monitor','/mail-logs · enviados, fallidos y bloqueados','bi-activity','#10b981'],
            ] as [$label,$value,$icon,$color])
            <div style="background:var(--surface-bg);border:1px solid var(--surface-border);border-radius:.65rem;padding:.85rem;display:flex;gap:.7rem;align-items:flex-start;">
                <i class="bi {{ $icon }}" style="color:{{ $color }};font-size:1.2rem;"></i>
                <div>
                    <strong style="display:block;font-size:.84rem;">{{ $label }}</strong>
                    <span style="display:block;font-size:.78rem;color:var(--text-muted);line-height:1.45;">{{ $value }}</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
