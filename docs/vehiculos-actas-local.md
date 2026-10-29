# Actas de vehículos en SAEP — revisión local 28/10/2026

## Abrir la demostración

Desde PowerShell en la raíz: `./scripts/vehiculos-preview.ps1`. Abrir http://127.0.0.1:8018/gestion-vehiculos.
Usuario `bodega.demo@example.test`, contraseña `DemoVehiculos2026!` (solo datos ficticios).
El script usa SQLite separado, `.env.vehiculos-preview` ignorado, correo en memoria y credenciales externas vacías. No modifica la base habitual. No ejecutar este entorno en una interfaz pública.

Las reservas RV-DEMO-1, RV-DEMO-2 y RV-DEMO-3 muestran respectivamente inspección inicial, consentimiento pendiente y entrega firmada lista para devolución. Fotos y firmas de ejemplo son sintéticas, sin validez.

## Flujo implementado

Bodega registra fecha, kilometraje, combustible, condiciones, equipamiento, observaciones y 1–4 fotografías privadas. Firma la inspección y la prepara. El conductor revisa y firma presencialmente con Bodega o mediante su identidad Microsoft en el portal. La firma final cambia la reserva a EN_USO; la devolución firmada la cambia a DEVUELTA. Las actas firmadas no son editables. Corregir una inspección pendiente invalida la firma previa del encargado.

El PDF incorpora condiciones, evidencias, acuerdo original del formulario 1165545, firmas y trazabilidad. El correo con PDF y actualización de calendario se controlan por banderas independientes. El modo local permite revisar el correo sin enviarlo.

## Activación y pendientes

La funcionalidad está desactivada por defecto (`VEHICLE_ACTAS_ENABLED=false`). La migración es `2026_10_28_180000_create_vehiculo_actas_table.php`. El flujo base se publicó en producción el 28/10/2026 (6da8971), seguido por la compresión de fotografías del PDF (7d73384). Los cambios del 29/10 descritos abajo permanecen locales; no confundirlos con la versión publicada.

`VEHICLE_ACTAS_MAIL_ENABLED` y `VEHICLE_ACTAS_CALENDAR_ENABLED` permanecen desactivados en la demo. Su activación real requiere revisar destinatarios, correo, trabajador de colas y credenciales. Las pruebas usan transportes simulados. Un envío de resultado incierto requiere revisar el proveedor antes de reintentar.

Kizeo: se genera un paquete interno de datos y medios para el formulario 1165545; NO se presenta como contrato de escritura confirmado ni se envía. La documentación pública revisada expone push de fichas, pero no confirmó el cierre del formulario con carga externa de fotos/firmas. Pendiente validar con Kizeo el endpoint autorizado, campos de listas avanzadas, geolocalización y prueba integral. Estado mostrado: pendiente de validar API.

Pendientes de revisión: experiencia en teléfono físico de Bodega/conductor, sincronización Kizeo y recepción efectiva del correo. El croquis y ubicación en SAEP están implementados localmente desde el 29/10; su publicación y la integración externa con Kizeo siguen pendientes.

## Validaciones

`php vendor/phpunit/phpunit/phpunit --filter='VehiculoActaTest|ReservaVehiculoTest|ReservaVehiculoTeamsServiceTest'`

48 pruebas, 273 aserciones: ciclo entrega/devolución, firmas, permisos, revisiones obsoletas, kilometraje, custodia activa, protección frente a cambios heredados y correo sin duplicar. PDF de ejemplo de tres páginas revisado visualmente. Evidencias de pruebas en `storage/logs/vehiculos-tests-output.txt` y `storage/logs/vehiculos-tests.xml`.

## Ajuste móvil 28/10/2026

Nombre y correo se precargan y se resuelven en servidor desde la reserva (en devolución, desde la entrega firmada). No pueden reemplazarse mediante el formulario del celular. Se muestra al responsable el destinatario del respaldo. La aceptación y la firma siguen siendo explícitas; nombre precargado no significa consentimiento.

Controles táctiles, navegación por secciones, firma ampliable y cámara con fotos acumulables/eliminables hasta cuatro. Kilometraje, combustible y estado físico requieren inspección, no se presuponen. Comprobados 49 tests / 278 aserciones y viewport de 390 px sin desborde. Pendiente prueba de cámara y tacto en dispositivos Android/iPhone reales. El servidor de revisión permanece limitado a 127.0.0.1: no es accesible directamente desde otro celular; no se abrió acceso de red.

## Continuación 29/10/2026 — pendiente de publicar

Implementados en local: captura voluntaria de ubicación del dispositivo (latitud, longitud, precisión y hora), motivo explícito si no está disponible, croquis opcional privado y presentación de ambos en revisión/PDF. Forman parte del contenido firmado; no se agregan retrospectivamente a las actas anteriores. Se preserva transparencia PNG en nuevas evidencias. 53 pruebas / 304 aserciones.

### Propuesta seleccionada: referencia Kizeo → SAEP

La documentación oficial https://kizeo.github.io/kizeo-forms-documentations/docs/data/ describe POST /forms/{formId}/push como precarga sin guardar el formulario final. No se identificó contrato público para finalizar con medios externos. No se enviaron datos.

Propuesta para 1165545: código de reserva, gestión, fecha/kilometraje y observaciones_adicionales con resumen, UUID y enlace al acta firmada en SAEP. Los enlaces requieren inicio de sesión y permisos de Bodega; fotos, firmas y PDF permanecen en almacenamiento privado SAEP. El paquete de integración ahora incluye propuesta_referencia para inspección, sin ejecutar solicitudes.

Antes de implementar el envío: elegir destinatario Kizeo, resolver valores de listas avanzadas, probar reglas de obligatoriedad y decidir adaptación o copia del formulario (sus fotos/firmas obligatorias pueden impedir cierre usando solo referencias). Registrar por separado ID/estado de precarga y envío idempotente. Una precarga no se presentará como formulario Kizeo finalizado. No se modifica el formulario existente sin concretar esa elección.

Correo RV-2026-000212: sigue sin reenviar por indicación expresa del usuario. La recepción real y prueba de devolución desde un teléfono siguen pendientes; no se simulan como realizadas.

### Croquis guiado de carrocería — 29/10/2026 (local)
Se reemplazó el lienzo vacío por una guía esquemática con cinco vistas: ambos lados, frente, parte trasera y superior. Un toque crea un círculo rojo; arrastrar al crear ajusta el radio. Permite deshacer y limpiar por vista. Las cinco vistas se exportan juntas en PNG 1500x600; sin marcas nuevas se conserva el croquis anterior. Los cambios invalidan la firma del encargado para exigir revisión. Guía genérica, no a escala; complementa fotos y observaciones. Pendiente de publicación y prueba en teléfono físico.

### Validación de continuidad — 29/10/2026
54 pruebas / 318 aserciones correctas en la suite de actas, reservas y calendario. Incluye guardado del PNG 1500x600, generación del PDF, acceso privado, conservación del croquis al editar sin marcas nuevas, eliminación explícita, rechazo de revisiones obsoletas e imposibilidad de modificar el croquis firmado. La prueba de PDF verifica generación; la revisión visual del PDF final con las cinco vistas sigue pendiente. No se enviaron correos ni se publicó esta continuación.

Revisión visual completada: borrador RV-DEMO-1 guardado desde navegador con croquis de dos marcas; exportación de cinco vistas comprobada. PDF de demostración generado sin escribir cambios sobre registros firmados, revisado visualmente y ajustado para agrupar croquis y fotos sin página innecesaria. Evidencia local: storage/logs/acta-croquis-final-2.png; PDF sin validez: storage/logs/acta-croquis-demo.pdf. Prueba de PDF repetida después del ajuste: correcta. No se publicó.
