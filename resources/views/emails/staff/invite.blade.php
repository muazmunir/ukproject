@component('mail::message')
# Welcome to ZAIVIAS

Hi {{ $user->first_name ?? 'there' }},

You’ve been added as a **{{ $role }}** on ZAIVIAS.

Click the button below to set your password and access the dashboard.

@component('mail::button', ['url' => $link])
Set your password
@endcomponent

This invite link expires in **24 hours**.

If you didn’t expect this invite, you can ignore this email.

Thanks,  
**ZAIVIAS Team**
@endcomponent
