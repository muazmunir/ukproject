<?php

namespace App\Mail;

use App\Models\Users;
use Illuminate\Mail\Mailable;

class CoachUnderReviewMail extends Mailable
{
    public function __construct(public Users $user) {}

    public function build()
    {
        return $this->subject('Your ZAIVIAS Coach Account Is Under Review')
            ->view('emails.coach_under_review');
    }
}
