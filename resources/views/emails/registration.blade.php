@component('mail::message')
# Welcome, {{ $user->name }}!

Your SmartCar AI account has been created.

**Email:** {{ $user->email }}  
**Password:** {{ $plainPassword }}

@if ($isTemporary)
> This is a temporary password. Please change it after you sign in.
@endif

@component('mail::button', ['url' => $loginUrl])
Sign in
@endcomponent

If the button doesn’t work, copy and paste this link into your browser:
{{ $loginUrl }}

Thanks,<br>
{{ config('app.name') }}
@endcomponent
