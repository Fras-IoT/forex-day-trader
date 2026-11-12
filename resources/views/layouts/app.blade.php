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
            padding: 0.95rem 1.15rem;
            border-radius: 6px;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        .ai-box > :first-child { margin-top: 0; }
        .ai-box > :last-child { margin-bottom: 0; }
        .ai-box h1, .ai-box h2, .ai-box h3, .ai-box h4 {
            margin: 0.85rem 0 0.4rem;
            font-size: 0.98rem;
            font-weight: 700;
            color: var(--text);
        }
        .ai-box p { margin: 0.45rem 0; }
        .ai-box ul, .ai-box ol {
            margin: 0.35rem 0 0.55rem;
            padding-left: 1.25rem;
        }
        .ai-box li { margin: 0.25rem 0; }
        .ai-box hr {
            border: 0;
            border-top: 1px solid var(--border);
            margin: 0.75rem 0;
        }
        footer {
            max-width: 1280px;
            margin: 1rem auto 2rem;
            padding: 0 1.25rem;
            font-size: 0.8rem;
            color: var(--muted);
        }

        /* Floating Gemini Copilot Web Chat Widget */
        .copilot-fab {
            position: fixed;
            right: 1.25rem;
            bottom: 1.25rem;
            z-index: 40;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.72rem 1.05rem;
            border-radius: 999px;
            border: 1px solid rgba(56, 189, 248, 0.4);
            background: linear-gradient(135deg, #2563eb, #0ea5e9);
            color: #ffffff;
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            box-shadow: 0 10px 26px rgba(37, 99, 235, 0.35);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .copilot-fab:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(37, 99, 235, 0.45);
        }
        .copilot-fab-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #4ade80;
            box-shadow: 0 0 8px #4ade80;
        }
        #copilot-widget[popover] {
            position: fixed;
            inset: auto 1.25rem 4.85rem auto;
            margin: 0;
            padding: 0;
            width: min(400px, calc(100vw - 2rem));
            height: min(540px, calc(100vh - 6.5rem));
            border-radius: 14px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            box-shadow: 0 20px 48px rgba(15, 23, 42, 0.28);
            display: none;
            flex-direction: column;
            overflow: hidden;
        }
        #copilot-widget[popover]:is(:popover-open, .\:popover-open) {
            display: flex;
        }
        .copilot-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 0.75rem 0.9rem;
            background: linear-gradient(135deg, #0f172a, #1e293b);
            color: #f8fafc;
        }
        .copilot-head-actions {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        .copilot-icon-btn {
            background: rgba(255, 255, 255, 0.12);
            color: #f8fafc;
            border: 0;
            border-radius: 6px;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            cursor: pointer;
        }
        .copilot-icon-btn:hover { background: rgba(255, 255, 255, 0.22); }
        .copilot-messages {
            flex: 1;
            overflow-y: auto;
            padding: 0.85rem;
            background: var(--bg);
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }
        .copilot-msg {
            max-width: 88%;
            padding: 0.55rem 0.8rem;
            border-radius: 10px;
            font-size: 0.84rem;
            line-height: 1.5;
        }
        .copilot-msg-user {
            align-self: flex-end;
            background: var(--brand);
            color: var(--brand-text);
            white-space: pre-wrap;
        }
        .copilot-msg-ai {
            align-self: flex-start;
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text);
        }
        .copilot-msg-ai > :first-child { margin-top: 0; }
        .copilot-msg-ai > :last-child { margin-bottom: 0; }
        .copilot-msg-ai p { margin: 0.35rem 0; }
        .copilot-msg-ai ul, .copilot-msg-ai ol { margin: 0.25rem 0 0.4rem; padding-left: 1.1rem; }
        .copilot-msg-ai h1, .copilot-msg-ai h2, .copilot-msg-ai h3, .copilot-msg-ai h4 {
            margin: 0.5rem 0 0.25rem;
            font-size: 0.9rem;
        }
        .copilot-form {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            padding: 0.65rem 0.75rem;
            border-top: 1px solid var(--border);
            background: var(--surface);
        }
        .copilot-form-row {
            display: flex;
            gap: 0.4rem;
            align-items: center;
        }
        .copilot-form select {
            width: 112px;
            padding: 0.42rem 0.45rem;
            font-size: 0.78rem;
        }
        .copilot-form input[type="text"] {
            flex: 1;
            padding: 0.45rem 0.6rem;
            font-size: 0.84rem;
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
        @if(auth()->user()?->role === 'admin')
            <a href="{{ route('accounts.index') }}" class="{{ request()->routeIs('accounts.*') ? 'active' : '' }}">Akun</a>
            <a href="{{ route('settings.index') }}" class="{{ request()->routeIs('settings.*') ? 'active' : '' }}">Settings</a>
        @endif
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
    <strong>Disclaimer:</strong> Bukan nasihat keuangan (Not financial advice). Paper trading aktif secara default. Tidak ada order yang dieksekusi tanpa konfirmasi eksplisit Anda.
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

@auth
<button type="button" class="copilot-fab" commandfor="copilot-widget" command="toggle-popover" aria-label="Buka Chat Gemini Copilot">
    <span class="copilot-fab-dot" aria-hidden="true"></span>
    <span>&#x1F4AC; Gemini Copilot</span>
</button>

<div id="copilot-widget" popover>
    <div class="copilot-head">
        <div>
            <div style="font-weight:700; font-size:0.9rem;">Gemini FX Copilot</div>
            <div style="font-size:0.72rem; color:#94a3b8;">Asisten Trading &amp; Manajemen Risiko</div>
        </div>
        <div class="copilot-head-actions">
            <button type="button" id="copilot-clear-btn" class="copilot-icon-btn" title="Bersihkan chat">Clear</button>
            <button type="button" class="copilot-icon-btn" commandfor="copilot-widget" command="hide-popover" aria-label="Tutup chat">&#x2715;</button>
        </div>
    </div>

    <div id="copilot-messages" class="copilot-messages">
        <div class="muted" style="font-size:0.8rem;">Memuat riwayat percakapan...</div>
    </div>

    <form id="copilot-chat-form" class="copilot-form">
        <div class="copilot-form-row">
            <select id="copilot-pair" name="pair" aria-label="Fokus Pair">
                @foreach(array_keys(\App\Services\ForexMarketService::PAIRS) as $p)
                    <option value="{{ $p }}">{{ $p }}</option>
                @endforeach
            </select>
            <input id="copilot-input" name="message" type="text" placeholder="Tanya setup, SL/TP, atau risiko..." required autocomplete="off">
            <button type="submit" id="copilot-send-btn" class="btn btn-primary btn-sm">Kirim</button>
        </div>
    </form>
</div>

<script type="module">
    if (!Object.hasOwn(HTMLElement.prototype, 'popover')) {
        import('https://unpkg.com/@oddbird/popover-polyfill@latest/dist/popover.min.js');
    }
    if (!('commandForElement' in HTMLButtonElement.prototype)) {
        import('https://esm.run/invokers-polyfill');
    }
</script>

<script>
(() => {
    const widget = document.getElementById('copilot-widget');
    const messagesEl = document.getElementById('copilot-messages');
    const form = document.getElementById('copilot-chat-form');
    const input = document.getElementById('copilot-input');
    const pairSelect = document.getElementById('copilot-pair');
    const sendBtn = document.getElementById('copilot-send-btn');
    const clearBtn = document.getElementById('copilot-clear-btn');
    if (!widget || !messagesEl || !form) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let lastInteractionId = null;
    let loaded = false;

    function scrollBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function appendMessage(role, htmlOrText, isHtml = false) {
        const emptyHint = messagesEl.querySelector('.copilot-empty');
        if (emptyHint) emptyHint.remove();

        const div = document.createElement('div');
        div.className = 'copilot-msg ' + (role === 'user' ? 'copilot-msg-user' : 'copilot-msg-ai');
        if (isHtml) {
            div.innerHTML = htmlOrText;
        } else {
            div.textContent = htmlOrText;
        }
        messagesEl.appendChild(div);
        scrollBottom();
        return div;
    }

    async function loadHistory() {
        if (loaded) return;
        try {
            const res = await fetch('{{ route('copilot.history') }}', { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            loaded = true;
            lastInteractionId = data.lastInteractionId || null;
            messagesEl.innerHTML = '';

            if (!data.configured) {
                messagesEl.innerHTML = '<div class="alert-banner alert-warn" style="margin:0;">Token Gemini API belum diisi. <a href="{{ route('settings.index') }}">Buka Settings</a>.</div>';
            }

            if (!data.messages || !data.messages.length) {
                const p = document.createElement('div');
                p.className = 'muted copilot-empty';
                p.style.fontSize = '0.8rem';
                p.textContent = 'Halo! Tanyakan setup pair, saran SL/TP, atau evaluasi risiko trading Anda.';
                messagesEl.appendChild(p);
                return;
            }

            data.messages.forEach(m => appendMessage(m.role, m.html, true));
        } catch (_) {
            messagesEl.innerHTML = '<div class="muted" style="font-size:0.8rem;">Gagal memuat riwayat chat.</div>';
        }
    }

    widget.addEventListener('toggle', (e) => {
        if (e.newState === 'open') {
            loadHistory();
            setTimeout(() => input?.focus(), 60);
        }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = input.value.trim();
        if (!text) return;

        appendMessage('user', text, false);
        input.value = '';
        sendBtn.disabled = true;
        const typing = appendMessage('assistant', '<span class="muted">Gemini sedang mengetik...</span>', true);

        try {
            const res = await fetch('{{ route('copilot.send') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    message: text,
                    pair: pairSelect.value,
                    previous_interaction_id: lastInteractionId
                })
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                typing.innerHTML = '<span style="color:var(--red);">' + (data.error || 'Gagal menghubungi Gemini.') + '</span>';
            } else {
                lastInteractionId = data.interaction_id || lastInteractionId;
                typing.innerHTML = data.html;
            }
        } catch (_) {
            typing.innerHTML = '<span style="color:var(--red);">Koneksi terputus. Coba lagi.</span>';
        } finally {
            sendBtn.disabled = false;
            scrollBottom();
        }
    });

    clearBtn?.addEventListener('click', async () => {
        try {
            await fetch('{{ route('copilot.clear') }}', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
            });
            lastInteractionId = null;
            messagesEl.innerHTML = '<div class="muted copilot-empty" style="font-size:0.8rem;">Riwayat percakapan dibersihkan. Silakan mulai pertanyaan baru.</div>';
        } catch (_) {}
    });
})();
</script>
@endauth
</body>
</html>

