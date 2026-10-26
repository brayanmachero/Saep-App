# Sábana de tarifas de Comercial

Ruta: `/comercial/tarifas`. Requiere acceso al módulo Comercial.

La sábana conserva referencias por cliente, centro, cargo, categoría, modalidad,
unidad, variante y moneda. Cada importe mantiene fuente, hash del archivo,
hoja/celda y desglose original. No recalcula ni sustituye las cotizaciones.

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
