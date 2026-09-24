<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Test email</title>
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
                    <div
                        style="
                            width: 44px;
                            height: 44px;
                            border-radius: 22px;
                            background: #dcfce7;
                            color: #16a34a;
                            font-size: 24px;
                            line-height: 44px;
                            text-align: center;
                        "
                    >
                        &#10003;
                    </div>
                    <h1 style="margin: 20px 0 8px; font-size: 20px; color: #0f172a">Your email settings work</h1>
                    <p style="margin: 0 0 20px; font-size: 14px; line-height: 1.6">
                        This is a test email from {{ $appName }}. If you can read it, the website can send emails: contact form replies, password
                        resets and notifications.
                    </p>
                    <table
                        role="presentation"
                        width="100%"
                        cellpadding="0"
                        cellspacing="0"
                        style="font-size: 13px; background: #f8fafc; border-radius: 8px"
                    >
                        <tr>
                            <td style="padding: 10px 14px; color: #64748b">Server</td>
                            <td style="padding: 10px 14px; color: #0f172a; font-family: monospace">{{ $server }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 14px; color: #64748b">Encryption</td>
                            <td style="padding: 10px 14px; color: #0f172a">{{ $encryption }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 14px; color: #64748b">From</td>
                            <td style="padding: 10px 14px; color: #0f172a">{{ $fromAddress }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 14px; color: #64748b">Sent</td>
                            <td style="padding: 10px 14px; color: #0f172a">{{ $sentAt }}</td>
                        </tr>
                    </table>
                    <p style="margin: 20px 0 0; font-size: 12px; color: #94a3b8">Sent from Admin → Settings → Email. You can ignore this message.</p>
                </td>
            </tr>
        </table>
    </body>
</html>
