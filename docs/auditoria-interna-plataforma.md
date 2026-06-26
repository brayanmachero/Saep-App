# Auditoria interna inicial de plataforma SAEP

Fecha de revision: 2026-06-26

> Este documento captura hallazgos tecnicos, visuales y funcionales para priorizar trabajo futuro. No reemplaza pruebas completas por rol, QA formal ni revision legal.

## Resumen ejecutivo

La plataforma tiene una base funcional amplia y coherente para una herramienta operacional: autenticacion, roles/modulos, paneles, formularios, SST, contratacion, comercial, Kanban, integraciones, logs, documentos y proteccion de datos. Lo que no cuadra del todo es el modo en que ha crecido: hay mucha funcionalidad acumulada en controladores grandes, vistas Blade muy extensas, estilos inline y JavaScript por pantalla. Eso hace que la experiencia pueda verse bien en pantallas puntuales, pero sea dificil mantener una identidad visual uniforme y flujos consistentes.

Mi lectura: no estamos frente a una app rota; estamos frente a una app que ya necesita una capa de orden interno para seguir creciendo sin que cada modulo invente su propia manera de hacer lo mismo.

## Evidencia levantada

- Rutas registradas: 326.
- Rutas con autenticacion: 296.
- Rutas publicas o sin `auth`: 30.
- Estilos inline en Blade: 6517 ocurrencias de `style="..."`.
- Scripts o manejadores embebidos en Blade: 293 ocurrencias de `<script>`, `onclick=` u `onsubmit=`.
- Pantallas internas revisadas en navegador local: dashboard, perfil, mis formularios, ARCO titular, ARCO administracion y matriz de retencion.
- Breakpoint movil revisado: 390 x 844 px en dashboard, sin overflow horizontal detectado.
- Revision posterior con `SUPER_ADMIN` completo: 36 accesos del menu lateral recorridos en navegador local, sin errores 403 ni errores de servidor detectados.

## Lo que si cuadra

- La separacion general por dominios existe: Formularios, SST, Ley Karin, Contratacion, Comercial, Kanban, Proteccion de Datos, Configuracion, Documentacion.
- El modelo de acceso por rol/modulo esta bien encaminado y aparece de forma consistente en muchas rutas.
- El modulo Comercial ya tiene una estructura mas modular bajo `app/Modules/Comercial`, lo que sirve como referencia para futuros modulos grandes.
- La navegacion movil tiene barra inferior y sidebar lateral, lo que ayuda bastante en uso operativo.
- Los flujos publicos sensibles incorporan consentimiento en Ley Karin, postulacion publica y ARCO publico.
- El login tiene marca, enlace a politica y mensaje claro para el usuario.

## Hallazgos principales

### 1. Estado de consentimiento inconsistente

Prioridad: Alta. Estado: corregido tecnicamente.

El middleware `app/Http/Middleware/VerificarConsentimientoDatos.php` permite o bloquea segun `users.acepta_politica_datos`, pero el portal ARCO en `app/Http/Controllers/ProteccionDatosController.php` muestra el estado desde `consentimientos_datos`. En la base local se vio el caso concreto: el usuario tenia `acepta_politica_datos = true`, pero no tenia consentimiento vigente en `consentimientos_datos`; por eso pudo navegar, pero la pantalla mostro "No ha aceptado la politica".

Recomendacion: definir una unica fuente de verdad. Idealmente crear un metodo en `User`, por ejemplo `consentimientoDatosVigente()`, y usarlo tanto en middleware como en vistas/exportacion. Ademas, migrar o reconstruir registros historicos cuando exista el booleano legado sin registro formal.

Correccion aplicada: `User::tieneConsentimientoDatosVigente()` y `User::consentimientoDatosVigente()` centralizan la consulta; el middleware y el portal ARCO usan ese registro vigente. Se agrego una migracion de backfill para reconstruir registros auditables cuando existia el booleano legado aceptado sin fila vigente en `consentimientos_datos`, y pruebas feature para impedir regresiones.

### 2. SUPER_ADMIN dependia de filas incompletas en `rol_modulo`

Prioridad: Alta. Estado: corregido tecnicamente.

La revision inicial se hizo con un usuario cuyo rol era `SUPER_ADMIN`, pero la tabla `rol_modulo` solo tenia asociado el modulo `proteccion_datos`. Esto provocaba que el menu visible pareciera incompleto y que el analisis inicial no cubriera todos los modulos.

Correccion aplicada: `Rol::tieneAcceso()` ahora reconoce `SUPER_ADMIN` como acceso total a modulos activos, `CheckPermission` permite permisos globales a superadmin, y se agrego una migracion de backfill para completar permisos `SUPER_ADMIN` en `rol_modulo`.

### 3. ARCO visible de forma desigual en desktop y movil

Prioridad: Alta. Estado: corregido en navegacion.

La ruta interna `/proteccion-datos` esta disponible para usuarios autenticados, pero en el sidebar desktop el acceso "Mis Derechos ARCO" depende de `tieneAcceso('proteccion_datos')`. En la barra movil, en cambio, el acceso ARCO aparece siempre.

Riesgo: un usuario desktop sin permiso de modulo podria tener derecho a usar el canal ARCO, pero no ver el acceso en navegacion principal. Para cumplimiento y experiencia, "Mis Derechos ARCO" deberia ser visible para todo usuario autenticado; solo "Gestion Solicitudes", "Registro Tratamiento" y "Matriz Retencion" deberian depender del permiso administrativo.

Correccion aplicada: "Mis Derechos ARCO" queda visible en desktop para todo usuario autenticado; las opciones administrativas siguen condicionadas a `proteccion_datos, puede_editar`.

### 4. Sistema visual fragmentado

Prioridad: Media alta.

Hay clases globales utiles (`glass-card`, `btn-premium`, `btn-secondary`, `badge`, `form-input`), pero muchas pantallas siguen resolviendo layout, colores y espaciados con estilos inline. Tambien hay pantallas publicas y auth con CSS completo propio.

Impacto:

- Cambiar marca, radios, sombras o espaciados requiere tocar muchas vistas.
- Es facil que dos modulos se vean "parecidos pero no iguales".
- El modo oscuro y responsive quedan dependientes de cada pantalla.

Recomendacion: extraer componentes Blade o parciales para page header, stats, cards, filters, tables, empty states, action buttons, badges, modals y toast/notification UI.

### 5. Vistas y controladores demasiado grandes

Prioridad: Media alta.

Controladores mas grandes detectados:

- `KizeoWebhookController.php`: 1249 lineas.
- `KanbanController.php`: 874 lineas.
- `CartaGanttController.php`: 728 lineas.
- `RespuestaController.php`: 693 lineas.
- `ContratacionController.php`: 662 lineas.
- `ContratacionPublicoController.php`: 563 lineas.

Vistas mas grandes detectadas:

- `resources/views/kizeo/dashboard.blade.php`: 1347 lineas.
- `resources/views/comercial/cotizador/create.blade.php`: 1128 lineas.
- `resources/views/stop-dashboard/index.blade.php`: 1072 lineas.
- `resources/views/formularios/show.blade.php`: 1004 lineas.
- `resources/views/ley_karin_publico/formulario.blade.php`: 769 lineas.

Recomendacion: no refactorizar todo a la vez. Empezar por los modulos con mas riesgo operacional: Kizeo, Contratacion, Formularios/Respuestas, Kanban y Carta Gantt. Extraer servicios de negocio, actions por caso de uso, request validators y parciales Blade.

### 6. Rutas grandes concentradas en `routes/web.php`

Prioridad: Media.

El archivo principal de rutas contiene muchos dominios funcionales. Comercial ya demuestra una alternativa con rutas propias por modulo.

Recomendacion: mover rutas por dominio, por ejemplo:

- `routes/modules/formularios.php`
- `routes/modules/sst.php`
- `routes/modules/ley-karin.php`
- `routes/modules/contratacion.php`
- `routes/modules/kanban.php`
- `routes/modules/proteccion-datos.php`

Esto no cambia comportamiento, pero baja ruido y ayuda a auditar permisos.

### 7. Superficie publica que requiere auditoria puntual

Prioridad: Alta para revision, no necesariamente para cambio inmediato. Estado: parcialmente corregido.

Rutas publicas relevantes:

- `/api/kizeo/webhook/{secret?}`
- `/comercial/api/clientes`
- `/comercial/api/tarifas-cotizadas`
- `/postulacion/*`
- `/denuncia-ley-karin/*`
- `/solicitud-arco/*`
- login y recuperacion de clave

La existencia de rutas publicas es normal por los portales externos, pero hay que verificar contrato de seguridad de cada una. En particular, revisar si las APIs comerciales publicas deben exigir token, firma, IP allowlist u otro mecanismo, y si el secreto opcional del webhook Kizeo debe ser obligatorio.

Correccion aplicada: el webhook Kizeo ahora falla cerrado cuando `KIZEO_WEBHOOK_REQUIRE_SECRET=true` y falta `KIZEO_WEBHOOK_SECRET`; tambien rechaza llamadas sin secreto o con secreto incorrecto. Se documento la bandera en `.env.example` y se agregaron pruebas feature para cubrir falta de secreto, secreto invalido y secreto valido. La API comercial ya estaba protegida por token y falla con `503` si no hay token configurado. Los envios publicos de Ley Karin y Postulacion quedaron con `throttle:5,1`. Las confirmaciones publicas por folio de Ley Karin y Postulacion ahora requieren URL firmada temporal, evitando acceso por folios correlativos.

### 8. Procesamiento documental duplicado y delicado

Prioridad: Media alta.

Contratacion interna y publica comparten necesidades muy parecidas: subir documentos, generar ficha PDF, consolidar, convertir, sincronizar con SharePoint, notificar y registrar errores. Hoy hay logica parecida distribuida en `ContratacionController` y `ContratacionPublicoController`, incluyendo busqueda de Ghostscript y ejecucion de comandos.

Recomendacion: extraer un servicio unico de expediente/postulante, por ejemplo `ContratacionExpedienteService`, y encapsular conversion/merge PDF en una clase separada. Tambien auditar rutas y argumentos usados con `exec`, `proc_open` y `shell_exec`.

### 9. Jerarquia visual y accesibilidad mejorables

Prioridad: Media.

En varias pantallas internas se detectaron dos `h1`: uno del layout global y otro dentro de la vista. Tambien hay botones icon-only sin `aria-label` claro en el header global, notificaciones y sidebar.

Recomendacion: el layout deberia tener un solo titulo principal por pagina, o el titulo del header global deberia ser un `div`/`span` visual cuando la vista ya tiene su propio `h1`. Los botones de icono deberian tener `title` y `aria-label`.

### 10. Dependencias externas de UI

Prioridad: Media.

El proyecto aun carga recursos desde CDN en varias pantallas: Bootstrap Icons, Google Fonts, Chart.js, SortableJS y FullCalendar. Esto puede ser aceptable, pero contradice la direccion de usar assets locales para evitar caidas externas.

Recomendacion: instalar via npm o copiar assets versionados al proyecto, compilar con Vite y eliminar CDNs en pantallas criticas.

### 11. Logs y correos con patron repetido

Prioridad: Media.

Hay un listener global para mails enviados y muchos `catch` manuales con `MailLog::recordFailed()`. El enfoque es util, pero la responsabilidad esta repartida en varios controladores.

Recomendacion: centralizar envio en un servicio o wrapper, por ejemplo `TrackedMailer`, para que los modulos no repitan manejo de errores, asunto, mailable y auditoria.

## Redundancias detectadas

- Layouts visuales publicos/auth con estilos completos propios.
- Estilos inline para cards, badges, filtros, tablas y empty states.
- Logica documental/PDF/SharePoint duplicada en contratacion publica e interna.
- Estado de consentimiento duplicado entre columna de usuario y tabla de consentimientos.
- Validacion/representacion de estados repetida en vistas y modelos.
- Registro manual de fallos de correo repetido en controladores.
- Rutas y permisos mezclados en un archivo principal grande.

## Revision navegador con SUPER_ADMIN completo

Se recorrieron los accesos principales del menu lateral luego de corregir permisos `SUPER_ADMIN`. No se detectaron errores de servidor, 403 ni bloqueos de permiso en:

- General: Panel Principal, Mi Perfil, Kanban.
- Formularios: Mis Formularios, Formularios, Categorias.
- Prevencion SST: Kizeo Analytics, Seguimiento Charlas, Tarjeta STOP CCU, Charlas, Carta Gantt, Visitas, Auditorias, Accidentes, Ley Karin.
- RRHH: Contratacion.
- Comercial: Cotizador, Clientes y CC, Mantenedor Comercial, Reportes Comerciales, Documentacion Comercial.
- Administracion: Usuarios, Departamentos, Cargos, Centros de Costo.
- Sistema: Configuracion, Permisos por Rol, Importacion, Webhooks Log, Monitor de Correos.
- Proteccion de Datos: Mis Derechos ARCO, Gestion Solicitudes, Registro Tratamiento, Matriz Retencion.
- Ayuda y herramientas: Documentacion, Notas por Voz.

Pantallas mas pesadas visualmente en esta revision:

- `stop-dashboard`: 1612 estilos inline, 31 cards y 13 tablas.
- `permisos`: 1219 estilos inline, 39 formularios y 11 tablas. Estado: primera mejora aplicada.
- `kizeo`: 358 estilos inline, 29 cards y 2 tablas.
- `documentacion/comercial`: 414 estilos inline.
- `mail-logs`: 206 estilos inline.
- `comercial/mantenedor/parametros`: 148 estilos inline y 30 cards.

### Mejora aplicada en Permisos por Rol

Se reorganizo la pantalla `permisos` para que funcione mejor como herramienta administrativa: resumen superior, chips de roles/modulos, buscador de modulos, filtro por grupo, matriz con encabezados y columna de modulo fijos, acciones rapidas por rol/fila/grupo, indicador de cambios pendientes y barra de guardado persistente. `SUPER_ADMIN` queda visualmente bloqueado como acceso total efectivo y el backend fuerza sus permisos completos al guardar la matriz.

## Recomendacion de orden de trabajo

1. Completar auditoria de rutas publicas restantes: callbacks OAuth, expiracion/vida util de enlaces firmados y endpoints comerciales con token.
2. Crear componentes UI base y continuar con `stop-dashboard`, Kizeo, ARCO y listados principales.
3. Extraer servicio documental de Contratacion.
4. Dividir rutas por dominio sin cambiar comportamiento.
5. Refactorizar los controladores gigantes por servicios/actions.
6. Mover dependencias CDN a Vite/assets locales.
7. Agregar pruebas feature para los flujos internos clave por rol.

## Nota sobre proteccion de datos

Los puntos legales pendientes quedaron documentados en `docs/proteccion-datos-auditoria.md`, seccion "Backlog futuro priorizado". Los mas importantes para futuro son: validar matriz de retencion, implementar bloqueo tecnico por modulo, propagar acciones a terceros, revisar storage sensible, ampliar pruebas y programar jobs de retencion.
