<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>Login — Forex Day Trader AI</title>
    <style>
        :root {
            color-scheme: dark;
            --bg: #070b14;
            --surface: rgba(15, 23, 42, 0.82);
            --border: rgba(56, 189, 248, 0.22);
            --text: #e2e8f0;
            --muted: #94a3b8;
            --brand: #38bdf8;
            --green: #22c55e;
            --red: #ef4444;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            background: radial-gradient(circle at 20% 20%, #0f1f3a 0%, #070b14 55%, #04070d 100%);
            color: var(--text);
            display: grid;
            place-items: center;
            padding: 1.25rem;
            position: relative;
            overflow: hidden;
        }

        /* Forex Grid + Candlestick SVG Background */
        .fx-bg {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            opacity: 0.48;
        }
        .fx-grid {
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(to right, rgba(56, 189, 248, 0.06) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(56, 189, 248, 0.06) 1px, transparent 1px);
            background-size: 48px 48px;
            pointer-events: none;
            z-index: 0;
        }

        .login-wrap {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 430px;
        }

        .ticker-strip {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        .ticker-pill {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.74rem;
            padding: 0.25rem 0.6rem;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.75);
            border: 1px solid rgba(148, 163, 184, 0.22);
            color: var(--text);
            backdrop-filter: blur(8px);
        }
        .up { color: var(--green); }
        .dn { color: var(--red); }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1.75rem;
            backdrop-filter: blur(16px);
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.55);
        }
        .logo-row {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            margin-bottom: 0.35rem;
        }
        .logo-badge {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, #0ea5e9, #22c55e);
            color: #06131f;
            font-weight: 800;
            display: grid;
            place-items: center;
            font-size: 0.95rem;
        }
        h1 { margin: 0; font-size: 1.25rem; }
        .subtitle { margin: 0 0 1.25rem; color: var(--muted); font-size: 0.88rem; }

        .field { margin-bottom: 0.95rem; }
        label { display: block; font-size: 0.84rem; font-weight: 600; margin-bottom: 0.3rem; color: #cbd5e1; }
        input[type="email"], input[type="password"] {
            width: 100%;
            padding: 0.62rem 0.8rem;
            border-radius: 8px;
            border: 1px solid rgba(148, 163, 184, 0.28);
            background: rgba(7, 11, 20, 0.8);
            color: var(--text);
            font-size: 0.95rem;
        }
        input:focus {
            outline: 2px solid var(--brand);
            border-color: transparent;
        }

        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.15rem;
            font-size: 0.85rem;
            color: var(--muted);
        }
        .remember-row label {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            margin: 0;
            font-weight: 500;
            cursor: pointer;
        }

        .btn-submit {
            width: 100%;
            padding: 0.68rem 1rem;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, #0ea5e9, #38bdf8);
            color: #06131f;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
        }
        .btn-submit:hover { filter: brightness(1.06); }

        .alert-err {
            padding: 0.65rem 0.8rem;
            border-radius: 8px;
            border: 1px solid rgba(239, 68, 68, 0.55);
            background: rgba(127, 29, 29, 0.35);
            color: #fca5a5;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }
        .alert-ok {
            padding: 0.65rem 0.8rem;
            border-radius: 8px;
            border: 1px solid rgba(34, 197, 94, 0.55);
            background: rgba(20, 83, 45, 0.35);
            color: #86efac;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }
        .hint {
            margin-top: 1rem;
            padding-top: 0.85rem;
            border-top: 1px solid rgba(148, 163, 184, 0.18);
            font-size: 0.8rem;
            color: var(--muted);
            text-align: center;
        }
        code { color: var(--brand); }
    </style>
</head>
<body>
    <div class="fx-grid" aria-hidden="true"></div>
    <svg class="fx-bg" viewBox="0 0 1440 800" preserveAspectRatio="none" aria-hidden="true">
        <defs>
            <linearGradient id="areaGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.28"/>
                <stop offset="100%" stop-color="#38bdf8" stop-opacity="0"/>
            </linearGradient>
        </defs>
        <!-- Area & Trend Curve -->
        <path d="M0,580 C160,540 240,620 380,500 C510,390 620,460 760,340 C900,220 1040,310 1200,190 C1310,120 1380,150 1440,110 L1440,800 L0,800 Z" fill="url(#areaGrad)"/>
        <path d="M0,580 C160,540 240,620 380,500 C510,390 620,460 760,340 C900,220 1040,310 1200,190 C1310,120 1380,150 1440,110" fill="none" stroke="#38bdf8" stroke-width="2.5"/>

        <!-- Stylized Forex Candlesticks -->
        <g stroke-width="2">
            <line x1="140" y1="500" x2="140" y2="610" stroke="#22c55e"/><rect x="132" y="530" width="16" height="50" fill="#22c55e" rx="2"/>
            <line x1="210" y1="530" x2="210" y2="635" stroke="#ef4444"/><rect x="202" y="550" width="16" height="55" fill="#ef4444" rx="2"/>
            <line x1="280" y1="480" x2="280" y2="590" stroke="#22c55e"/><rect x="272" y="505" width="16" height="55" fill="#22c55e" rx="2"/>
            <line x1="350" y1="440" x2="350" y2="540" stroke="#22c55e"/><rect x="342" y="465" width="16" height="45" fill="#22c55e" rx="2"/>
            <line x1="420" y1="410" x2="420" y2="510" stroke="#ef4444"/><rect x="412" y="430" width="16" height="50" fill="#ef4444" rx="2"/>
            <line x1="640" y1="340" x2="640" y2="450" stroke="#22c55e"/><rect x="632" y="365" width="16" height="55" fill="#22c55e" rx="2"/>
            <line x1="710" y1="300" x2="710" y2="400" stroke="#22c55e"/><rect x="702" y="325" width="16" height="45" fill="#22c55e" rx="2"/>
            <line x1="920" y1="220" x2="920" y2="330" stroke="#ef4444"/><rect x="912" y="245" width="16" height="55" fill="#ef4444" rx="2"/>
            <line x1="1080" y1="180" x2="1080" y2="290" stroke="#22c55e"/><rect x="1072" y="205" width="16" height="50" fill="#22c55e" rx="2"/>
            <line x1="1150" y1="145" x2="1150" y2="245" stroke="#22c55e"/><rect x="1142" y="165" width="16" height="55" fill="#22c55e" rx="2"/>
            <line x1="1290" y1="100" x2="1290" y2="205" stroke="#22c55e"/><rect x="1282" y="120" width="16" height="55" fill="#22c55e" rx="2"/>
        </g>
    </svg>

    <main class="login-wrap">
        <div class="ticker-strip" aria-hidden="true">
            <span class="ticker-pill">EUR/USD <span class="up">+0.28%</span></span>
            <span class="ticker-pill">GBP/USD <span class="up">+0.19%</span></span>
            <span class="ticker-pill">USD/JPY <span class="dn">-0.14%</span></span>
            <span class="ticker-pill">XAU/USD <span class="up">+0.62%</span></span>
        </div>

        <section class="card" aria-labelledby="login-heading">
            <div class="logo-row">
                <div class="logo-badge">FX</div>
                <div>
                    <h1 id="login-heading">Forex Day Trader AI</h1>
                </div>
            </div>
            <p class="subtitle">Masuk ke terminal trading kuantitatif &amp; AI Copilot Anda.</p>

            @if(session('status'))
                <div class="alert-ok">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="alert-err">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.post') }}">
                @csrf
                <div class="field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="Masukkan email">
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Masukkan password">
                </div>

                <div class="remember-row">
                    <label>
                        <input type="checkbox" name="remember" value="1">
                        Ingat sesi saya
                    </label>
                    <span>Paper Mode Ready</span>
                </div>

                <button type="submit" class="btn-submit">Masuk ke Terminal</button>
            </form>
        </section>
    </main>
</body>
</html>

