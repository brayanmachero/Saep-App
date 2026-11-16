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

### Revisión guiada y reemplazo directo — 16/11/2026

El detalle separa requisitos, vínculos y validaciones. Los filtros Todos/Sin vincular/Por revisar/Validados conservan la posición real del requisito, muestran vacío cuando corresponde y permiten Anterior/Siguiente dentro del filtro. La validación vuelve al mismo filtro.

Vincular/reemplazar abre un selector de un archivo con vista previa autenticada antes de confirmar. El reemplazo es transaccional: conserva la posición y referencia original, impide duplicar otro documento de la batería, exige revisión vigente, elimina la aprobación del archivo anterior y audita nombre/ID anterior y nuevo. El candidato se consulta sin modificar la batería ni habilitar la validación del archivo guardado. Las versiones activas siguen protegidas.

Validado con 14 pruebas y 182 aserciones, incluyendo filtros, vacío, vinculación, reemplazo, duplicados, token inválido, revisión concurrente, versión activa y PDF candidato. QA Edge local con Blade/CSS reales y datos sintéticos a 360/768/1440: modal de reemplazo, candidato PDF, selección, cierre y estados pendientes; sin desborde horizontal ni errores JS. No se sustituyeron documentos productivos durante las pruebas.

### Observaciones, comparación y cierre guiado — 16/11/2026

Un documento puede marcarse como Requiere corrección con motivo, responsable y fecha. Se retira su validación previa y se bloquea validar/activar mientras la observación siga abierta. Registrar la resolución conserva el historial y devuelve el documento a revisión; no lo aprueba automáticamente. Las acciones requieren edición, borrador y revisión vigente.

La pantalla Revisión final y comparación de versiones reúne aplicación de la batería, estados excluyentes, pendientes con enlaces directos y activación condicionada. Compara solamente versiones de la misma familia: agregados, retirados, reemplazos/vinculaciones, cambios de posición y reglas. Usa referencia de origen, nombre e ID Talana; no compara el texto de PDF ni certifica versiones externas. Al activar se mantienen los snapshots previos y se archiva la versión activa anterior.

Validación: 16 pruebas/232 aserciones; observaciones, resolución, invalidación de aprobación, activación bloqueada, permisos, concurrencia, comparación de IDs/títulos/reglas/orden y rechazo de otra familia. QA Edge con datos sintéticos en 360/768/1440 px para observado, cierre bloqueado, listo y comparación: sin desborde horizontal ni errores JS. No se crearon observaciones reales ni se activaron baterías productivas para probar.
