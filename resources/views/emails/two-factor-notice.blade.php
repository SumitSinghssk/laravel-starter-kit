@extends('emails.layout')

@section('title', $title)

@section('content')
    <h1 style="margin: 0 0 8px; font-size: 20px; color: #0f172a">{{ $title }}</h1>
    <p style="margin: 0 0 20px; font-size: 14px; line-height: 1.6">Hi {{ $name }}, {{ lcfirst($text) }}</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size: 13px; background: #f8fafc; border-radius: 8px">
        <tr>
            <td style="padding: 10px 14px; color: #64748b">Account</td>
            <td style="padding: 10px 14px; color: #0f172a; text-align: right">{{ $email }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 14px; color: #64748b; border-top: 1px solid #e2e8f0">When</td>
            <td style="padding: 10px 14px; color: #0f172a; text-align: right; border-top: 1px solid #e2e8f0">{{ $when }}</td>
        </tr>
        @if ($by)
            <tr>
                <td style="padding: 10px 14px; color: #64748b; border-top: 1px solid #e2e8f0">Done by</td>
                <td style="padding: 10px 14px; color: #0f172a; text-align: right; border-top: 1px solid #e2e8f0">{{ $by }}</td>
            </tr>
        @endif

        <tr>
            <td style="padding: 10px 14px; color: #64748b; border-top: 1px solid #e2e8f0">Device</td>
            <td style="padding: 10px 14px; color: #0f172a; text-align: right; border-top: 1px solid #e2e8f0">{{ $device }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 14px; color: #64748b; border-top: 1px solid #e2e8f0">IP address</td>
            <td style="padding: 10px 14px; color: #0f172a; text-align: right; border-top: 1px solid #e2e8f0">{{ $ip }}</td>
        </tr>
    </table>
    @if ($warn)
        <p style="margin: 20px 0 0; font-size: 13px; line-height: 1.6; color: #b91c1c">
            Wasn't you? Change your password straight away and tell your site administrator.
        </p>
    @endif
@endsection
