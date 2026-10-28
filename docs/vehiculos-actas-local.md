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

La funcionalidad está desactivada por defecto (`VEHICLE_ACTAS_ENABLED=false`). La migración nueva es `2026_10_28_180000_create_vehiculo_actas_table.php`. La demostración ya la aplica únicamente a su SQLite. No se ha migrado ni publicado en producción.

`VEHICLE_ACTAS_MAIL_ENABLED` y `VEHICLE_ACTAS_CALENDAR_ENABLED` permanecen desactivados en la demo. Su activación real requiere revisar destinatarios, correo, trabajador de colas y credenciales. Las pruebas usan transportes simulados. Un envío de resultado incierto requiere revisar el proveedor antes de reintentar.

Kizeo: se genera un paquete interno de datos y medios para el formulario 1165545; NO se presenta como contrato de escritura confirmado ni se envía. La documentación pública revisada expone push de fichas, pero no confirmó el cierre del formulario con carga externa de fotos/firmas. Pendiente validar con Kizeo el endpoint autorizado, campos de listas avanzadas, geolocalización y prueba integral. Estado mostrado: pendiente de validar API.

Pendientes de revisión: experiencia de Bodega/conductor, acuerdo y modalidad de identificación aceptados por la organización, sincronización Kizeo, correo/calendario reales y despliegue posterior. El dibujo de daños y geolocalización de Kizeo no están implementados en este prototipo.

## Validaciones

`php vendor/phpunit/phpunit/phpunit --filter='VehiculoActaTest|ReservaVehiculoTest|ReservaVehiculoTeamsServiceTest'`

48 pruebas, 273 aserciones: ciclo entrega/devolución, firmas, permisos, revisiones obsoletas, kilometraje, custodia activa, protección frente a cambios heredados y correo sin duplicar. PDF de ejemplo de tres páginas revisado visualmente. Evidencias de pruebas en `storage/logs/vehiculos-tests-output.txt` y `storage/logs/vehiculos-tests.xml`.

## Ajuste móvil 28/10/2026

Nombre y correo se precargan y se resuelven en servidor desde la reserva (en devolución, desde la entrega firmada). No pueden reemplazarse mediante el formulario del celular. Se muestra al responsable el destinatario del respaldo. La aceptación y la firma siguen siendo explícitas; nombre precargado no significa consentimiento.

Controles táctiles, navegación por secciones, firma ampliable y cámara con fotos acumulables/eliminables hasta cuatro. Kilometraje, combustible y estado físico requieren inspección, no se presuponen. Comprobados 49 tests / 278 aserciones y viewport de 390 px sin desborde. Pendiente prueba de cámara y tacto en dispositivos Android/iPhone reales. El servidor de revisión permanece limitado a 127.0.0.1: no es accesible directamente desde otro celular; no se abrió acceso de red.
