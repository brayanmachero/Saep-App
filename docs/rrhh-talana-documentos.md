# RRHH: operar un lote documental

La batería identifica documentos requeridos; no genera sus textos legales. Crear el lote en RRHH, revisar los contratos y resolver observaciones. Adjuntar PDFs autorizados con su categoría de Talana desde el detalle, o preparar una carpeta privada para importarlos en bloque.

- Generar el manifiesto: php artisan rrhh:preparar-pdfs LOTE CARPETA. Completar categoria_talana en el CSV y colocar los archivos con los nombres correspondientes.
- Importar sin contactar a Talana: repetir con --importar. Si falta una fila o un archivo, se revierte toda la importación.
- Validar: php artisan rrhh:documentos LOTE. Se requiere un contrato vigente inequívoco por RUT y datos sincronizados en las últimas 48 horas.
- Piloto autorizado: configurar TALANA_DOCUMENT_TOKEN y TALANA_DOCUMENT_WRITES en el servidor, recargar el caché de configuración y ejecutar el comando del lote primero con --ejecutar. Verificar en Talana antes de usar --firmar: este paso puede notificar a trabajadores. Sin interruptor habilitado, no hay envíos.
- Consultar con --consultar. Sólo una firma confirmada cambia a firmado. Un resultado ausente no prueba rechazo. Una respuesta ambigua o ejecución interrumpida requiere conciliación manual antes de repetir.
- Rotación: sustituir sólo el token documental, recargar la configuración y repetir una lectura antes de operar.

No hay envío programado. Para validar la integración de Reclutamiento se requiere identificar la bandeja con una solicitud conocida.

## Revisión UX del mantenedor — 16/11/2026

Problemas identificados:
- La entrada mezclaba selección de trabajadores, definiciones de baterías y lotes; faltaba un listado global de versiones guardadas.
- La guía EST mostraba coincidencias de nombre como si fueran revisión de archivos. Una coincidencia de título no verifica contenido ni vigencia.
- Búsqueda, selección, PDF y confirmación estaban dispersos; no era evidente dónde preparar y dónde validar.
- Las acciones de edición y cambio de versión no tenían una jerarquía visible.

Flujo implementado:
1. Baterías: tarjetas por versión con centro/cargo, estado, avance y fecha de actualización local; filtros por nombre/centro y estado.
2. Crear batería: definir aplicación, buscar títulos oficiales de Talana, seleccionar y guardar borrador.
3. Detalle: lista de documentos y visor PDF contiguos; ampliación en modal y apertura en pestaña alternativa. Al cargar correctamente el PDF se habilita la confirmación explícita. El servidor exige también la evidencia de carga y la revisión vigente.
4. Validar cada documento: registra revisor y fecha; al volver se selecciona el primer pendiente. Quitar afecta sólo al borrador y conserva evento de auditoría.
5. Activar: exige todos los documentos revisados. Las versiones activas se modifican mediante una nueva versión; los lotes históricos conservan su instantánea.
6. Lotes de trabajadores: sección separada. Activar una batería no genera documentos personales ni los envía a Talana.

La guía de Cristian permanece como referencia separada. El catálogo consultado devuelve documentos asociados a trabajadores: el PDF es una referencia, no una plantilla legal reutilizable. La fecha informada por Talana corresponde al registro observado en la página consultada; no certifica que sea la última revisión global.

Validación: 13 pruebas funcionales / 138 aserciones, incluyendo permisos, selección autenticada, navegación, validación sin PDF, edición, activación y conservación de versiones. QA en Edge con HTML Blade real, CSS compilado y API/PDF sintéticos: cinco vistas a 360, 768 y 1440 px, sin desbordamiento horizontal; modales, Escape, selección/retiro, error de carga, bloqueo y reintento. No se crearon baterías ni se validaron documentos reales como parte del QA.

Pendientes operativos: revisión de contenido por Cristian, primera batería real y piloto controlado de lotes. No se habilitaron envíos productivos, generación automática de textos ni alertas automáticas de nuevas versiones de Talana.
