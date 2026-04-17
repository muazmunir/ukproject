<?php

namespace App\Mail;

use App\Models\StaffInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StaffInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public StaffInvite $invite) {}

    public function build()
    {
        $user = $this->invite->user;

        $subjectRole = $user->role === 'manager' ? 'Manager' : 'Admin';

        return $this->subject("You’ve been invited to ZAIVIAS as {$subjectRole}")
            ->markdown('emails.staff.invite', [
                'invite' => $this->invite,
                'user'   => $user,
                'role'   => $subjectRole,
                'link'   => route('staff.invite.show', $this->invite->token),
            ]);
    }
}
