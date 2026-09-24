@extends('emails.layout')

@section('title', 'Reset your password')

@section('content')
    <h1 style="margin: 0 0 8px; font-size: 20px; color: #0f172a">Reset your password</h1>
    <p style="margin: 0 0 20px; font-size: 14px; line-height: 1.6">
        Hi {{ $name }}, someone asked to reset the password for your {{ $appName }} admin account ({{ $email }}). Click the button to choose a new
        one.
    </p>
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 0 20px">
        <tr>
            <td style="border-radius: 8px; background: #2563eb">
                <a
                    href="{{ $url }}"
                    style="display: inline-block; padding: 12px 22px; font-size: 14px; font-weight: 600; color: #ffffff; text-decoration: none"
                >
                    Choose a new password
                </a>
            </td>
        </tr>
    </table>
    <p style="margin: 0 0 20px; font-size: 13px; line-height: 1.6; color: #64748b">
        The link works once and expires in {{ $minutes }} minutes. If the button doesn't work, copy this address into your browser:
        <br />
        <a href="{{ $url }}" style="color: #2563eb; word-break: break-all">{{ $url }}</a>
    </p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size: 13px; background: #f8fafc; border-radius: 8px">
        <tr>
            <td style="padding: 10px 14px; color: #64748b">Requested from</td>
            <td style="padding: 10px 14px; color: #0f172a; text-align: right">{{ $device }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 14px; color: #64748b; border-top: 1px solid #e2e8f0">IP address</td>
            <td style="padding: 10px 14px; color: #0f172a; text-align: right; border-top: 1px solid #e2e8f0">{{ $ip }}</td>
        </tr>
    </table>
    <p style="margin: 20px 0 0; font-size: 13px; line-height: 1.6; color: #64748b">
        Didn't ask for this? You can ignore this email. Your password stays the same until someone uses the link.
    </p>
@endsection
