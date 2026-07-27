<?php

namespace App\Support;

final class TalanaMarcaDirection
{
    public static function normalize(mixed $value): ?string
    {
        $direction = mb_strtoupper(trim((string) $value), 'UTF-8');

        return match ($direction) {
            'E', 'ENTRADA', 'INGRESO', 'IN' => 'E',
            'S', 'X', 'SALIDA', 'EGRESO', 'OUT' => 'S',
            '' => null,
            default => $direction,
        };
    }

    public static function label(?string $direction): string
    {
        return match ($direction) {
            'E' => 'Entrada',
            'S' => 'Salida',
            default => $direction ?: 'Sin tipo',
        };
    }
}
