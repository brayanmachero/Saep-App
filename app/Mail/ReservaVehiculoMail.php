<?php

namespace App\Mail;

use App\Models\ReservaVehiculo;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservaVehiculoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ReservaVehiculo $reserva,
        public string $tipo,
        public ?User $actor = null,
        public ?array $contexto = null,
    ) {}

    public function envelope(): Envelope
    {
        $asunto = match ($this->tipo) {
            'confirmacion' => 'Reserva de vehiculo confirmada',
            'recordatorio' => 'Recordatorio de reserva de vehiculo',
            'vencimiento' => 'Reserva de vehiculo vencida',
            'administracion' => 'Nueva reserva de vehiculo',
            'cancelacion' => 'Reserva de vehiculo cancelada',
            'actualizacion' => 'Reserva de vehiculo actualizada',
            'reprogramacion' => 'Reserva de vehiculo reprogramada por Bodega',
            'ampliacion' => 'Horario de reserva ampliado',
            'eventualidad' => 'Eventualidad reportada en reserva de vehiculo',
            'eliminacion' => 'Reserva de vehiculo eliminada',
            default => 'Actualizacion de reserva de vehiculo',
        };

        return new Envelope(subject: $asunto.' - '.$this->reserva->codigo);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reserva_vehiculo');
    }
}
