<?php

namespace App\Mail;

use App\Models\Service;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ServiceRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Service $service;
    public ?string $reason;

    public function __construct(Service $service, ?string $reason = null)
    {
        $this->service = $service;
        $this->reason  = $reason;
    }

    public function build()
    {
        $coach = $this->service->coach;

        return $this->subject('❌ Your ZAIVIAS service was rejected')
            ->view('emails.services.rejected')
            ->with([
                'service' => $this->service,
                'coach'   => $coach,
                'reason'  => $this->reason,
            ]);
    }
}
