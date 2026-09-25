@extends('emails.layout')

@section('title', 'Your account was locked')

@section('content')
    <h1 style="margin: 0 0 8px; font-size: 20px; color: #0f172a">Your account was locked</h1>
    <p style="margin: 0 0 20px; font-size: 14px; line-height: 1.6">
        Hi {{ $name }}, there were {{ $failures }} failed attempts to sign in to your {{ $appName }} admin account ({{ $email }}), so we locked it
        to keep it safe.

        @if ($until)
            It unlocks by itself at {{ $until }}.
        @else
            An administrator needs to unlock it.
        @endif
    </p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size: 13px; background: #f8fafc; border-radius: 8px">
        <tr>
            <td style="padding: 10px 14px; color: #64748b">When</td>
            <td style="padding: 10px 14px; color: #0f172a; text-align: right">{{ $when }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 14px; color: #64748b; border-top: 1px solid #e2e8f0">Last attempt from</td>
            <td style="padding: 10px 14px; color: #0f172a; text-align: right; border-top: 1px solid #e2e8f0">{{ $device }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 14px; color: #64748b; border-top: 1px solid #e2e8f0">IP address</td>
            <td style="padding: 10px 14px; color: #0f172a; text-align: right; border-top: 1px solid #e2e8f0">{{ $ip }}</td>
        </tr>
    </table>
    <p style="margin: 20px 0 0; font-size: 13px; line-height: 1.6; color: #64748b">
        Forgot your password? Resetting it unlocks the account straight away:
        <a href="{{ $resetUrl }}" style="color: #2563eb">reset your password</a>
        <br />
        If it wasn't you, someone may be guessing your password, so choose a strong new one.
    </p>
@endsection
