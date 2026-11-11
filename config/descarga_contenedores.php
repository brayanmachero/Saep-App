<?php

/*
 * Centros y cargos que participan en Descarga de Contenedores.
 *
 * Los nombres se comparan con LIKE %texto% contra centros_costo.nombre y contra
 * los nombres de centro que trae Talana, por lo que "LTS QUILICURA" cubre también
 * "LTS QUILICURA SUB". Para sumar un centro nuevo basta con agregarlo aquí.
 */

return [

    // Interpretación con IA de programaciones en formatos no reconocidos (botón opcional
    // en Programación). Se activa solo si hay clave: OPENAI_API_KEY o GEMINI_API_KEY.
    'ia' => [
        'provider' => env('CONTENEDORES_IA_PROVIDER', 'openai'), // openai | gemini
        'model' => env('CONTENEDORES_IA_MODEL'),                 // vacío = modelo económico por defecto
        'timeout' => (int) env('CONTENEDORES_IA_TIMEOUT', 45),
        'max_chars' => 20000,
    ],

    // Centros del módulo por código oficial Talana (CODIGO-MODALIDAD). Es la forma
    // preferida: coincide exacto con centros_costo.codigo_talana (o codigo) una
    // vez ejecutado `php artisan centros:sync-talana --aplicar`.
    'centros_talana' => [
        '35-SUB',   // MAERSK LA VARA SUB
        '28-SUB',   // MAERSK BUENAVENTURA SUB
        '106-SUB',  // LTS QUILICURA SUB
        '136-SUB',  // LTS PEÑON SUB
        '23-SUB',   // LTS CAMPOS DE CHILE SUB
        '37-SUB',   // DHL GLOBAL SUB
        '9-SUB',    // LTS LO AGUIRRE SUB
        '150-EST',  // CLUB DE PERROS Y GATOS EST
    ],

    // Respaldo por nombre (LIKE %texto%) para centros aún no vinculados a Talana
    // y para nombres de centro que traen los trabajadores. Retirar cuando todos
    // los centros del módulo estén vinculados por código.
    'centros' => [
        'CAMPOS DE CHILE',
        'LTS CAMPOS DE CHILE',
        'LTS PEÑON',
        'LTS PEÑÓN',
        'LTS PENON',
        'LTS QUILICURA',
        'LTS LO AGUIRRE',
        'MAERSK PUMA',
        'MAERSK LA VARA',
        'MAERSK MATTEL',
        'MAERSK BUENAVENTURA',
        'DHL GLOBAL',
        'CLUB DE PERROS Y GATOS',
    ],

    // Centros adicionales que solo se ofrecen como centro de operación.
    'centros_operacion_extra' => [
        'DHL',
        'ECOMMERCE',
        'E-COMMERCE',
        'TRANSPORTE',
        'BRAZO',
    ],

    // Cargos que forman la dotación de descarga.
    'cargos' => [
        'DESCARGA',
        'ESTIBA',
        'DESCARGADOR',
        'OPERARIO',
        'ENCARGADO DE TURNO',
        'SUPERVISOR DE OPERACIONES',
    ],

    // Centros que deben existir en centros_costo (código Talana y modalidad).
    // SUB = empresa propia (NORMAL), EST = servicios transitorios (TRANSITORIO).
    'centros_requeridos' => [
        ['codigo' => '35-SUB', 'nombre' => 'MAERSK LA VARA SUB'],
        ['codigo' => '28-SUB', 'nombre' => 'MAERSK BUENAVENTURA SUB'],
        ['codigo' => '106-SUB', 'nombre' => 'LTS QUILICURA SUB'],
        ['codigo' => '136-SUB', 'nombre' => 'LTS PEÑON SUB'],
        ['codigo' => '23-SUB', 'nombre' => 'LTS CAMPOS DE CHILE SUB'],
        ['codigo' => '37-SUB', 'nombre' => 'DHL GLOBAL SUB'],
        ['codigo' => '9-SUB', 'nombre' => 'LTS LO AGUIRRE SUB'],
        ['codigo' => '150-EST', 'nombre' => 'CLUB DE PERROS Y GATOS EST'],
    ],

];
