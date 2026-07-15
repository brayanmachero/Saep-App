<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class KanbanResumenVencimientoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $usuario,
        public Collection $items,
    ) {}

    public function envelope(): Envelope
    {
        $total = $this->items->count();
        $vencidas = $this->items->where('dias_restantes', '<=', 0)->count();
        $proximas = max(0, $total - $vencidas);

        $estado = $vencidas > 0
            ? "{$vencidas} vencida(s)"
            : "{$proximas} proxima(s)";

        return new Envelope(
            subject: "Resumen Kanban: {$total} tarea(s) por revisar ({$estado})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.kanban_resumen_vencimientos',
            with: [
                'usuario' => $this->usuario,
                'items' => $this->items,
                'tableros' => $this->items->groupBy(fn ($item) => $item['tarea']->tablero_id),
                'total' => $this->items->count(),
                'vencidas' => $this->items->where('dias_restantes', '<=', 0)->count(),
                'proximas' => $this->items->where('dias_restantes', '>', 0)->count(),
            ],
        );
    }
}
