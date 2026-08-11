<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Condiciones de servicio de las plataformas digitales de SAEP.">
    <title>Condiciones de Servicio Digitales | SAEP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --saep-navy: #230b54;
            --saep-navy-light: #352070;
            --saep-orange: #ff6b2c;
            --ink: #17233b;
            --muted: #5f6f8b;
            --surface: #ffffff;
            --canvas: #f4f7fb;
            --border: #dbe3ef;
            --notice: #fff7e7;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--canvas);
            color: var(--ink);
            font-family: Inter, Arial, sans-serif;
            font-size: 16px;
            line-height: 1.62;
        }

        a { color: var(--saep-navy); }

        .hero {
            background: var(--saep-navy);
            border-bottom: 5px solid var(--saep-orange);
            color: #fff;
            padding: 32px 24px 38px;
        }

        .hero-inner,
        .content,
        .footer-inner {
            width: min(100%, 960px);
            margin: 0 auto;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            color: #fff;
            font-weight: 700;
            text-decoration: none;
        }

        .brand img {
            display: block;
            width: 134px;
            height: auto;
            max-height: 46px;
        }

        .eyebrow {
            margin: 34px 0 6px;
            color: #f7c9b5;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        h1,
        h2,
        h3 { line-height: 1.24; }

        h1 {
            max-width: 720px;
            margin: 0;
            font-size: clamp(28px, 4vw, 42px);
            letter-spacing: 0;
        }

        .hero-description {
            max-width: 760px;
            margin: 10px 0 0;
            color: #e9e4f6;
        }

        .metadata {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 22px;
        }

        .metadata span {
            border: 1px solid rgba(255, 255, 255, .28);
            border-radius: 4px;
            padding: 5px 10px;
            color: #fff;
            font-size: 13px;
            font-weight: 600;
        }

        .content { padding: 30px 24px 42px; }

        .quick-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 18px;
        }

        .quick-nav a {
            border: 1px solid var(--border);
            border-radius: 4px;
            background: var(--surface);
            padding: 7px 11px;
            color: var(--saep-navy);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .quick-nav a:hover,
        .quick-nav a:focus-visible {
            border-color: var(--saep-navy);
            background: #f1edff;
        }

        .notice {
            display: flex;
            gap: 12px;
            margin-bottom: 18px;
            border: 1px solid #f0ca78;
            border-left: 4px solid var(--saep-orange);
            border-radius: 6px;
            background: var(--notice);
            padding: 14px 16px;
            color: #664314;
        }

        .notice i { margin-top: 3px; color: #ae5a00; }
        .notice p { margin: 0; }

        .document {
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface);
            box-shadow: 0 12px 30px rgba(31, 48, 84, .07);
            overflow: hidden;
        }

        .document-intro {
            border-bottom: 1px solid var(--border);
            background: #fbfcfe;
            padding: 24px 28px;
        }

        .document-intro p { margin: 0; color: var(--muted); }

        .document section {
            padding: 24px 28px;
        }

        .document section + section { border-top: 1px solid var(--border); }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 10px;
            color: var(--saep-navy);
            font-size: 20px;
        }

        .section-number {
            display: inline-grid;
            width: 28px;
            height: 28px;
            place-items: center;
            border-radius: 4px;
            background: var(--saep-navy);
            color: #fff;
            font-size: 13px;
        }

        p { margin: 0 0 12px; }
        p:last-child { margin-bottom: 0; }

        ul {
            margin: 10px 0 0;
            padding-left: 21px;
        }

        li + li { margin-top: 6px; }

        .inline-callout {
            margin-top: 15px;
            border-left: 3px solid #4f46e5;
            background: #f4f2ff;
            padding: 12px 14px;
            color: #312b63;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 42px;
            border: 1px solid var(--saep-navy);
            border-radius: 5px;
            padding: 9px 14px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }

        .button-primary { background: var(--saep-navy); color: #fff; }
        .button-secondary { background: #fff; color: var(--saep-navy); }
        .button:hover { filter: brightness(.96); }

        footer {
            border-top: 1px solid var(--border);
            background: #fff;
            padding: 24px;
            color: var(--muted);
            font-size: 13px;
        }

        .footer-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }

        .footer-links { display: flex; flex-wrap: wrap; gap: 14px; }
        .footer-links a { font-weight: 600; text-decoration: none; }
        .footer-links a:hover { text-decoration: underline; }

        @media (max-width: 640px) {
            .hero { padding: 24px 18px 30px; }
            .content { padding: 22px 14px 32px; }
            .document-intro,
            .document section { padding: 20px; }
            .eyebrow { margin-top: 26px; }
            .footer-inner { align-items: flex-start; flex-direction: column; }
            .button { width: 100%; }
        }
    </style>
</head>
<body>
    <header class="hero">
        <div class="hero-inner">
            <a class="brand" href="{{ url('/') }}" aria-label="Ir al sitio de SAEP">
                <img src="{{ asset('brand/wp/Logo-Saep_footer.svg') }}" alt="SAEP">
            </a>
            <p class="eyebrow">Servicios digitales SAEP</p>
            <h1>Condiciones de Servicio Digitales</h1>
            <p class="hero-description">Reglas de uso para las plataformas operacionales, de reclutamiento y de comunicación digital administradas por SAEP.</p>
            <div class="metadata">
                <span>Versión 1.0</span>
                <span>Actualizado: 11 de agosto de 2026</span>
            </div>
        </div>
    </header>

    <main class="content">
        <nav class="quick-nav" aria-label="Secciones de las condiciones">
            <a href="#alcance">Alcance</a>
            <a href="#uso">Uso autorizado</a>
            <a href="#comunicaciones">Comunicaciones</a>
            <a href="#datos">Datos personales</a>
            <a href="#responsabilidades">Responsabilidades</a>
        </nav>

        <aside class="notice" role="note">
            <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
            <p>Estas condiciones regulan el uso de los servicios digitales de SAEP. No reemplazan contratos laborales, comerciales ni el asesoramiento jurídico aplicable a cada caso.</p>
        </aside>

        <article class="document">
            <div class="document-intro">
                <p>Al acceder, operar o administrar una plataforma digital de SAEP, la persona usuaria acepta estas condiciones y se compromete a utilizar los servicios de forma responsable, segura y conforme a la normativa aplicable.</p>
            </div>

            <section id="alcance">
                <h2 class="section-title"><span class="section-number">1</span> Alcance del servicio</h2>
                <p>Estas condiciones aplican a los sistemas web, portales operacionales, automatizaciones, integraciones y canales digitales de SAEP, incluidos los módulos de reclutamiento, gestión documental, comunicaciones y las conexiones con proveedores tecnológicos autorizados.</p>
                <p>El acceso puede requerir credenciales, permisos internos, autenticación de terceros o una cuenta corporativa, según el módulo utilizado.</p>
            </section>

            <section id="uso">
                <h2 class="section-title"><span class="section-number">2</span> Uso autorizado y cuentas</h2>
                <ul>
                    <li>Las cuentas, roles y permisos se asignan según las funciones autorizadas por SAEP.</li>
                    <li>Cada persona usuaria debe resguardar sus credenciales y no compartirlas con terceros.</li>
                    <li>La información registrada debe ser veraz, pertinente y necesaria para la operación correspondiente.</li>
                    <li>SAEP puede modificar, suspender o revocar accesos cuando exista una necesidad operativa, de seguridad o de cumplimiento.</li>
                </ul>
            </section>

            <section id="comunicaciones">
                <h2 class="section-title"><span class="section-number">3</span> Comunicaciones y reclutamiento</h2>
                <p>Los canales de mensajería, incluidas las integraciones con WhatsApp Business, se utilizarán únicamente para finalidades legítimas de atención, reclutamiento, coordinación operacional, seguimiento o información relacionada con servicios de SAEP.</p>
                <div class="inline-callout">
                    <strong>Base de contacto.</strong> Quien importe contactos, cree campañas o envíe mensajes debe contar con una base habilitante válida y respetar las preferencias de contacto, las políticas del proveedor de mensajería y la normativa aplicable. No se permite el envío de mensajes no solicitados, engañosos, fraudulentos o masivos sin autorización.
                </div>
                <p>Las plantillas, campañas y respuestas automatizadas deben revisarse antes de su uso. Los mensajes pueden estar sujetos a límites de tiempo, aprobación de plantillas y otras reglas definidas por Meta, WhatsApp u otro proveedor aplicable.</p>
            </section>

            <section id="datos">
                <h2 class="section-title"><span class="section-number">4</span> Protección de datos personales</h2>
                <p>SAEP trata los datos personales que resulten necesarios para prestar, administrar, proteger y mejorar sus servicios, de acuerdo con su Política de Protección de Datos Personales y la normativa aplicable.</p>
                <p>Las personas titulares pueden ejercer los derechos que correspondan mediante la solicitud pública habilitada por SAEP. Los equipos internos deben acceder solo a los datos necesarios para sus funciones y mantener la confidencialidad de la información a la que tengan acceso.</p>
                <p><a href="{{ route('proteccion-datos.politica-privacidad') }}">Consultar la Política de Protección de Datos Personales</a> o <a href="{{ route('proteccion-datos.publico.crear') }}">ingresar una solicitud de derechos ARCO</a>.</p>
            </section>

            <section id="integraciones">
                <h2 class="section-title"><span class="section-number">5</span> Integraciones con terceros</h2>
                <p>Algunos módulos se conectan con proveedores externos, tales como Microsoft, Meta, WhatsApp, Kizeo Forms, SharePoint y servicios de correo. Esas integraciones se rigen además por las condiciones, políticas, disponibilidad y requisitos de seguridad de cada proveedor.</p>
                <p>SAEP no controla las decisiones, cambios de producto, restricciones o interrupciones imputables a dichos proveedores, aunque aplicará medidas razonables para monitorear y atender incidencias dentro de su ámbito de control.</p>
            </section>

            <section id="responsabilidades">
                <h2 class="section-title"><span class="section-number">6</span> Restricciones y responsabilidades</h2>
                <p>Está prohibido utilizar los servicios para actividades ilícitas, suplantación de identidad, fraude, envío de contenido malicioso, acceso no autorizado, extracción indebida de información o cualquier acción que afecte la seguridad, privacidad o continuidad del servicio.</p>
                <p>La persona usuaria es responsable de las acciones realizadas con su cuenta. Ante una incidencia de seguridad, acceso inusual o posible exposición de datos, debe informar oportunamente a los canales corporativos habilitados.</p>
            </section>

            <section id="disponibilidad">
                <h2 class="section-title"><span class="section-number">7</span> Disponibilidad y mantenimiento</h2>
                <p>SAEP busca mantener los servicios disponibles y con medidas de respaldo razonables. Pueden existir mantenciones, actualizaciones, medidas de seguridad o fallas de terceros que afecten temporalmente la operación. Cuando sea posible, se informarán las intervenciones que tengan impacto relevante.</p>
            </section>

            <section id="propiedad">
                <h2 class="section-title"><span class="section-number">8</span> Propiedad intelectual y confidencialidad</h2>
                <p>Los sistemas, marcas, diseños, documentación y contenidos de SAEP están protegidos por la normativa aplicable. No se autoriza su copia, distribución, ingeniería inversa o uso fuera de la finalidad permitida, salvo autorización expresa.</p>
                <p>La información operacional, de clientes, postulantes, trabajadores y proveedores debe tratarse como confidencial cuando corresponda.</p>
            </section>

            <section id="cambios">
                <h2 class="section-title"><span class="section-number">9</span> Cambios a estas condiciones</h2>
                <p>SAEP podrá actualizar estas condiciones para reflejar cambios operativos, tecnológicos, de seguridad o normativos. La versión vigente se publicará en esta página y será aplicable desde su fecha de actualización.</p>
            </section>
        </article>

        <div class="actions">
            <a class="button button-primary" href="{{ route('proteccion-datos.publico.crear') }}"><i class="bi bi-shield-check" aria-hidden="true"></i> Solicitud de derechos ARCO</a>
            <a class="button button-secondary" href="{{ route('proteccion-datos.politica-privacidad') }}"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Política de privacidad</a>
        </div>
    </main>

    <footer>
        <div class="footer-inner">
            <span>&copy; {{ now()->year }} SAEP. Servicios digitales.</span>
            <nav class="footer-links" aria-label="Enlaces legales">
                <a href="{{ route('proteccion-datos.condiciones-servicio') }}">Condiciones de servicio</a>
                <a href="{{ route('proteccion-datos.politica-privacidad') }}">Privacidad</a>
                <a href="{{ route('proteccion-datos.publico.crear') }}">Derechos ARCO</a>
            </nav>
        </div>
    </footer>
</body>
</html>
