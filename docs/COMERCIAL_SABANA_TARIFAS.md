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

