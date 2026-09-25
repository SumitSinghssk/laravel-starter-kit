@if ($heading)
    {!! $heading !!}
@endif

{!! $bodyText !!}
@if ($button && $url)
    {!! $button !!}: {!! $url !!}
@endif

@if ($details)
    @foreach ($details as $label => $value)
        {!! $label !!}: {!! $value !!}
    @endforeach
@endif

@if ($noteText !== '')
    {!! $noteText !!}
@endif

-- {!! $appName !!} · {!! $design['footer'] !!}
