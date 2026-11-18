<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('code', '500') · {{ config('app.name', 'ClaimGuard') }}</title>
        <style>
            :root { color-scheme: light; }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
                background: #f1f5f9;
                color: #0f172a;
                font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            }
            .card {
                width: 100%;
                max-width: 520px;
                padding: 32px;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
                text-align: center;
            }
            .code {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 999px;
                background: #ccfbf1;
                color: #115e59;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.08em;
            }
            h1 { margin: 16px 0 8px; font-size: 24px; line-height: 1.3; }
            p { margin: 0; color: #475569; font-size: 14px; line-height: 1.6; }
            .actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 24px; }
            .actions a {
                padding: 10px 16px;
                border-radius: 10px;
                font-size: 13px;
                font-weight: 600;
                text-decoration: none;
            }
            .primary { background: #0f766e; color: #ffffff; }
            .secondary { background: #f1f5f9; color: #334155; }
            .hint { margin-top: 20px; font-size: 11px; color: #94a3b8; }
        </style>
    </head>
    <body>
        <main class="card">
            <span class="code">@yield('code', '500')</span>
            <h1>@yield('title', 'Terjadi kesalahan')</h1>
            <p>@yield('message', 'Permintaan tidak dapat diproses saat ini.')</p>
            <div class="actions">
                <a class="primary" href="{{ url('/') }}">Beranda</a>
                @auth
                    <a class="secondary" href="{{ route('dashboard') }}">Dashboard</a>
                @endauth
            </div>
            <p class="hint">Jika masalah berlanjut, hubungi administrator sistem.</p>
        </main>
    </body>
</html>
