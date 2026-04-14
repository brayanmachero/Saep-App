<?php

namespace App\Mail;

use App\Models\KanbanTarea;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class KanbanVencimientoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public KanbanTarea $tarea,
        public int $diasRestantes,
    ) {}

    public function envelope(): Envelope
    {
        $emoji = $this->diasRestantes <= 0 ? '🔴' : '⚠️';
        return new Envelope(
            subject: "{$emoji} Tarea próxima a vencer — {$this->tarea->titulo}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.kanban_vencimiento',
            with: [
                'tarea'          => $this->tarea,
                'diasRestantes'  => $this->diasRestantes,
                'tableroNombre'  => $this->tarea->tablero?->nombre ?? 'Tablero',
                'columnaNombre'  => $this->tarea->columna?->nombre ?? '',
                'userName'       => $this->tarea->asignado?->name ?? 'Usuario',
                'tareaUrl'       => route('kanban.show', $this->tarea->tablero_id),
            ],
        );
    }
}
