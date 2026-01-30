<x-mail::message>
# Welcome to Memofy!

You have been invited to join the BukSU Memofy system as a **{{ ucfirst($invitation->role) }}**.

Please click the button below to set up your password and access your account.

<x-mail::button :url="config('app.frontend_url') . '/setup-password?token=' . $invitation->token">
Set Up Account
</x-mail::button>

This link will expire in 7 days.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
