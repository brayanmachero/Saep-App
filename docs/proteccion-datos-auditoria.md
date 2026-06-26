# Auditoria de proteccion de datos personales - SAEP

Fecha de revision: 2026-06-25

> Nota: este documento es una auditoria tecnica y funcional del proyecto. Debe ser validado por asesoria legal antes de usarlo como politica corporativa definitiva.

## Marco usado

- Chile: Ley 19.628 vigente sobre proteccion de la vida privada/proteccion de datos personales.
- Chile: Ley 21.719, publicada y con vigencia general diferida al 1 de diciembre de 2026, que reforma la Ley 19.628 y crea la Agencia de Proteccion de Datos Personales.
- Criterios tecnicos aplicados: licitud, finalidad, proporcionalidad/minimizacion, calidad, responsabilidad, seguridad, transparencia, confidencialidad, derechos del titular y evidencia auditable.

## Superficies con datos personales

### Usuarios internos

- Tabla principal: `users`.
- Datos: nombre, apellidos, email, RUT, telefono, fecha de nacimiento, nacionalidad, sexo, estado civil, fecha de ingreso, cargo, departamento, centro de costo, rol, identificadores Azure/Talana.
- Controles existentes: autenticacion, roles/modulos, consentimiento obligatorio antes de entrar al sistema, portal ARCO, exportacion JSON, auditoria de tratamiento.
- Mejora implementada: version unica de politica (`PrivacyPolicy::VERSION`) y ejecucion de supresion autorizada desde solicitud ARCO aprobada.

### Formularios y respuestas

- Tablas: `formularios`, `respuestas`, `aprobaciones`, adjuntos en storage publico.
- Datos: contenido dinamico en `datos_json`, archivos adjuntos, solicitante, aprobadores, estado, PDF/correos.
- Riesgo principal: `datos_json` puede contener datos personales o sensibles no tipados por columna.
- Mejora implementada: el flujo de supresion anonimiza respuestas asociadas al usuario cuando corresponde, elimina archivos detectados en `datos_json`, preserva evidencias legales/SST detectadas y deja advertencia de revision manual para texto libre.

### Firmas electronicas

- Tabla: `firmas_electronicas`.
- Datos: nombre, RUT, email, cargo, firma base64, hash, IP, user agent, geolocalizacion.
- Riesgo principal: firma e identificadores pueden ser datos sensibles/biometricos segun el contexto.
- Mejora implementada: supresion autorizada anonimiza identidad, limpia firma e identificadores tecnicos asociados al titular.

### Ley Karin

- Tablas: `ley_karin`, `ley_karin_logs`, `archivos_adjuntos`.
- Datos: denunciante, denunciado, email, RUT, hechos, evidencias, ubicacion opcional, estado de investigacion.
- Riesgo principal: datos sensibles, confidencialidad, texto libre con nombres y relatos.
- Controles existentes: acceso por modulo, confidencialidad, formulario publico con Google, consentimiento obligatorio, adjuntos privados.
- Mejora implementada: metadatos de consentimiento publico (version, texto, fecha, IP, user agent) y registro de tratamiento sensible.
- Pendiente: matriz legal de retencion especifica y revision manual de relatos/evidencias cuando proceda supresion.

### Portal publico de contratacion

- Tabla: `postulantes_contratacion`.
- Datos: nombre, RUT, email, Google ID/nombre/avatar, carnet, AFP, FONASA, licencia, estado, observaciones.
- Riesgo principal: antes no habia aceptacion expresa trazable en el formulario publico.
- Mejora implementada: consentimiento obligatorio antes de enviar/actualizar, version/texto/IP/user agent/fecha, registro de tratamiento y soft deletes para conservar trazabilidad minima tras anonimizacion.
- Pendiente: procedimiento para solicitar ARCO sin cuenta SAEP usando folio/email, y verificacion de borrado en SharePoint/correos externos.

### Kizeo, Google Drive, OneDrive/SharePoint, correo

- Servicios: `KizeoService`, `GoogleDriveService`, `OneDriveService`, envios `Mail`.
- Riesgo principal: copias externas fuera de la base de datos local.
- Mejora implementada: el resultado de supresion advierte explicitamente que se deben revisar copias externas.
- Pendiente critico: inventario de encargados de tratamiento, contratos/mandatos, ubicacion de datos, retencion y procedimiento de propagacion de rectificacion/supresion.

## Flujo ARCO actual

1. Usuario autenticado crea solicitud: acceso, rectificacion, supresion, oposicion o portabilidad.
2. Se registra numero, fecha y vencimiento a 30 dias corridos.
3. Administrador con modulo `proteccion_datos` puede revisar y responder.
4. Para supresion:
   - no se puede cerrar como completada manualmente;
   - debe quedar aprobada;
   - luego se ejecuta el boton "Ejecutar supresion";
   - el sistema anonimiza/elimina lo controlado por la app y guarda resultado en auditoria.

## Brechas pendientes

- Completar politica de privacidad con representante legal, domicilio, telefono y encargado/delegado real.
- Validar legalmente la matriz formal de retencion por modulo y causal legal, especialmente Ley Karin, accidentes, SST y contratacion.
- Ejecutar propagacion real a terceros/encargados: SharePoint, correo, Kizeo, Google Drive, proveedores cloud.
- Convertir el bloqueo temporal operativo en bloqueo tecnico por modulo cuando cada proceso lo soporte.
- Revisar cifrado o proteccion adicional de archivos publicos en `storage/app/public` que contienen documentos de identidad/laborales.
- Agregar pruebas automatizadas del flujo ARCO y del consentimiento publico.
- Revisar logs de correo y errores para evitar exposicion innecesaria de datos personales.

## Proxima fase recomendada

1. Validar plazos reales de la matriz de retencion con responsable legal/privacidad.
2. Implementar job de vencimiento/anonimizacion programada por modulo.
3. Integrar acciones reales de propagacion con SharePoint, Kizeo, Google Drive y correo.
4. Pruebas Feature para consentimiento publico y ejecucion de supresion.

## Backlog futuro priorizado

- Completar datos reales de la politica: representante legal, domicilio, telefono, correo operativo y encargado/delegado de proteccion de datos.
- Validar legalmente plazos de retencion por modulo: usuarios, contratacion, Ley Karin, SST, accidentes, formularios, firmas, correos, logs y respaldos.
- Implementar bloqueo tecnico por modulo: impedir nuevas comunicaciones, exportaciones, uso operativo o modificaciones no obligatorias cuando una solicitud tenga bloqueo activo.
- Automatizar propagacion a terceros/encargados: SharePoint, Kizeo, Google Drive, correo, backups y proveedores cloud, dejando evidencia de instruccion/respuesta.
- Revisar storage sensible: mover documentos personales o probatorios a storage privado, con descarga autenticada, controles de permiso y expiracion si aplica.
- Ampliar pruebas automatizadas: supresion interna, preservacion de evidencias legales/SST, administracion ARCO, bloqueo y auditoria de tratamiento.
- Definir jobs programados de retencion: anonimizar, purgar o bloquear registros vencidos cuando la matriz legal este validada.

## Fase 2 implementada

- Canal publico `/solicitud-arco` para titulares sin cuenta interna, con folio y token privado de seguimiento.
- Registro de consentimiento especifico para solicitudes ARCO publicas.
- Soporte de derecho de bloqueo como tipo de solicitud y bandera operativa de bloqueo temporal.
- Administracion interna con filtro por canal y visibilidad de titulares externos.
- Supresion autorizada para solicitudes publicas con busqueda automatizada por email/RUT en postulaciones y Ley Karin.
- Matriz de retencion y catalogo de encargados externos en `config/proteccion_datos.php`, visible desde `/proteccion-datos/matriz-retencion`.

## Alcance del canal ARCO

- La plataforma ejecuta acciones tecnicas sobre los datos que administra directamente en su base de datos y storage configurado.
- Si una solicitud del titular apunta a datos tratados por SAEP en otros sistemas internos, repositorios documentales, correos, respaldos o proveedores tecnologicos, la solicitud debe evaluarse como requerimiento recibido por SAEP y derivarse a las areas o encargados correspondientes.
- La existencia del canal en esta plataforma no implica supresion automatica de todos los datos de la empresa. La respuesta debe considerar base legal, finalidad, obligacion de conservacion, expedientes laborales, SST, Ley Karin, contabilidad, defensa de derechos y demas excepciones aplicables.
- Cuando corresponda propagacion a terceros, debe quedar evidencia de la instruccion, respuesta o limitacion tecnica/legal del encargado externo.

## Evidencias legales y documentos firmados

- Documentos de capacitaciones, charlas, ODI, prevencion de riesgos, EPP, accidentes, actas, entregas de equipos/vehiculos y firmas electronicas pueden contener datos personales, pero tambien cumplen una finalidad probatoria o legal.
- Ante una solicitud de supresion, estos documentos no deben eliminarse automaticamente si son necesarios para acreditar cumplimiento laboral, SST, investigacion, fiscalizacion, obligacion contractual o defensa de derechos.
- La respuesta adecuada puede ser bloqueo/restriccion de uso no obligatorio, control de acceso, minimizacion en reportes y eliminacion de copias duplicadas o vencidas.
- El servicio de supresion preserva automaticamente registros que detecta como evidencia legal/SST por categoria, entidad, proposito o nombre asociado, y deja el conteo en el resultado tecnico de ejecucion.
