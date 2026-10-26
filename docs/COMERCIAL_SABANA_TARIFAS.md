# Sábana de tarifas de Comercial

## Revisión del margen comercial (26/10/2026)

El detalle de tarifa y la ficha de cotización muestran al equipo con edición o aprobación
el costo total, precio, diferencia en dinero, porcentaje sobre costo y porcentaje sobre venta.
El porcentaje del cotizador corresponde a la base costo. Se contrasta la diferencia con el
margen guardado (tolerancia 0,02 unidades monetarias) y su porcentaje (0,01 puntos porcentuales).
La etiqueta «Cálculo consistente» indica coherencia aritmética, no aprobación de rentabilidad.
Se advierte un margen negativo; no hay un porcentaje mínimo comercial configurado.

Cotizaciones nativas: costo/subtotal y margen guardados por el cotizador. Históricos y manuales:
solo se acepta un concepto explícito de costo total, con moneda y unidad conocidas. No se suman
conceptos ni se deduce costo desde precio/margen; datos ausentes, costo cero, ambiguo o inválido
se muestran como «Margen no verificable». Los históricos desconocidos conservan sus antecedentes.
Para documentar el costo faltante se registra una nueva versión, con el campo costo total respaldado
y la explicación del documento en condiciones. No se copia automáticamente el costo a una versión nueva.

Confirmar desde sábana o activar desde cotizador exige un cálculo verificable y consistente,
revalidado dentro de la transacción antes de cambiar el estado. La auditoría conserva la fotografía
de costo/precio/margen/bases verificada en esa acción. Guardar pendientes sigue permitido.
La revisión no cambia precios ni aprueba registros existentes de forma retroactiva.
El panel interno no se agrega a la API de prefacturas.

## API de consulta y Power Query (26/10/2026)

`GET https://saep.bmachero.com/comercial/api/sabana-tarifas`

Consulta de solo lectura, protegida por la configuración dedicada `COMERCIAL_SABANA_API_ENABLED`
y `COMERCIAL_SABANA_API_TOKEN`. Enviar `Authorization: Bearer <credencial>` y `Accept: application/json`.
La credencial actual es global: permite consultar todos los clientes. Los filtros no son permisos
por cliente. Compartirla solo con personas autorizadas a esa cobertura; no incluirla en archivos
Excel que se entreguen a clientes. La clave viaja solo por cabecera y no habilita los endpoints de remuneraciones de la API anterior.

Por defecto devuelve únicamente tarifas confirmadas y vigentes según las mismas reglas de la
pantalla. Filtros opcionales exactos: `cliente`, `centro`, `cargo`, `modalidad`, `unidad`.
Sin cliente/centro devuelve todos. `vista=referencias` entrega la sábana no descartada, incluidos
pendientes e históricos: no utilizar esas filas para prefacturar sin comprobar `vigente_en_fecha`.
`fecha=AAAA-MM-DD` evalúa las fechas de vigencia con los estados actuales; no reconstruye los
estados que existían antes de una aprobación, eliminación o reemplazo.

Respuesta JSON v1: `generated_at`, `fecha_consulta`, `vista`, `pagination` y `data`.
Páginas de 500 filas por defecto, máximo 1000; continuar hasta `pagination.last_page`.
No hay truncamiento silencioso. `precio` conserva los seis decimales como texto con punto decimal;
Excel lo convierte a número usando cultura `en-US`. `null` indica dato desconocido, no cero.
Se entregan identificación de tarifa y cotización, cliente, centro, servicio, modalidad, moneda,
unidad, condiciones, fechas, revisión, estado del cotizador y fecha de actualización.
No se incluyen fórmulas, remuneraciones, márgenes, celdas ni archivos de respaldo.
Respuesta vacía en vigentes significa que no hay precios aprobados aplicables; no se reemplaza
automáticamente por una cotización pendiente. La API anterior `tarifas-cotizadas` se conserva.

### Conectar Excel de escritorio

1. Datos → Obtener datos → Consulta en blanco → Editor avanzado.
2. Crear el parámetro de texto `TokenSAEP` con la credencial entregada por el administrador.
3. Pegar [comercial-sabana-powerquery.pq](comercial-sabana-powerquery.pq). Ajustar `Cliente` y `Centro`
   o dejarlos vacíos para cargar toda la sábana vigente y filtrar después.
4. En las credenciales del origen Web elegir Anónima: la autenticación real se envía en la cabecera
   Bearer. El parámetro no cifra ni oculta la clave a quien pueda editar el libro.
5. Cerrar y cargar como tabla. Usar Datos → Actualizar todo antes de preparar la prefactura;
   opcionalmente activar actualizar al abrir el archivo en las propiedades de la conexión.

La actualización ocurre al refrescar, no como una notificación en vivo. Si falla, Excel puede
conservar datos de la actualización anterior: comprobar éxito y `consultado_en` antes de usarlo.
Cruzar por tarifa/cliente/centro/servicio/modalidad/unidad/moneda/condiciones, no solo por cargo.
Guardar una copia de los precios utilizados al cerrar una prefactura para que una actualización
posterior no modifique silenciosamente un documento ya emitido.
La paginación consulta datos vivos, no una instantánea transaccional de varias peticiones;
si Comercial cambia tarifas durante la carga, actualizar nuevamente antes de cerrar la prefactura.

Fuentes: [Web.Contents](https://learn.microsoft.com/en-us/powerquery-m/web-contents) y
[actualización de conexiones en Excel](https://support.microsoft.com/en-us/excel/refresh-an-external-data-connection-in-excel).
El script es una plantilla de conexión; su ejecución en el libro real de prefacturas debe validarse
al configurar ese archivo. No se ha modificado ningún libro de operaciones.

Ruta: `/comercial/tarifas`. Requiere acceso al módulo Comercial.

La sábana conserva referencias por cliente, centro, cargo, categoría, modalidad,
unidad, variante y moneda. Cada importe mantiene fuente, hash del archivo,
hoja/celda y desglose original. Las cotizaciones nativas tienen vínculo único y
actualizan su representación en la sábana; esta consulta no recalcula precios.

## Consulta y mantenimiento

- Último período: conserva las referencias del período más reciente de cada grupo;
  si varias fuentes coinciden en período, se muestran para conciliar. Una fecha
  desconocida no se convierte en fecha actual.
- Vigentes: solo referencias confirmadas, desde la fecha de inicio y hasta el
  término informado. Una versión posterior confirmada reemplaza la anterior del
  mismo grupo; el vencimiento de la nueva no reactiva una tarifa antigua.
- Todas las versiones: mantiene antecedentes y descartadas.
- Registrar tarifa / nueva versión: añade un registro pendiente; nunca sobreescribe
  el precio original. La revisión conserva antes, después, usuario y motivo.
- Confirmar requiere permiso comercial de edición y `puede_aprobar`, modalidad,
  unidad, cliente identificado, precio y fecha inicial. Las fechas contractuales
  no se infieren de los nombres de archivo.
- Exportación CSV respeta filtros y conserva seis decimales. Los conceptos y las
  fórmulas originales se consultan en el detalle.

## Carga de las fuentes auditadas

`ops/comercial/preparar_sabana.py AUDIT_DIRECTORY OUTPUT_JSON` lee copias de los
ZIP originales y sus inventarios sin modificar Excel. Necesita Python/openpyxl.
El lote incluye el inventario completo, tablas detalladas IDL (base y HHEE),
tarifas tabulares de Sra. Ruth y bloques reconocidos por el importador histórico.
No duplica hojas resumen ni trata escenarios intermedios como tarifas base.
Los otros formatos permanecen en la cobertura como pendientes de extracción.

```sh
php artisan comercial:importar-sabana /ruta/privada/lote-sabana.json
php artisan comercial:importar-sabana /ruta/privada/lote-sabana.json --aplicar
```

La primera llamada valida el contrato del lote sin escribir. La segunda registra
referencias pendientes dentro de una transacción. Archivo/ruta/hash/hoja/celda
identifican cada fuente y referencia: repetir un lote no duplica ni borra revisiones.
Un archivo modificado produce otra versión; no borra la anterior.
El cliente de tablas sin cliente explícito solo se homologa cuando el nombre o
código del centro coincide de forma exacta y única con un centro comercial.

No hay sincronización automática con SharePoint. Las nuevas versiones pueden
registrarse desde la pantalla o incorporarse mediante otro lote evaluado. Las
categorías comerciales y la homologación pendiente requieren revisión de negocio.
Los archivos privados de origen y lotes no deben incorporarse al repositorio.

## Validación

Pruebas en `ComercialCotizacionFlowTest`: importación idempotente, precisión,
vigencia inclusiva, sustitución sin resucitar tarifas vencidas, unidades separadas,
consulta por año, permisos, revisión auditada, nuevas versiones, render y CSV.
# Ampliación de matrices comerciales (13-10-2026)

`ops/comercial/extraer_matrices.py AUDIT_DIRECTORY` amplía el lote inicial leyendo
las matrices de remuneraciones de los archivos inventariados. Conserva precio,
conceptos, fórmulas guardadas, hoja y celda. Recorre todas las columnas de las
hojas candidatas, incluidas las posteriores a AF. No recalcula Excel.

Reconoce únicamente precio de venta adyacente a su etiqueta, sueldo base y cargo
del mismo bloque. No convierte el sueldo ni los cálculos auxiliares de HHEE en
tarifas. No hereda fechas del nombre de la hoja o del archivo: usa el encabezado
del bloque (el primer día representa un período mensual) o deja fecha pendiente.
Los errores guardados y encabezados ambiguos quedan en `cobertura-matrices.json`.
Las tablas por filas, producción, tramos y otras estructuras siguen pendientes;
una fuente con extracción se marca parcial, nunca completa por esta pasada.

El lote ampliado se valida y carga con `comercial:importar-sabana`; volver a cargar
el mismo lote no crea duplicados ni reemplaza revisiones comerciales. Las
referencias son antecedentes por archivo/celda, no un conteo de tarifas vigentes
ni de contratos distintos.

`php artisan comercial:homologar-tarifas` muestra coincidencias propuestas sin
escritura. Con `--aplicar`, identifica clientes únicamente por centro normalizado
inequívoco o por nombre completo del cliente como prefijo, usando maestros
comerciales. Conserva el centro original, precio, estado pendiente y vigencia;
registra antes/después en auditoría. Omite referencias ya revisadas y coincidencias
ambiguas. No usa similitud de nombres ni asigna categorías automáticamente.

Verificación del extractor: `python -m unittest discover -s ops/comercial -p test_extraer_matrices.py`.

## Tablas por cargo y unidades explícitas

`ops/comercial/extraer_tablas.py AUDIT_DIRECTORY` parte del lote ampliado y añade
matrices con costo final mensual/precio unitario y tablas de remuneraciones con
un cargo por fila. Distingue mes, día, hora, HHEE 50% y HHEE 100% por encabezados
explícitos. Una columna de porcentaje intermedia no se interpreta como precio;
las variantes de IPC y otros encabezados se conservan. Bloques marcados CON ERROR
o NO USAR y filas con errores guardados no se incorporan. No se heredan fechas
del archivo; las referencias sin fecha verificable permanecen sin fecha.

La homologación admite prefijos de archivo numéricos, FINAL y Copia de. La
denominación LTS se vincula solo si todos los centros LTS de los maestros tienen
el mismo cliente. Cada cambio mantiene la auditoría y el estado pendiente.
Los archivos aún sin extracción conservan el motivo específico de revisión en
las observaciones de la fuente; no deben marcarse completos por tener inventario.

## Formatos de cliente y tablas paralelas

`ops/comercial/extraer_formatos_cliente.py AUDIT_DIRECTORY` lee los formatos
auditados de La Polar (EST/SUB) y LTS Peñón/Quilicura. La Polar usa la columna
VALOR y el TOTAL A FACTURAR FINAL mensual, conservando los componentes anteriores
y las tarifas horarias explícitas. Las tablas paralelas se separan por encabezado
CARGO; cada versión conserva la celda del bloque, sin inventar una fecha común.
Si hay PRECIO DE VENTA MES, COSTO FINAL MES se conserva solo como componente.

Genera un lote delta y otro acumulado. La referencia histórica de Quilicura W7,
extraída previamente como costo, debe descartarse con auditoría al incorporar Z7,
el precio final. Nunca se borra ni se confirma automáticamente una tarifa.

## Consulta operativa (26/10/2026)

La consulta prioriza cliente, centro, servicio, precio, unidad y vigencia. Los
filtros secundarios se encuentran en Más filtros. La ficha agrupa conceptos sin
recalcular ni sumar subtotales; no infiere moneda, unidad ni conceptos ausentes.
Los precios se presentan con hasta dos decimales; el importe exacto sigue en los
datos y en la exportación CSV. El estado Vigente hoy usa las mismas reglas de
sustitución y vencimiento que la consulta de vigentes.

La revisión y los antecedentes quedan plegados para usuarios autorizados.
Las fórmulas se conservan en la base de datos y en el archivo, fuera del detalle
operativo. La descarga autenticada /comercial/tarifas/{tarifa}/archivo busca el
archivo por nombre y SHA-256 en los tres ZIP privados de la evaluación del
13/10/2026. No extrae archivos al directorio público. Si falta el respaldo o no
coincide su hash, responde 404 sin entregar otro archivo del mismo nombre.

### Filtros relacionados

Cliente limita centros y servicios; centro limita servicios dentro del cliente.
Cambiar cliente, centro o servicio envía la consulta y limpia sus filtros
subordinados, incluidos categoría/modalidad/unidad. Conserva búsqueda, vista,
año y fecha. Sin JavaScript, Consultar actualiza las opciones. El servidor
normaliza enlaces con centro o servicio incompatible y muestra un aviso.
Las opciones dependen de los antecedentes registrados, no de que exista una
tarifa aprobada en la fecha consultada: una combinación válida puede no tener
precios vigentes. No se alteran tarifas ni estados.

### Comparador y bandeja de validación

Desde la consulta se seleccionan 2–4 tarifas. La selección persiste en la pestaña
al navegar por filtros o páginas y puede limpiarse. /comercial/tarifas/comparar
valida IDs únicos y conserva el orden: la primera tarifa es la base. La diferencia
solo se calcula con precio, moneda y unidad conocidos y coincidentes; con base
cero no se calcula porcentaje. Se destacan condiciones diferentes y no se afirma
equivalencia de servicios ni aprobación de tarifas pendientes.

/comercial/tarifas/validacion requiere permiso de edición y muestra solo pendientes,
agrupados por cliente/centro, con filtros por dato ausente. Fecha de referencia y
categoría son datos de organización, no sustituyen vigencia. La revisión individual
permite registrar fecha de referencia y guardar avances incompletos como pendientes;
confirmar sigue requiriendo modalidad, unidad, precio, cliente identificado e inicio
de vigencia, además del permiso de aprobación y auditoría existente. No hay aprobación
masiva ni cambios automáticos de precios.

## Integración con cotizador (26/10/2026)

Cada cotización nativa crea una referencia con `cotizacion_id` único. Al guardar,
editar, duplicar, reajustar IPC, aprobar, reemplazar, rechazar o eliminar una
cotización, sus eventos Eloquent actualizan esa referencia. Las operaciones HTTP
de escritura conservan cotización, proyección y auditoría en la misma transacción.
No usar actualizaciones masivas por SQL para cambios comerciales: omiten eventos;
el comando de conciliación permite reparar las proyecciones si fuese necesario.

El cotizador es el lugar de edición y aprobación. La sábana muestra número y
enlace a la cotización, precio, desglose guardado y estado; bloquea cambios
independientes sobre registros vinculados. La vista «Cotizaciones del sistema»
incluye todas las versiones vinculadas y la búsqueda admite el número. La ficha
del cotizador enlaza de vuelta a la sábana. La actualización se ve al consultar
o recargar la página; no hay actualización en vivo de una pestaña abierta.

Los borradores se muestran como «En cotización», sin aprobarse. Solo estados
aprobados y fechas aplicables entran en «Vigentes»; no vigentes y eliminadas
siguen en el historial. Un borrador posterior no oculta una referencia aprobada
en la vista reciente. Las cotizaciones EST/SUB nativas calculan un precio mensual
en CLP; sus horas se derivan de esa base. No se infiere unidad para históricos.

`php artisan comercial:conciliar-cotizaciones` simula; `--aplicar` persiste y
registra auditoría. Es idempotente: no duplica referencias ni revisiones sin
cambios. Para históricos exige una única coincidencia de SHA-256, hoja y celda,
además de cliente, centro, cargo, modalidad, fecha y precio redondeado a dos
decimales. Conserva el importe original de seis decimales, desglose y respaldo;
refleja el estado del cotizador. Las fechas sintéticas del importador no se
convierten en vigencia aprobada. Origen distinto o coincidencia ambigua quedan
sin enlace ni copia automática y se advierte en la ficha de cotización.

Los históricos ya enlazados conservan sus datos originales; una aprobación
posterior explícita desde el cotizador actualiza estado y fechas. Los históricos
importados antes que su fuente de sábana requieren volver a ejecutar conciliación
después de importar esa fuente.

Validación: pruebas de creación, edición, IPC, aprobación/reemplazo, vencimiento,
inicio futuro, eliminación, permisos de edición, navegación, exportación,
idempotencia y rechazo de coincidencias históricas por origen/precio/ambigüedad.
