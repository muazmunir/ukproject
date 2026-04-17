<?php


namespace App\Mail;

use App\Models\Users;
use Illuminate\Mail\Mailable;

class ClientWelcomeMail extends Mailable
{
    public function __construct(public Users $user) {}

    public function build()
    {
        return $this->subject('Welcome to ZAIVIAS 🎉')
            ->view('emails.client_welcome');
    }
}

