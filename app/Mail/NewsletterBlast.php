<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class NewsletterBlast extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectText,
        public string $bodyText,
        public array $mailAttachments = [] // renamed ✅
    ) {}

    public function build()
    {
        $mail = $this->subject($this->subjectText)
            ->view('emails.newsletter-blast')
            ->with(['body' => $this->bodyText]);

        foreach ($this->mailAttachments as $att) {
            $path = $att['path'] ?? null;
            $name = $att['name'] ?? null;

            if ($path && Storage::exists($path)) {
                $mail->attach(Storage::path($path), [
                    'as' => $name ?: basename($path),
                ]);
            }
        }

        return $mail;
    }
}
