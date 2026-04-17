<?php

namespace App\Mail;

use App\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ServiceSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Service $service;

    public function __construct(Service $service)
    {
        $this->service = $service;
    }

    public function build()
    {
        $this->service->loadMissing('coach');

        return $this->subject('📩 Service submitted for review — ZAIVIAS')
            ->view('emails.services.submitted')
            ->with([
                'service' => $this->service,
                'coach'   => $this->service->coach,
            ]);
    }
}
