<?php

namespace App\Mail;

use App\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ServiceApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Service $service;

    public function __construct(Service $service)
    {
        $this->service = $service;
    }

    public function build()
    {
        $coach = $this->service->coach;

        return $this->subject('✅ Your ZAIVIAS service has been approved')
            ->view('emails.services.approved')
            ->with([
                'service' => $this->service,
                'coach'   => $coach,
            ]);
    }
}
