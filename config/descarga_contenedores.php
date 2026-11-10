<?php

/*
 * Centros y cargos que participan en Descarga de Contenedores.
 *
 * Los nombres se comparan con LIKE %texto% contra centros_costo.nombre y contra
 * los nombres de centro que trae Talana, por lo que "LTS QUILICURA" cubre también
 * "LTS QUILICURA SUB". Para sumar un centro nuevo basta con agregarlo aquí.
 */

return [

    // Centros cuya dotación aparece en los desplegables de personal y en el
    // listado de centros del módulo.
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
