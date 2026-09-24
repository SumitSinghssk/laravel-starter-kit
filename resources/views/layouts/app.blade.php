<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        @stack('heads')
        @stack('head-scripts')
    </head>

    <body class="{{ $bodyClass }}">
        {{ $slot }}

        @stack('scripts')
    </body>
</html>
