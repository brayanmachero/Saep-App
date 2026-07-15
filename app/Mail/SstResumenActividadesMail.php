<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class SstResumenActividadesMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public Collection $items,
        public ?string $nombre = null
    ) {}

    public function envelope(): Envelope
    {
        $total = $this->items->count();
        $vencidas = $this->items->where('tipo', 'vencida')->count();
        $porVencer = $this->items->where('tipo', 'vencimiento')->count();
        $seguimientos = $this->items->whereIn('tipo', ['recordatorio', 'seguimiento_pendiente'])->count();
        $estado = match (true) {
            $vencidas > 0 => "{$vencidas} vencida(s)",
            $porVencer > 0 => "{$porVencer} por vencer",
            $seguimientos > 0 => "{$seguimientos} seguimiento(s)",
            default => 'sin pendientes',
        };

        return new Envelope(
            subject: "Resumen Carta Gantt: {$total} actividad(es) por revisar ({$estado})",
        );
    }

    public function content(): Content
    {
        $items = $this->items->values();

        return new Content(
            view: 'emails.sst_resumen_actividades',
            with: [
                'email' => $this->email,
                'nombre' => $this->nombre,
                'items' => $items,
                'total' => $items->count(),
                'conteos' => [
                    'vencida' => $items->where('tipo', 'vencida')->count(),
                    'vencimiento' => $items->where('tipo', 'vencimiento')->count(),
                    'recordatorio' => $items->where('tipo', 'recordatorio')->count(),
                    'seguimiento_pendiente' => $items->where('tipo', 'seguimiento_pendiente')->count(),
                ],
                'itemsPorPrograma' => $items->groupBy(fn ($item) => $item['programa']?->id ?? 0),
            ],
        );
    }
}
