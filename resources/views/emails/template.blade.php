@extends('emails.layout')

@section('title', $heading ?: $subject)

@section('content')
    @if ($heading)
        <h1 style="margin: 0 0 12px; font-size: 20px; line-height: 1.3; color: #0f172a">{{ $heading }}</h1>
    @endif

    <div style="font-size: 14px; line-height: 1.6">{!! $body !!}</div>

    @if ($button && $url)
        <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 6px 0 20px">
            <tr>
                <td style="border-radius: 8px; background: {{ $design['accent'] }}">
                    <a
                        href="{{ $url }}"
                        style="display: inline-block; padding: 12px 22px; font-size: 14px; font-weight: 600; color: #ffffff; text-decoration: none"
                    >
                        {{ $button }}
                    </a>
                </td>
            </tr>
        </table>
        <p style="margin: 0 0 20px; font-size: 12px; line-height: 1.6; color: #64748b">
            If the button doesn't work, copy this address into your browser:
            <br />
            <a href="{{ $url }}" style="color: {{ $design['accent'] }}; word-break: break-all">{{ $url }}</a>
        </p>
    @endif

    @if ($details)
        <table
            role="presentation"
            width="100%"
            cellpadding="0"
            cellspacing="0"
            style="margin: 6px 0 0; font-size: 13px; background: #f8fafc; border-radius: 8px"
        >
            @foreach ($details as $label => $value)
                <tr>
                    <td style="padding: 10px 14px; color: #64748b{{ $loop->first ? '' : '; border-top: 1px solid #e2e8f0' }}">{{ $label }}</td>
                    <td style="padding: 10px 14px; color: #0f172a; text-align: right{{ $loop->first ? '' : '; border-top: 1px solid #e2e8f0' }}">
                        {{ $value }}
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($noteText !== '')
        <div style="margin: 20px 0 0; font-size: 13px; line-height: 1.6; color: {{ $warn ? '#b91c1c' : '#64748b' }}">{!! $note !!}</div>
    @endif
@endsection
