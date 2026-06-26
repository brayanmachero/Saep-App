<?php

return [
    'retention_matrix_version' => '2026-06-26',

    'retention_matrix' => [
        [
            'modulo' => 'Usuarios internos',
            'datos' => 'Identificación, contacto, rol, cargo, estructura organizacional y accesos.',
            'base' => 'Ejecución de relación laboral/contractual, seguridad de la plataforma y obligaciones legales.',
            'retencion' => 'Mientras exista cuenta activa y luego según obligaciones laborales, auditoría y defensa de derechos.',
            'accion_vencimiento' => 'Desactivar cuenta, revocar consentimientos vigentes y anonimizar identificadores cuando proceda.',
            'riesgo' => 'medio',
        ],
        [
            'modulo' => 'Postulación pública',
            'datos' => 'Identificación, contacto, RUT y documentos laborales subidos por el postulante.',
            'base' => 'Consentimiento del titular, gestión precontractual y obligaciones laborales si avanza el proceso.',
            'retencion' => 'Durante el proceso de selección y el plazo definido por RRHH/legal para defensa de derechos.',
            'accion_vencimiento' => 'Eliminar documentos, anonimizar postulante no seleccionado y conservar evidencia mínima de auditoría.',
            'riesgo' => 'alto',
        ],
        [
            'modulo' => 'Ley Karin',
            'datos' => 'Denunciante, denunciado, relato de hechos, medidas, investigación y antecedentes sensibles.',
            'base' => 'Obligación legal, prevención/investigación de acoso o violencia laboral y protección de derechos.',
            'retencion' => 'Según plazo legal, expediente laboral/investigativo y defensa de derechos. Requiere validación legal formal.',
            'accion_vencimiento' => 'Restringir acceso, anonimizar identificadores cuando proceda y conservar expediente exigido por ley.',
            'riesgo' => 'critico',
        ],
        [
            'modulo' => 'SST, accidentes, auditorías e inspecciones',
            'datos' => 'Salud laboral, evidencias, responsables, firmas, geolocalización si aplica y documentos de respaldo.',
            'base' => 'Obligaciones de seguridad y salud en el trabajo, cumplimiento normativo y defensa de derechos.',
            'retencion' => 'Según normativa laboral/SST y política interna validada. Requiere matriz legal definitiva.',
            'accion_vencimiento' => 'Anonimizar datos no necesarios, mantener evidencias exigibles y restringir accesos.',
            'riesgo' => 'alto',
        ],
        [
            'modulo' => 'Formularios, charlas y firmas',
            'datos' => 'Respuestas, asistencia, firmas electrónicas, IP, user agent y evidencias asociadas.',
            'base' => 'Cumplimiento contractual/laboral, trazabilidad de capacitación y consentimiento cuando corresponda.',
            'retencion' => 'Mientras sea necesario para acreditar cumplimiento y según plazo interno aprobado.',
            'accion_vencimiento' => 'Preservar evidencias probatorias de capacitación, SST, EPP, actas, accidentes y firmas mientras exista obligación de conservación; anonimizar o eliminar solo registros no probatorios o vencidos.',
            'riesgo' => 'medio',
        ],
        [
            'modulo' => 'Logs, correos y auditoría',
            'datos' => 'Eventos técnicos, errores, notificaciones, destinatarios y trazas de tratamiento.',
            'base' => 'Seguridad, trazabilidad, continuidad operacional y cumplimiento.',
            'retencion' => 'Plazo mínimo necesario para auditoría y seguridad, evitando datos sensibles en mensajes.',
            'accion_vencimiento' => 'Purgar logs antiguos, minimizar datos personales y conservar solo evidencia necesaria.',
            'riesgo' => 'medio',
        ],
    ],

    'external_processors' => [
        [
            'nombre' => 'SharePoint / Microsoft 365',
            'tipo' => 'Repositorio documental y colaboración',
            'datos' => 'Documentos laborales, evidencias, reportes, archivos adjuntos y respaldos operativos.',
            'accion_supresion' => 'Buscar por folio/RUT/email, eliminar o restringir documentos no obligatorios y registrar evidencia.',
        ],
        [
            'nombre' => 'Correo electrónico',
            'tipo' => 'Notificaciones y comunicaciones',
            'datos' => 'Destinatarios, cuerpo de correo, adjuntos y trazas de entrega.',
            'accion_supresion' => 'Minimizar futuras comunicaciones, revisar adjuntos enviados y registrar limitaciones de eliminación retroactiva.',
        ],
        [
            'nombre' => 'Kizeo',
            'tipo' => 'Formularios operacionales externos',
            'datos' => 'Registros de formularios, firmas, evidencias y datos de terreno.',
            'accion_supresion' => 'Buscar registros asociados, solicitar eliminación/anonimización al encargado y guardar comprobante.',
        ],
        [
            'nombre' => 'Google OAuth / Google Drive',
            'tipo' => 'Autenticación pública y repositorio auxiliar',
            'datos' => 'Identificadores Google, avatar, correo, nombre y archivos asociados si se usan.',
            'accion_supresion' => 'Desvincular identificadores OAuth y revisar carpetas/archivos compartidos vinculados al titular.',
        ],
        [
            'nombre' => 'Infraestructura cloud y respaldos',
            'tipo' => 'Hosting, base de datos, storage, backups y monitoreo',
            'datos' => 'Datos alojados por la aplicación, respaldos, logs técnicos y métricas.',
            'accion_supresion' => 'Aplicar retención de respaldos, documentar plazo de purga y evitar restaurar datos suprimidos sin control.',
        ],
    ],
];
