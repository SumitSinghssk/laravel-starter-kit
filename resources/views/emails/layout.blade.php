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
        <table
            role="presentation"
            width="100%"
            cellpadding="0"
            cellspacing="0"
            style="max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0"
        >
            <tr>
                <td style="padding: 32px">
                    @yield('content')
                </td>
            </tr>
        </table>
        <p style="max-width: 520px; margin: 16px auto 0; font-size: 12px; line-height: 1.5; color: #94a3b8; text-align: center">
            {{ $appName }} ·
            @yield('footer', 'This is an automatic message.')
        </p>
    </body>
</html>
