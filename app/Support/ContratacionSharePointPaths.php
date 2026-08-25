<?php

namespace App\Support;

use App\Models\PostulanteContratacion;

final class ContratacionSharePointPaths
{
    private const HISTORIAL_DIR = '02. Historial de postulaciones';

    public static function vigente(string $root, PostulanteContratacion $postulante): string
    {
        // La ficha principal conserva la ubicación histórica de SharePoint:
        // queda directamente en la carpeta de la persona y representa la
        // última postulación recibida.
        return self::carpetaPostulante($root, $postulante).'/'.self::fichaNombre($postulante);
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
        // La carpeta física es única por RUT. Se conserva el nombre de la
        // primera postulación para que un cambio de mayúsculas, segundo
        // apellido o correo no cree otra carpeta de SharePoint.
        $nombreCarpeta = PostulanteContratacion::query()
            ->where('rut', $postulante->rut)
            ->oldest('created_at')
            ->value('nombre') ?: $postulante->nombre;

        return trim($root, '/').'/'.$postulante->rut.' - '.$nombreCarpeta;
    }

    private static function fichaNombre(PostulanteContratacion $postulante): string
    {
        $secuencia = (int) substr(strrchr($postulante->folio, '-'), 1);

        return $postulante->rut
            .' - FICHA '
            .str_pad((string) $secuencia, 3, '0', STR_PAD_LEFT)
            .' - '.$postulante->nombre.'.pdf';
    }
}
