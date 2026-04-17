<?php

namespace App\Mail;

use App\Models\Users;
use Illuminate\Mail\Mailable;

class CoachApprovedMail extends Mailable
{
    public function __construct(public Users $user) {}

    public function build()
    {
        return $this->subject('Your ZAIVIAS Coach Account Has Been Approved')
            ->view('emails.coach_approved');
    }
}

