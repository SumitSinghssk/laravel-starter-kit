@php
    $emailDesign = $design ?? \App\Support\EmailTemplates::design();
    $emailLogo = $logo ?? ($emailDesign['show_logo'] ? \App\Helpers\Settings::logoLight() : null);
@endphp

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>@yield('title')</title>
    </head>
    <body
        style="margin: 0; padding: 32px 16px; background: #f1f5f9; font-family: -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; color: #334155"
    >
        @if ($emailLogo)
            <div style="max-width: 520px; margin: 0 auto 16px; text-align: center">
                <img src="{{ $emailLogo }}" alt="{{ $appName }}" style="max-height: 40px; max-width: 200px; border: 0" />
            </div>
        @endif

        <table
            role="presentation"
            width="100%"
            cellpadding="0"
            cellspacing="0"
            style="
                max-width: 520px;
                margin: 0 auto;
                background: #ffffff;
                border-radius: 12px;
                border: 1px solid #e2e8f0;
                border-top: 3px solid {{ $emailDesign['accent'] }};
            "
        >
            <tr>
                <td style="padding: 32px">
                    @yield('content')
                </td>
            </tr>
        </table>
        <p style="max-width: 520px; margin: 16px auto 0; font-size: 12px; line-height: 1.5; color: #94a3b8; text-align: center">
            {{ $appName }} ·
            @hasSection('footer')
                @yield('footer')
            @else
                {{ $emailDesign['footer'] }}
            @endif
        </p>
    </body>
</html>
