# RRHH: operar un lote documental

La batería identifica documentos requeridos; no genera sus textos legales. Crear el lote en RRHH, revisar los contratos y resolver observaciones. Adjuntar PDFs autorizados con su categoría de Talana desde el detalle, o preparar una carpeta privada para importarlos en bloque.

- Generar el manifiesto: php artisan rrhh:preparar-pdfs LOTE CARPETA. Completar categoria_talana en el CSV y colocar los archivos con los nombres correspondientes.
- Importar sin contactar a Talana: repetir con --importar. Si falta una fila o un archivo, se revierte toda la importación.
- Validar: php artisan rrhh:documentos LOTE. Se requiere un contrato vigente inequívoco por RUT y datos sincronizados en las últimas 48 horas.
- Piloto autorizado: configurar TALANA_DOCUMENT_TOKEN y TALANA_DOCUMENT_WRITES en el servidor, recargar el caché de configuración y ejecutar el comando del lote primero con --ejecutar. Verificar en Talana antes de usar --firmar: este paso puede notificar a trabajadores. Sin interruptor habilitado, no hay envíos.
- Consultar con --consultar. Sólo una firma confirmada cambia a firmado. Un resultado ausente no prueba rechazo. Una respuesta ambigua o ejecución interrumpida requiere conciliación manual antes de repetir.
- Rotación: sustituir sólo el token documental, recargar la configuración y repetir una lectura antes de operar.

No hay envío programado. Para validar la integración de Reclutamiento se requiere identificar la bandeja con una solicitud conocida.
