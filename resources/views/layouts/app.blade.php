<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', 'Forex Day Trader AI') — Gemini + MongoDB</title>
    <style>
        :root {
            --bg-light: #f4f6f9;
            --bg-dark: #0b0f17;
            --surface-light: #ffffff;
            --surface-dark: #131a26;
            --surface-2-light: #eef2f6;
            --surface-2-dark: #1b2434;
            --border-light: #d7dee8;
            --border-dark: #28354a;
            --text-light: #0f172a;
            --text-dark: #e6edf7;
            --muted-light: #475569;
            --muted-dark: #94a3b8;
            --brand-light: #0d6efd;
            --brand-dark: #38bdf8;
            --brand-text-light: #ffffff;
            --brand-text-dark: #06131f;
            --green-light: #15803d;
            --green-dark: #4ade80;
            --red-light: #b91c1c;
            --red-dark: #f87171;
            --amber-light: #b45309;
            --amber-dark: #fbbf24;

            --bg: var(--bg-light);
            --surface: var(--surface-light);
            --surface-2: var(--surface-2-light);
            --border: var(--border-light);
            --text: var(--text-light);
            --muted: var(--muted-light);
            --brand: var(--brand-light);
            --brand-text: var(--brand-text-light);
            --green: var(--green-light);
            --red: var(--red-light);
            --amber: var(--amber-light);

            color-scheme: light dark;
            accent-color: var(--brand);
            scrollbar-color: #6b7280 transparent;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: var(--bg-dark);
                --surface: var(--surface-dark);
                --surface-2: var(--surface-2-dark);
                --border: var(--border-dark);
                --text: var(--text-dark);
                --muted: var(--muted-dark);
                --brand: var(--brand-dark);
                --brand-text: var(--brand-text-dark);
                --green: var(--green-dark);
                --red: var(--red-dark);
                --amber: var(--amber-dark);
            }
        }

        @supports (color: light-dark(white, black)) {
            :root {
                --bg: light-dark(var(--bg-light), var(--bg-dark));
                --surface: light-dark(var(--surface-light), var(--surface-dark));
                --surface-2: light-dark(var(--surface-2-light), var(--surface-2-dark));
                --border: light-dark(var(--border-light), var(--border-dark));
                --text: light-dark(var(--text-light), var(--text-dark));
                --muted: light-dark(var(--muted-light), var(--muted-dark));
                --brand: light-dark(var(--brand-light), var(--brand-dark));
                --brand-text: light-dark(var(--brand-text-light), var(--brand-text-dark));
                --green: light-dark(var(--green-light), var(--green-dark));
                --red: light-dark(var(--red-light), var(--red-dark));
                --amber: light-dark(var(--amber-light), var(--amber-dark));
            }
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }
        a { color: var(--brand); text-decoration: none; }
        a:hover { text-decoration: underline; }

        .topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0.75rem 1.25rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            position: sticky;
            top: 0;
            z-index: 20;
        }
        .brand {
            font-weight: 700;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text);
        }
        .nav {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }
        .nav a {
            padding: 0.4rem 0.7rem;
            border-radius: 6px;
            font-size: 0.88rem;
            font-weight: 500;
            color: var(--muted);
        }
        .nav a:hover { background: var(--surface-2); color: var(--text); text-decoration: none; }
        .nav a.active { background: var(--brand); color: var(--brand-text); }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 1.25rem;
        }
        .grid { display: grid; gap: 1rem; }
        .grid-2 { grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
        .grid-4 { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1rem 1.15rem;
        }
        .card h2, .card h3 { margin-top: 0; font-size: 1.05rem; }
        .muted { color: var(--muted); font-size: 0.88rem; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }

        .badge {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 600;
            border: 1px solid var(--border);
            background: var(--surface-2);
        }
        .badge-green { color: var(--green); border-color: var(--green); }
        .badge-red { color: var(--red); border-color: var(--red); }
        .badge-amber { color: var(--amber); border-color: var(--amber); }
        .badge-brand { color: var(--brand); border-color: var(--brand); }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.48rem 0.9rem;
            border-radius: 7px;
            border: 1px solid var(--border);
            background: var(--surface-2);
            color: var(--text);
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
        }
        .btn:hover { filter: brightness(0.95); text-decoration: none; }
        .btn-primary { background: var(--brand); color: var(--brand-text); border-color: var(--brand); }
        .btn-danger { background: var(--red); color: #fff; border-color: var(--red); }
        .btn-sm { padding: 0.3rem 0.6rem; font-size: 0.8rem; }

        .alert-banner {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid var(--border);
            background: var(--surface);
        }
        .alert-ok { border-color: var(--green); color: var(--green); }
        .alert-err { border-color: var(--red); color: var(--red); }
        .alert-warn { border-color: var(--amber); color: var(--amber); }

        label { display: block; font-size: 0.84rem; font-weight: 600; margin-bottom: 0.25rem; }
        input, select, textarea {
            width: 100%;
            padding: 0.48rem 0.65rem;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--text);
            font-size: 0.9rem;
        }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 0.75rem; margin-bottom: 0.75rem; }

        /* Responsive Table (modern-web-guidance) */
        .table-wrapper {
            overflow: auto;
            max-inline-size: 100%;
            max-block-size: min(520px, 75vh);
            container-type: inline-size;
            border: 1px solid var(--border);
            border-radius: 8px;
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.88rem;
        }
        caption {
            text-align: left;
            padding: 0.6rem 0.75rem;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--muted);
        }
        thead {
            position: sticky;
            inset-block-start: 0;
            z-index: 2;
            background: var(--surface-2);
        }
        th, td {
            padding: 0.55rem 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        tbody th {
            position: sticky;
            inset-inline-start: 0;
            background: var(--surface);
            z-index: 1;
        }
        @container (width < 600px) {
            table, thead, tbody, tr, th, td { display: block; }
            thead { display: none; }
            tr { margin-block-end: 0.85rem; border: 1px solid var(--border); border-radius: 6px; }
            tbody th { position: sticky; inset-block-start: 0; background: var(--surface-2); }
            td::before { content: attr(data-label) ": "; font-weight: 600; color: var(--muted); }
        }

        /* Native Modal Dialog (modern-web-guidance) */
        dialog {
            background: var(--surface);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.25rem;
            max-width: 480px;
            width: calc(100% - 2rem);
        }
        dialog:modal { overscroll-behavior: contain; }
        dialog::backdrop {
            background: rgb(0 0 0 / 0.55);
            overflow: hidden;
            overscroll-behavior: contain;
        }

        .ai-box {
            background: var(--surface-2);
            border-left: 3px solid var(--brand);
            padding: 0.85rem 1rem;
            border-radius: 6px;
            white-space: pre-wrap;
            font-size: 0.9rem;
        }
        footer {
            max-width: 1280px;
            margin: 1rem auto 2rem;
            padding: 0 1.25rem;
            font-size: 0.8rem;
            color: var(--muted);
        }
    </style>
</head>
<body>
<header class="topbar">
    <div class="brand">
        <span>FX</span>
        <span>Forex Day Trader AI</span>
    </div>
    <nav class="nav" aria-label="Main navigation">
        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Dashboard</a>
        <a href="{{ route('scanner.index') }}" class="{{ request()->routeIs('scanner.*') ? 'active' : '' }}">Scanner</a>
        <a href="{{ route('news.index') }}" class="{{ request()->routeIs('news.*') ? 'active' : '' }}">News</a>
        <a href="{{ route('plans.index') }}" class="{{ request()->routeIs('plans.*') ? 'active' : '' }}">Entry & Exit</a>
        <a href="{{ route('timemachine.index') }}" class="{{ request()->routeIs('timemachine.*') ? 'active' : '' }}">Time Machine</a>
        <a href="{{ route('broker.index') }}" class="{{ request()->routeIs('broker.*') ? 'active' : '' }}">Risk & Broker</a>
        <a href="{{ route('alerts.index') }}" class="{{ request()->routeIs('alerts.*') ? 'active' : '' }}">Alerts</a>
        <a href="{{ route('copilot.index') }}" class="{{ request()->routeIs('copilot.*') ? 'active' : '' }}">Gemini Copilot</a>
        <a href="{{ route('accounts.index') }}" class="{{ request()->routeIs('accounts.*') ? 'active' : '' }}">Akun</a>
        <a href="{{ route('settings.index') }}" class="{{ request()->routeIs('settings.*') ? 'active' : '' }}">Settings</a>
        <form method="POST" action="{{ route('logout') }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-sm" style="margin-left:0.25rem;">Logout</button>
        </form>
    </nav>
</header>

<main class="container">
    @if(session('status'))
        <div class="alert-banner alert-ok">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-banner alert-err">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert-banner alert-err">
            <ul style="margin:0; padding-left:1.2rem;">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>

<footer>
    <strong>Disclaimer:</strong> Bukan nasihat keuangan (Not financial advice). Paper trading aktif secara default. Tidak ada order yang dieksekusi tanpa konfirmasi eksplisit Anda. Terinspirasi dari <code>day-trader-skill</code>, diadaptasi untuk Forex + Gemini AI + Laravel + MongoDB.
</footer>

<script>
(() => {
    const els = Array.from(document.querySelectorAll('[data-live-pair]'));
    if (!els.length) return;

    const pairs = [...new Set(els.map(el => el.dataset.livePair))];
    const statusEl = document.getElementById('live-price-status');

    async function refreshPrices() {
        try {
            const res = await fetch('/live-prices?pairs=' + encodeURIComponent(pairs.join(',')), {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) return;
            const data = await res.json();
            const quotes = data.quotes || {};

            for (const el of els) {
                const q = quotes[el.dataset.livePair];
                if (!q) continue;
                const oldVal = parseFloat(el.textContent);
                const newVal = Number(q.price);
                const formatted = newVal.toFixed(q.decimals ?? 5);

                if (!Number.isNaN(oldVal) && newVal !== oldVal) {
                    el.style.color = newVal > oldVal ? 'var(--green)' : 'var(--red)';
                    el.style.fontWeight = '700';
                    setTimeout(() => { el.style.color = ''; }, 1400);
                }
                el.textContent = formatted;
            }
            if (statusEl && data.server_time) {
                statusEl.textContent = 'Live (' + data.server_time + ')';
            }
        } catch (_) {
            // Silent retry on next tick
        }
    }

    setInterval(refreshPrices, 5000);
})();
</script>
</body>
</html>

