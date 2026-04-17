<?php

namespace App\Mail;

use App\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ServiceResubmittedMail extends Mailable
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

        return $this->subject('🔁 Service updated — back under review (ZAIVIAS)')
            ->view('emails.services.resubmitted')
            ->with([
                'service' => $this->service,
                'coach'   => $this->service->coach,
            ]);
    }
}
