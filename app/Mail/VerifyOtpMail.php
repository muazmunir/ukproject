<?php

namespace App\Mail;

use App\Models\Users;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerifyOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Users $user, public string $code) {}

    public function build() {
        return $this->subject('Your ZAIVIAS verification code')
            ->view('emails.verify-otp');
    }
}
