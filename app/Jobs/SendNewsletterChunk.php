<?php

namespace App\Jobs;

use App\Mail\NewsletterBlast;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendNewsletterChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200; // 20 min (adjust)
    public int $tries = 3;

    public function __construct(
        public array $emails,
        public string $subjectText,
        public string $bodyText,
        public array $attachments = []
    ) {}

    public function handle(): void
    {
        foreach ($this->emails as $email) {
            // Send inside the job
            Mail::to($email)->send(
                new NewsletterBlast(
                    $this->subjectText,
                    $this->bodyText,
                    $this->attachments
                )
            );
        }
    }
}
