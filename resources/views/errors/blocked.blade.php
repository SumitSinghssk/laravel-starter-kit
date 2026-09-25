<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="robots" content="noindex, nofollow" />
        <title>Access blocked</title>
        <style>
            :root {
                color-scheme: light dark;
                --bg: #f8fafc;
                --card: #ffffff;
                --text: #0f172a;
                --muted: #64748b;
                --line: #e2e8f0;
                --accent: #dc2626;
            }
            @media (prefers-color-scheme: dark) {
                :root {
                    --bg: #020617;
                    --card: #0f172a;
                    --text: #f1f5f9;
                    --muted: #94a3b8;
                    --line: #1e293b;
                    --accent: #f87171;
                }
            }
            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 16px;
                background: var(--bg);
                color: var(--text);
                font-family: -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
            }
            main {
                max-width: 420px;
                width: 100%;
                background: var(--card);
                border: 1px solid var(--line);
                border-radius: 16px;
                padding: 32px 28px;
                text-align: center;
            }
            .icon {
                width: 48px;
                height: 48px;
                margin: 0 auto 16px;
                border-radius: 999px;
                display: grid;
                place-items: center;
                background: color-mix(in srgb, var(--accent) 12%, transparent);
                color: var(--accent);
                font-size: 22px;
                font-weight: 700;
            }
            h1 {
                margin: 0 0 8px;
                font-size: 20px;
            }
            p {
                margin: 0;
                color: var(--muted);
                font-size: 14px;
                line-height: 1.6;
            }
        </style>
    </head>
    <body>
        <main>
            <div class="icon" aria-hidden="true">!</div>
            <h1>Access blocked</h1>
            <p>
                Requests from your network were blocked to protect this website.

                @if ($until)
                    You can try again after {{ \App\Support\LocalTime::dateTime($until, true) }}.
                @else
                    If you think this is a mistake, please contact the site owner.
                @endif
            </p>
        </main>
    </body>
</html>
