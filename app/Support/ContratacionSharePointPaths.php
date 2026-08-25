<?php

namespace App\Support;

use App\Models\PostulanteContratacion;

final class ContratacionSharePointPaths
{
    private const VIGENTE_DIR = '01. Documentacion vigente';
    private const HISTORIAL_DIR = '02. Historial de postulaciones';

    public static function vigente(string $root, PostulanteContratacion $postulante): string
    {
        return self::carpetaPostulante($root, $postulante).'/'.self::VIGENTE_DIR.'/Ficha vigente.pdf';
    }

    public static function historial(string $root, PostulanteContratacion $postulante): string
    {
        $fecha = ($postulante->created_at ?? now())->format('Y-m-d');

        return self::carpetaPostulante($root, $postulante)
            .'/'.self::HISTORIAL_DIR
            .'/'.$fecha.' - '.$postulante->folio
            .'/Ficha '.$postulante->folio.'.pdf';
    }

    private static function carpetaPostulante(string $root, PostulanteContratacion $postulante): string
    {
        return trim($root, '/').'/'.$postulante->rut.' - '.$postulante->nombre;
    }
}
