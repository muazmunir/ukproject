<?php

namespace App\Mail;

use App\Models\Users;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Users $user) {}

    public function build()
    {
        return $this->subject('Welcome to ZAIVIAS 🎉')
            ->view('emails.welcome');
    }
}
