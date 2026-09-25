{!! $body !!}
@if ($original)
    ---- Your message{!! $receivedAt ? ' on ' . $receivedAt : '' !!}:
    {!! $original !!}
@endif

-- {!! $appName !!}{!! filled($footer) ? ' · ' . $footer : '' !!}
