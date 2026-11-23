# Composición de baterías documentales

Implementación: 19/11/2026. Área: RRHH / Gestión Documental.

## Uso

1. Crear la base común, delimitar su empresa/tipo de contrato y agregar los documentos obligatorios. Revisar los PDF y validar su contenido en esa batería.
2. Crear una batería específica para un centro o cargo. En «Heredar documentos» seleccionar la versión de la base y, si corresponde, otra batería específica compatible. Agregar solamente sus requisitos adicionales.
3. Revisar «Así se compone esta batería»: total único, origen de cada documento, alcance combinado y pendientes. Los heredados se abren y mantienen en su origen, no se quitan desde el hijo.
4. Activar primero las bases/orígenes y después la específica. Es posible preparar borradores dependientes antes de activar sus bases.
5. En Lotes, filtrar los contratos y elegir la batería compuesta. El lote conserva nombres, metadatos, versiones, orígenes y alcance resultantes. Se preparan después los PDF individuales de cada trabajador.

Ejemplo: Base EST + documentos de CCU + documentos de Operario = batería final para operarios de CCU en EST. Las condiciones se combinan con AND; una batería para CCU no se puede combinar con otra que exige un centro diferente. Un registro de Talana repetido se cuenta una vez.

## Versiones y validaciones

- Cada relación apunta a una versión concreta, no a «la última» dinámicamente.
- Al activar una nueva base, la antigua se archiva. Las baterías que la utilizaban muestran un pendiente y no pueden preparar nuevos lotes hasta revisar su composición con la versión vigente. Los lotes guardados permanecen intactos.
- Crear nueva versión conserva las relaciones, pero requiere revisar sus documentos propios. Cambiar la composición elimina la validación propia previa; nunca altera las validaciones del origen.
- Se rechazan ciclos, más de diez niveles, dos versiones de una misma familia, conflictos de alcance y referencias distintas con idéntico título. Máximo: 50 documentos únicos por composición.
- Una específica debe incluir una base común y delimitar centro, cargo o tipo de contrato antes de activarse. Una base puede aplicarse a todos en su empresa; no supone universalidad EST/SUB.

## Límites explícitos

- Se usa la batería elegida para el lote y sus relaciones. No hay búsqueda automática de todas las baterías por empleado ni selección diferente para cada trabajador dentro de un mismo lote. Dividir por alcance cuando corresponda.
- El cliente se representa actualmente por las baterías y centros de costo; no existe un nuevo maestro independiente de clientes en este cambio.
- Los PDF de referencia de Talana no son plantillas para generar documentos personales ni se copian automáticamente a los trabajadores.
- No se habilitan escrituras/envíos a Talana. La revisión humana, los archivos personales y el piloto controlado siguen pendientes.
- Las baterías anteriores se conservan como independientes hasta clasificarlas expresamente. No se transforman o aprueban automáticamente durante la migración.

## Verificación

Pruebas de composición, deduplicación, alcances, ciclos, herencia sin adicionales, bloqueo de padres no activos, control de revisión, clonación, protección de documentos heredados y conservación de snapshots, junto con las regresiones de preparación/envío existentes. QA de vistas Blade con datos sintéticos a 360, 768 y 1440 px; sin escrituras en Talana.


## Empresa en Talana (23/11/2026)

La búsqueda requiere elegir empresa y envía `empresa` al listado de documentos. El token de selección firmado y los metadatos conservan `empresa_id`; las vistas previas consultan el detalle con esa empresa. Se rechaza agregar una selección de otra empresa. Cambiar la empresa en creación limpia selección, resultados y paginación, descartando respuestas en vuelo anteriores.

Las referencias anteriores a este cambio proceden de la consulta sin filtro (base SAEP 1039). Se preservan y se abren desde esa base; no se convierten ni renombran como EST. Si la composición requiere otra empresa, se avisa y se bloquea su validación/activación hasta reemplazarlas. Las nuevas selecciones deben revisarse de nuevo. Este cambio no reemplaza archivos automáticamente ni habilita envíos a trabajadores.

Validación: 22 pruebas / 308 aserciones; QA del selector a 360 y 1440 px con resultados sintéticos, sin desbordamiento ni errores JS, empresa obligatoria, paginación y limpieza al cambiar. Consulta real de listado y detalle EST con empresa1081 confirmada; no se crean documentos productivos en QA.
