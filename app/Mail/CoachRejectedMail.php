<?php

namespace App\Mail;

use App\Models\Users;
use Illuminate\Mail\Mailable;

class CoachRejectedMail extends Mailable
{
    public function __construct(public Users $user) {}

    public function build()
    {
        return $this->subject('Update on Your ZAIVIAS Coach Application')
            ->view('emails.coach_rejected');
    }
}

