<?php

namespace App\Mail;

use App\Models\KanbanTarea;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class KanbanTareaAsignadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public KanbanTarea $tarea,
        public User $asignador,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tarea asignada: ' . $this->tarea->titulo,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.kanban_tarea_asignada');
    }
}
