<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Protección de Datos Personales — SAEP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f3f4f6;
            color: #1f2937;
            line-height: 1.7;
        }
        .policy-header {
            background: linear-gradient(135deg, #0f1b4c 0%, #1e3a8a 100%);
            color: #fff;
            padding: 3rem 2rem;
            text-align: center;
        }
        .policy-header img { height: 48px; margin-bottom: 1.5rem; }
        .policy-header h1 { font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem; }
        .policy-header p { opacity: 0.8; font-size: 0.95rem; }
        .policy-meta {
            display: flex; gap: 2rem; justify-content: center;
            margin-top: 1.5rem; flex-wrap: wrap;
        }
        .policy-meta span {
            background: rgba(255,255,255,0.15); padding: 0.4rem 1rem;
            border-radius: 20px; font-size: 0.8rem;
        }
        .policy-container {
            max-width: 860px; margin: -2rem auto 3rem; padding: 0 1.5rem;
        }
        .policy-card {
            background: #fff; border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            padding: 3rem; margin-bottom: 1.5rem;
        }
        .policy-nav {
            background: #fff; border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            padding: 1.5rem 2rem; margin-bottom: 1.5rem;
        }
        .policy-nav h3 { font-size: 0.85rem; text-transform: uppercase; color: #6b7280; margin-bottom: 1rem; letter-spacing: 0.5px; }
        .policy-nav ol { padding-left: 1.2rem; }
        .policy-nav li { margin-bottom: 0.4rem; }
        .policy-nav a { color: #0f1b4c; text-decoration: none; font-size: 0.9rem; font-weight: 500; }
        .policy-nav a:hover { color: #f97316; }
        h2 {
            font-size: 1.25rem; font-weight: 700; color: #0f1b4c;
            margin: 2rem 0 1rem; padding-bottom: 0.5rem;
            border-bottom: 2px solid #e5e7eb;
            display: flex; align-items: center; gap: 0.6rem;
        }
        h2 i { color: #f97316; font-size: 1.1rem; }
        h2:first-child { margin-top: 0; }
        h3 { font-size: 1.05rem; font-weight: 600; color: #374151; margin: 1.5rem 0 0.5rem; }
        p { margin-bottom: 1rem; font-size: 0.95rem; color: #4b5563; }
        ul, ol { padding-left: 1.5rem; margin-bottom: 1rem; }
        li { margin-bottom: 0.4rem; font-size: 0.95rem; color: #4b5563; }
        .highlight-box {
            background: #eff6ff; border-left: 4px solid #0f1b4c;
            padding: 1.2rem 1.5rem; border-radius: 0 8px 8px 0;
            margin: 1.5rem 0;
        }
        .highlight-box.warning {
            background: #fff7ed; border-left-color: #f97316;
        }
        .rights-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem; margin: 1.5rem 0;
        }
        .right-card {
            background: #f9fafb; border: 1px solid #e5e7eb;
            border-radius: 10px; padding: 1.2rem; text-align: center;
        }
        .right-card i { font-size: 1.5rem; color: #0f1b4c; margin-bottom: 0.5rem; display: block; }
        .right-card strong { display: block; color: #111827; margin-bottom: 0.3rem; font-size: 0.9rem; }
        .right-card span { font-size: 0.8rem; color: #6b7280; }
        table {
            width: 100%; border-collapse: collapse;
            margin: 1rem 0; font-size: 0.9rem;
        }
        th { background: #0f1b4c; color: #fff; padding: 0.8rem 1rem; text-align: left; font-weight: 600; }
        td { padding: 0.7rem 1rem; border-bottom: 1px solid #e5e7eb; color: #4b5563; }
        tr:nth-child(even) td { background: #f9fafb; }
        .policy-footer {
            text-align: center; padding: 2rem;
            color: #9ca3af; font-size: 0.8rem;
        }
        .btn-back {
            display: inline-flex; align-items: center; gap: 0.4rem;
            background: #0f1b4c; color: #fff; padding: 0.7rem 1.5rem;
            border-radius: 8px; text-decoration: none; font-weight: 600;
            font-size: 0.9rem; margin-top: 1rem;
            transition: background 0.2s;
        }
        .btn-back:hover { background: #1e3a8a; }
        @media (max-width: 640px) {
            .policy-card { padding: 1.5rem; }
            .policy-header h1 { font-size: 1.3rem; }
        }
    </style>
</head>
<body>

<div class="policy-header">
    <img src="https://saep.cl/wp-content/uploads/2023/11/Logo-Saep_footer.svg" alt="SAEP">
    <h1>Política de Protección de Datos Personales</h1>
    <p>Conforme a la Ley N° 21.719 y la Ley N° 19.628 reformada</p>
    <div class="policy-meta">
        <span><i class="bi bi-calendar3"></i> Versión 1.0 — Marzo 2026</span>
        <span><i class="bi bi-building"></i> SAEP SpA</span>
        <span><i class="bi bi-shield-check"></i> Ley 21.719</span>
    </div>
</div>

<div class="policy-container">

    <div class="policy-nav">
        <h3><i class="bi bi-list-ol"></i> Índice de Contenidos</h3>
        <ol>
            <li><a href="#s1">Identificación del Responsable</a></li>
            <li><a href="#s2">Objeto y Alcance</a></li>
            <li><a href="#s3">Datos Personales que Tratamos</a></li>
            <li><a href="#s4">Finalidad del Tratamiento</a></li>
            <li><a href="#s5">Base de Legitimidad</a></li>
            <li><a href="#s6">Destinatarios y Comunicación de Datos</a></li>
            <li><a href="#s7">Derechos ARCO del Titular</a></li>
            <li><a href="#s8">Medidas de Seguridad</a></li>
            <li><a href="#s9">Conservación de Datos</a></li>
            <li><a href="#s10">Transferencias Internacionales</a></li>
            <li><a href="#s11">Decisiones Automatizadas</a></li>
            <li><a href="#s12">Consentimiento</a></li>
            <li><a href="#s13">Canal de Contacto</a></li>
            <li><a href="#s14">Modificaciones</a></li>
        </ol>
    </div>

    <div class="policy-card">

        <h2 id="s1"><i class="bi bi-building"></i> 1. Identificación del Responsable de Datos</h2>
        <table>
            <tr><td><strong>Responsable</strong></td><td>SAEP SpA</td></tr>
            <tr><td><strong>Representante Legal</strong></td><td>[Nombre del Representante Legal]</td></tr>
            <tr><td><strong>Domicilio</strong></td><td>[Dirección comercial de SAEP]</td></tr>
            <tr><td><strong>Correo electrónico</strong></td><td>protecciondatos@saep.cl</td></tr>
            <tr><td><strong>Teléfono</strong></td><td>[Teléfono de contacto]</td></tr>
            <tr><td><strong>Encargado de Protección de Datos</strong></td><td>[Nombre del DPO designado]</td></tr>
        </table>

        <h2 id="s2"><i class="bi bi-file-earmark-text"></i> 2. Objeto y Alcance</h2>
        <p>La presente Política de Tratamiento de Datos Personales tiene por objeto informar a los titulares de datos personales sobre las prácticas de SAEP SpA en relación con la recolección, tratamiento, almacenamiento, comunicación y eliminación de sus datos personales, en cumplimiento de la Ley N° 19.628, reformada por la Ley N° 21.719.</p>
        <p>Esta política aplica a todos los datos personales tratados a través de la plataforma SAEP y sus módulos: gestión de formularios, seguridad y salud en el trabajo (SST), charlas, auditorías, inspecciones, accidentes laborales y Ley Karin.</p>

        <h2 id="s3"><i class="bi bi-database"></i> 3. Datos Personales que Tratamos</h2>

        <h3>3.1 Datos de identificación personal</h3>
        <ul>
            <li>Nombre completo (nombre, apellido paterno, apellido materno)</li>
            <li>RUT (Rol Único Tributario)</li>
            <li>Correo electrónico</li>
            <li>Teléfono de contacto</li>
            <li>Fecha de nacimiento</li>
            <li>Nacionalidad</li>
            <li>Sexo</li>
            <li>Estado civil</li>
        </ul>

        <h3>3.2 Datos laborales</h3>
        <ul>
            <li>Departamento, cargo y centro de costo</li>
            <li>Fecha de ingreso laboral</li>
            <li>Tipo de nómina y razón social</li>
            <li>Rol asignado en el sistema</li>
        </ul>

        <h3>3.3 Datos técnicos</h3>
        <ul>
            <li>Dirección IP de acceso</li>
            <li>Información del navegador (user agent)</li>
            <li>Fecha y hora de último acceso</li>
            <li>Identificador Azure (si aplica SSO)</li>
        </ul>

        <h3>3.4 Datos sensibles</h3>
        <div class="highlight-box warning">
            <strong>⚠ Datos Sensibles:</strong> En el módulo de Accidentes SST y Ley Karin se pueden tratar datos relativos a la salud y situaciones de acoso laboral/sexual. Estos datos reciben protección reforzada conforme al artículo 16 bis de la ley reformada.
        </div>

        <h2 id="s4"><i class="bi bi-bullseye"></i> 4. Finalidad del Tratamiento</h2>
        <p>Los datos personales son tratados para las siguientes finalidades específicas, explícitas y lícitas:</p>
        <table>
            <thead><tr><th>Finalidad</th><th>Módulo</th></tr></thead>
            <tbody>
                <tr><td>Gestión de acceso y autenticación al sistema</td><td>General</td></tr>
                <tr><td>Administración de personal y estructura organizacional</td><td>Usuarios, Departamentos</td></tr>
                <tr><td>Creación, respuesta y aprobación de formularios SST</td><td>Formularios</td></tr>
                <tr><td>Registro de charlas de seguridad y asistencia</td><td>Charlas SST</td></tr>
                <tr><td>Planificación y seguimiento de actividades preventivas</td><td>Carta Gantt SST</td></tr>
                <tr><td>Registro de inspecciones y visitas de seguridad</td><td>Visitas SST</td></tr>
                <tr><td>Registro de auditorías de seguridad</td><td>Auditorías SST</td></tr>
                <tr><td>Investigación y registro de accidentes laborales</td><td>Accidentes SST</td></tr>
                <tr><td>Gestión de denuncias de Ley Karin</td><td>Ley Karin</td></tr>
                <tr><td>Generación de reportes y documentos PDF</td><td>Exportaciones</td></tr>
                <tr><td>Firma electrónica de documentos</td><td>Firmas</td></tr>
            </tbody>
        </table>

        <h2 id="s5"><i class="bi bi-patch-check"></i> 5. Base de Legitimidad del Tratamiento</h2>
        <p>El tratamiento de datos personales se realiza bajo las siguientes bases de legitimidad conforme al artículo 13 de la ley reformada:</p>
        <ul>
            <li><strong>Consentimiento del titular:</strong> Al aceptar esta política al momento de usar la plataforma.</li>
            <li><strong>Ejecución de contrato:</strong> El tratamiento es necesario para la ejecución de la relación laboral entre el empleador y el trabajador.</li>
            <li><strong>Cumplimiento de obligación legal:</strong> Obligaciones derivadas del Código del Trabajo, Ley 16.744 sobre accidentes del trabajo, D.S. 594, y Ley 21.643 (Ley Karin).</li>
            <li><strong>Interés legítimo:</strong> Mejora de la gestión de seguridad y salud ocupacional, prevención de riesgos laborales.</li>
        </ul>

        <h2 id="s6"><i class="bi bi-share"></i> 6. Destinatarios y Comunicación de Datos</h2>
        <p>Los datos personales podrán ser comunicados a los siguientes terceros:</p>
        <ul>
            <li><strong>Organismos reguladores:</strong> Inspección del Trabajo, Seremi de Salud, Superintendencia de Seguridad Social, cuando la ley así lo exija.</li>
            <li><strong>Mutualidades:</strong> Para la gestión de accidentes laborales y enfermedades profesionales.</li>
            <li><strong>Proveedores de servicios tecnológicos:</strong> Hosting, correo electrónico y servicios cloud necesarios para la operación del sistema.</li>
        </ul>
        <p>SAEP no vende, arrienda ni comercializa datos personales a terceros.</p>

        <h2 id="s7"><i class="bi bi-person-check"></i> 7. Derechos ARCO del Titular</h2>
        <p>Conforme a los artículos 8 al 9 bis de la ley reformada, usted tiene los siguientes derechos sobre sus datos personales:</p>

        <div class="rights-grid">
            <div class="right-card">
                <i class="bi bi-eye"></i>
                <strong>Acceso</strong>
                <span>Conocer qué datos personales suyos están siendo tratados y cómo</span>
            </div>
            <div class="right-card">
                <i class="bi bi-pencil-square"></i>
                <strong>Rectificación</strong>
                <span>Solicitar la corrección de datos inexactos o incompletos</span>
            </div>
            <div class="right-card">
                <i class="bi bi-trash3"></i>
                <strong>Supresión</strong>
                <span>Solicitar la eliminación de sus datos cuando ya no sean necesarios</span>
            </div>
            <div class="right-card">
                <i class="bi bi-hand-thumbs-down"></i>
                <strong>Oposición</strong>
                <span>Oponerse al tratamiento en determinadas circunstancias</span>
            </div>
            <div class="right-card">
                <i class="bi bi-box-arrow-right"></i>
                <strong>Portabilidad</strong>
                <span>Recibir sus datos en formato electrónico estructurado</span>
            </div>
        </div>

        <div class="highlight-box">
            <strong><i class="bi bi-info-circle"></i> ¿Cómo ejercer sus derechos?</strong><br>
            Puede ejercer sus derechos ARCO directamente desde la plataforma SAEP en la sección <strong>"Protección de Datos"</strong>, o enviando una solicitud a <strong>protecciondatos@saep.cl</strong>. El plazo máximo de respuesta es de <strong>30 días hábiles</strong> desde la recepción de su solicitud.
        </div>

        <p>En caso de que su solicitud sea rechazada o no reciba respuesta oportuna, tiene derecho a recurrir ante la <strong>Agencia de Protección de Datos Personales</strong> conforme al artículo 41 de la ley reformada.</p>

        <h2 id="s8"><i class="bi bi-shield-lock"></i> 8. Medidas de Seguridad</h2>
        <p>SAEP implementa las siguientes medidas técnicas y organizativas para proteger sus datos personales:</p>
        <ul>
            <li><strong>Control de acceso:</strong> Autenticación obligatoria, gestión de roles y permisos diferenciados.</li>
            <li><strong>Cifrado:</strong> Las contraseñas se almacenan con hash bcrypt. Las comunicaciones se realizan sobre HTTPS/TLS.</li>
            <li><strong>Auditoría:</strong> Registro de todas las actividades de tratamiento de datos personales en un log de auditoría.</li>
            <li><strong>Minimización:</strong> Solo se recopilan los datos estrictamente necesarios para cada finalidad.</li>
            <li><strong>Protección de datos sensibles:</strong> Los datos de salud y Ley Karin tienen acceso restringido solo a roles autorizados.</li>
            <li><strong>Respaldos:</strong> Se realizan copias de seguridad periódicas de las bases de datos.</li>
            <li><strong>Throttling:</strong> Limitación de intentos de inicio de sesión para prevenir ataques de fuerza bruta.</li>
        </ul>

        <h2 id="s9"><i class="bi bi-clock-history"></i> 9. Conservación de Datos</h2>
        <p>Los datos personales serán conservados durante el tiempo necesario para cumplir con la finalidad para la cual fueron recopilados:</p>
        <table>
            <thead><tr><th>Tipo de dato</th><th>Período de conservación</th></tr></thead>
            <tbody>
                <tr><td>Datos de usuario activo</td><td>Durante la vigencia de la relación laboral</td></tr>
                <tr><td>Registros SST (charlas, auditorías, inspecciones)</td><td>5 años desde su creación</td></tr>
                <tr><td>Registros de accidentes laborales</td><td>10 años (obligación legal)</td></tr>
                <tr><td>Registros Ley Karin</td><td>Según normativa legal vigente</td></tr>
                <tr><td>Logs de auditoría</td><td>3 años</td></tr>
                <tr><td>Datos de usuario inactivo</td><td>3 años desde la desvinculación, luego se anonimizan</td></tr>
            </tbody>
        </table>

        <h2 id="s10"><i class="bi bi-globe"></i> 10. Transferencias Internacionales</h2>
        <p>Los datos personales son almacenados y procesados en servidores ubicados en Chile. En caso de utilizar servicios cloud con servidores en el extranjero, se verificará que el país de destino ofrezca un nivel adecuado de protección conforme al artículo 27 de la ley reformada, o se adoptarán las garantías contractuales correspondientes.</p>

        <h2 id="s11"><i class="bi bi-cpu"></i> 11. Decisiones Automatizadas</h2>
        <p>SAEP no realiza decisiones basadas únicamente en el tratamiento automatizado de datos personales, incluida la elaboración de perfiles, que produzcan efectos jurídicos en el titular o le afecten significativamente de modo similar.</p>

        <h2 id="s12"><i class="bi bi-check2-circle"></i> 12. Consentimiento</h2>
        <p>Al utilizar la plataforma SAEP, se le solicitará su consentimiento expreso para el tratamiento de sus datos personales. Este consentimiento es:</p>
        <ul>
            <li><strong>Libre:</strong> Sin condicionamiento ni presión.</li>
            <li><strong>Informado:</strong> Se le proporciona esta política antes de otorgar su consentimiento.</li>
            <li><strong>Específico:</strong> Vinculado a las finalidades aquí descritas.</li>
            <li><strong>Inequívoco:</strong> Manifestado mediante una acción afirmativa clara.</li>
        </ul>
        <p>Usted puede retirar su consentimiento en cualquier momento a través de la sección "Protección de Datos" de la plataforma, sin que ello afecte la licitud del tratamiento previo a su revocación.</p>

        <h2 id="s13"><i class="bi bi-envelope"></i> 13. Canal de Contacto</h2>
        <div class="highlight-box">
            <p style="margin: 0;">Para cualquier consulta, solicitud o reclamo relacionado con la protección de sus datos personales, puede contactarnos en:</p>
            <ul style="margin-top: 0.8rem; margin-bottom: 0;">
                <li><strong>Correo:</strong> protecciondatos@saep.cl</li>
                <li><strong>Plataforma:</strong> Sección "Protección de Datos" dentro de SAEP</li>
                <li><strong>Dirección postal:</strong> [Dirección de SAEP]</li>
            </ul>
        </div>

        <h2 id="s14"><i class="bi bi-arrow-repeat"></i> 14. Modificaciones a la Política</h2>
        <p>SAEP se reserva el derecho de modificar esta política en cualquier momento. Cualquier cambio será notificado a los titulares a través de la plataforma y requerirá la renovación del consentimiento cuando los cambios sean sustanciales.</p>
        <p>Última actualización: <strong>Marzo 2026</strong> | Versión: <strong>1.0</strong></p>

        <div style="text-align: center; margin-top: 2.5rem; padding-top: 1.5rem; border-top: 2px solid #e5e7eb;">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="btn-back">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div class="policy-footer">
        <p>&copy; {{ date('Y') }} SAEP SpA — Todos los derechos reservados</p>
        <p>Ley N° 21.719 · Protección de Datos Personales · Chile</p>
    </div>
</div>

</body>
</html>
