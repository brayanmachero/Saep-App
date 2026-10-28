<?php

namespace App\Mail;

use App\Models\VehiculoActa;
use App\Services\VehiculoActaDocumentService;
use Illuminate\Mail\Mailable;

class VehiculoActaMail extends Mailable
{
    public function __construct(public VehiculoActa $acta) {}

    public function build(): self
    {
        return $this->subject('Acta de '.$this->acta->tipo.' · '.$this->acta->datos['reserva']['codigo'])
            ->view('emails.vehiculo_acta_saep')
            ->attachData(app(VehiculoActaDocumentService::class)->pdf($this->acta), 'Acta-'.$this->acta->uuid.'.pdf', ['mime' => 'application/pdf']);
    }
}
