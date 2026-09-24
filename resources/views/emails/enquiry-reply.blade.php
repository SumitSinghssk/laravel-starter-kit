@extends('emails.layout')

@section('title', $appName)

@section('content')
    <div style="font-size: 15px; line-height: 1.7; color: #1e293b">{!! nl2br(e($body)) !!}</div>

    @if ($original)
        <div style="margin-top: 28px; padding: 14px 16px; border-left: 3px solid #cbd5e1; background: #f8fafc; border-radius: 0 8px 8px 0">
            <p style="margin: 0 0 6px; font-size: 12px; color: #64748b">Your message{{ $receivedAt ? ' on ' . $receivedAt : '' }}:</p>
            <div style="font-size: 13px; line-height: 1.6; color: #475569">{!! nl2br(e($original)) !!}</div>
        </div>
    @endif
@endsection

@section('footer', 'Reply to this email to answer ' . $senderName . ' directly. Reference ' . $reference . '.')
