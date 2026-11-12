@extends('layouts.app')
@section('title', 'Time Machine')

@section('content')
<div class="card" style="margin-bottom: 1rem;">
    <h2>Forex Time Machine (10,000 Monte Carlo Replays)</h2>
    <p class="muted">Mensimulasikan sisa sesi ribuan kali dari bar 5-menit historis pasangan mata uang ini sebelum Anda mempertaruhkan uang.</p>
    <form method="POST" action="{{ route('timemachine.run') }}">
        @csrf
        <div class="form-row">
            <div>
                <label for="trade_plan_id">Pilih Trade Plan</label>
                <select id="trade_plan_id" name="trade_plan_id" required>
                    @foreach($plans as $p)
                        <option value="{{ (string)$p->_id }}" {{ ($activePlan && (string)$activePlan->_id === (string)$p->_id) ? 'selected' : '' }}>
                            {{ $p->pair }} ({{ strtoupper($p->side) }}) @ {{ $p->entry_price }} | SL {{ $p->stop_loss }} | TP {{ $p->take_profit }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="replays">Jumlah Replay</label>
                <input id="replays" name="replays" type="number" value="10000" min="1000" max="20000">
            </div>
            <div>
                <label for="seed">Random Seed (Reproducible)</label>
                <input id="seed" name="seed" type="number" value="19" min="1">
            </div>
        </div>
        <button class="btn btn-primary" type="submit" {{ $plans->isEmpty() ? 'disabled' : '' }}>Jalankan 10,000 Simulasi</button>
    </form>
</div>

@if($sim)
<div class="grid grid-4" style="margin-bottom: 1rem;">
    <div class="card">
        <div class="muted">Target First</div>
        <div style="font-size:1.6rem; font-weight:700; color:var(--green);" class="mono">{{ $sim->target_first_pct }}%</div>
        <div class="muted">Break-even needed: {{ $sim->breakeven_win_pct }}%</div>
    </div>
    <div class="card">
        <div class="muted">Stop First (Conservative)</div>
        <div style="font-size:1.6rem; font-weight:700; color:var(--red);" class="mono">{{ $sim->stop_first_pct }}%</div>
        <div class="muted">Still Open: {{ $sim->neither_pct }}%</div>
    </div>
    <div class="card">
        <div class="muted">Expected R / Trade</div>
        <div style="font-size:1.6rem; font-weight:700;" class="mono">{{ $sim->expected_r }}R</div>
        <div class="muted">Finished Green: {{ $sim->green_pct }}%</div>
    </div>
    <div class="card">
        <div class="muted">System Verdict</div>
        <div style="font-size:1.1rem; font-weight:700; margin-top:0.3rem;">{{ $sim->verdict }}</div>
        <div class="muted">Seed {{ $sim->seed }} | {{ number_format($sim->replays) }} runs</div>
    </div>
</div>

<div class="grid grid-2" style="margin-bottom: 1rem;">
    <div class="card mc-hud-card">
        <div class="mc-hud-header">
            <div>
                <div class="mc-hud-badge">
                    <span class="mc-pulse-dot" aria-hidden="true"></span>
                    MONTE CARLO ENGINE
                </div>
                <h3 style="margin:0.25rem 0 0; color:#f1f5f9;">Fan Chart &amp; Ensemble Paths (5th – 95th Percentile)</h3>
            </div>
            <div class="mc-hud-actions">
                <span class="mc-meta-pill mono">{{ number_format($sim->replays) }} PATHS &bull; SEED {{ $sim->seed }}</span>
                <button type="button" id="mc-replay-btn" class="mc-replay-btn" title="Putar ulang animasi simulasi">&#x21bb; Replay</button>
            </div>
        </div>

        <div class="mc-canvas-wrap">
            <canvas id="mc-fan-canvas" aria-label="Grafik Monte Carlo Fan Chart untuk {{ $sim->pair }}"></canvas>
            <div id="mc-tooltip" class="mc-tooltip" hidden></div>
        </div>

        <div class="mc-legend">
            <span><i style="background:#22c55e;"></i> TP {{ $sim->take_profit }}</span>
            <span><i style="background:#38bdf8; box-shadow:0 0 8px #38bdf8;"></i> P50 Median</span>
            <span><i style="background:rgba(56,189,248,0.35); border:1px solid rgba(56,189,248,0.7);"></i> P25–P75 IQR</span>
            <span><i style="background:rgba(56,189,248,0.14); border:1px dashed rgba(56,189,248,0.45);"></i> P5–P95 Fan</span>
            <span><i style="background:#ef4444;"></i> SL {{ $sim->stop_loss }}</span>
            <span><i style="background:linear-gradient(90deg,#22c55e,#38bdf8,#ef4444);"></i> Sample Replays</span>
        </div>

        <div class="mc-dist-wrap" aria-label="Distribusi hasil akhir {{ number_format($sim->replays) }} simulasi">
            <div class="mc-dist-labels mono">
                <span style="color:#4ade80;">TP Hit: {{ $sim->target_first_pct }}%</span>
                <span style="color:#38bdf8;">Open: {{ $sim->neither_pct }}%</span>
                <span style="color:#f87171;">SL Hit: {{ $sim->stop_first_pct }}%</span>
            </div>
            <div class="mc-dist-bar">
                <div style="width:{{ $sim->target_first_pct }}%; background:linear-gradient(90deg,#16a34a,#4ade80);"></div>
                <div style="width:{{ $sim->neither_pct }}%; background:#38bdf8;"></div>
                <div style="width:{{ $sim->stop_first_pct }}%; background:linear-gradient(90deg,#ef4444,#b91c1c);"></div>
            </div>
        </div>

        <details class="mc-a11y-table">
            <summary>Lihat tabel persentil simulasi (aksesibilitas)</summary>
            <table>
                <thead>
                    <tr>
                        <th>Bar</th>
                        <th>P05</th>
                        <th>P25</th>
                        <th>P50</th>
                        <th>P75</th>
                        <th>P95</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sim->fan_series ?? [] as $pt)
                    <tr>
                        <td>{{ $pt['bar'] }}</td>
                        <td>{{ $pt['p05'] }}</td>
                        <td>{{ $pt['p25'] ?? '-' }}</td>
                        <td>{{ $pt['p50'] }}</td>
                        <td>{{ $pt['p75'] ?? '-' }}</td>
                        <td>{{ $pt['p95'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    </div>

    <style>
        .mc-hud-card {
            background: radial-gradient(circle at top right, rgba(56, 189, 248, 0.12), transparent 55%), #070d1a;
            border: 1px solid rgba(56, 189, 248, 0.28);
            color: #e2e8f0;
            box-shadow: 0 12px 32px rgba(2, 6, 23, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.06);
            isolation: isolate;
        }
        .mc-hud-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 0.75rem;
        }
        .mc-hud-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            color: #38bdf8;
        }
        .mc-pulse-dot {
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: #22d3ee;
            box-shadow: 0 0 8px #22d3ee;
            animation: mcPulse 1.8s ease-in-out infinite;
        }
        @keyframes mcPulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.45; transform: scale(0.8); }
        }
        .mc-hud-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .mc-meta-pill {
            font-size: 0.72rem;
            padding: 0.25rem 0.55rem;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(56, 189, 248, 0.3);
            color: #93c5fd;
        }
        .mc-replay-btn {
            background: rgba(56, 189, 248, 0.14);
            border: 1px solid rgba(56, 189, 248, 0.4);
            color: #e0f2fe;
            border-radius: 6px;
            padding: 0.25rem 0.6rem;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
        }
        .mc-replay-btn:hover { background: rgba(56, 189, 248, 0.28); }
        .mc-canvas-wrap {
            position: relative;
            width: 100%;
            height: 270px;
            border-radius: 10px;
            overflow: hidden;
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.92), rgba(6, 11, 22, 0.98));
            border: 1px solid rgba(56, 189, 248, 0.18);
        }
        #mc-fan-canvas {
            width: 100%;
            height: 100%;
            display: block;
            cursor: crosshair;
        }
        .mc-tooltip {
            position: absolute;
            pointer-events: none;
            background: rgba(8, 15, 30, 0.92);
            border: 1px solid rgba(56, 189, 248, 0.45);
            border-radius: 8px;
            padding: 0.45rem 0.6rem;
            font-size: 0.72rem;
            line-height: 1.45;
            color: #f8fafc;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(6px);
            z-index: 5;
            white-space: nowrap;
        }
        .mc-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 0.65rem;
            font-size: 0.73rem;
            color: #94a3b8;
        }
        .mc-legend span {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .mc-legend i {
            display: inline-block;
            width: 12px;
            height: 6px;
            border-radius: 2px;
        }
        .mc-dist-wrap { margin-top: 0.75rem; }
        .mc-dist-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.73rem;
            margin-bottom: 0.28rem;
        }
        .mc-dist-bar {
            display: flex;
            height: 7px;
            border-radius: 999px;
            overflow: hidden;
            background: rgba(148, 163, 184, 0.15);
        }
        .mc-a11y-table {
            margin-top: 0.65rem;
            font-size: 0.75rem;
            color: #94a3b8;
        }
        .mc-a11y-table summary { cursor: pointer; }
        .mc-a11y-table table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.4rem;
            font-size: 0.72rem;
        }
        .mc-a11y-table th, .mc-a11y-table td {
            border: 1px solid rgba(148, 163, 184, 0.2);
            padding: 0.2rem 0.4rem;
            text-align: right;
        }
        @media (prefers-reduced-motion: reduce) {
            .mc-pulse-dot { animation: none; }
        }
    </style>

    <script>
    (() => {
        const canvas = document.getElementById('mc-fan-canvas');
        const tooltip = document.getElementById('mc-tooltip');
        const replayBtn = document.getElementById('mc-replay-btn');
        if (!canvas) return;

        const series = @json($sim->fan_series ?? []);
        let samplePaths = @json($sim->sample_paths ?? []);
        const tp = {{ (float) $sim->take_profit }};
        const sl = {{ (float) $sim->stop_loss }};
        const entry = {{ (float) $sim->entry_price }};
        const decimals = {{ str_contains($sim->pair, 'JPY') ? 3 : 5 }};

        if (!series.length) return;

        // Fallback ensemble paths for older SimulationRun documents without sample_paths
        if (!samplePaths.length) {
            for (let k = 0; k < 28; k++) {
                const ratio = (k / 27);
                const pts = series.map((pt, idx) => {
                    const wave = Math.sin((idx + 1) * (k + 1) * 0.45) * 0.18;
                    const q = Math.min(1, Math.max(0, ratio + wave));
                    const val = pt.p05 + (pt.p95 - pt.p05) * q;
                    return idx === 0 ? entry : val;
                });
                const last = pts[pts.length - 1];
                const outcome = Math.abs(last - tp) < 1e-6 ? 'target' : (Math.abs(last - sl) < 1e-6 ? 'stop' : 'neither');
                samplePaths.push({ outcome, points: pts });
            }
        }

        const vals = [tp, sl, entry];
        series.forEach(pt => {
            vals.push(pt.p05, pt.p95);
        });
        samplePaths.forEach(p => (p.points || []).forEach(v => vals.push(v)));

        const rawMin = Math.min(...vals);
        const rawMax = Math.max(...vals);
        const padSpan = Math.max(1e-6, (rawMax - rawMin) * 0.08);
        const minY = rawMin - padSpan;
        const maxY = rawMax + padSpan;
        const spanY = maxY - minY;

        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let animProgress = reducedMotion ? 1 : 0;
        let hoverIdx = null;
        let rafId = null;

        function render() {
            const rect = canvas.getBoundingClientRect();
            if (!rect.width || !rect.height) return;
            const dpr = window.devicePixelRatio || 1;
            canvas.width = Math.round(rect.width * dpr);
            canvas.height = Math.round(rect.height * dpr);

            const ctx = canvas.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

            const w = rect.width;
            const h = rect.height;
            const padL = 14, padR = 72, padT = 18, padB = 26;
            const plotW = w - padL - padR;
            const plotH = h - padT - padB;
            const n = series.length;

            const mapX = i => padL + (i / Math.max(1, n - 1)) * plotW;
            const mapY = v => padT + plotH - ((v - minY) / spanY) * plotH;

            ctx.clearRect(0, 0, w, h);

            // Cyber grid
            ctx.strokeStyle = 'rgba(148, 163, 184, 0.1)';
            ctx.lineWidth = 1;
            for (let g = 0; g <= 4; g++) {
                const gy = padT + (g / 4) * plotH;
                ctx.beginPath();
                ctx.moveTo(padL, gy);
                ctx.lineTo(w - padR, gy);
                ctx.stroke();

                const priceLbl = (maxY - (g / 4) * spanY).toFixed(decimals);
                ctx.fillStyle = 'rgba(148, 163, 184, 0.65)';
                ctx.font = '10px ui-monospace, SFMono-Regular, Menlo, monospace';
                ctx.fillText(priceLbl, w - padR + 6, gy + 3);
            }

            const maxStepIdx = Math.max(1, Math.floor((n - 1) * animProgress));

            // Vertical time markers
            ctx.fillStyle = 'rgba(148, 163, 184, 0.65)';
            ctx.font = '10px ui-monospace, SFMono-Regular, Menlo, monospace';
            for (let i = 0; i < n; i += Math.max(1, Math.floor(n / 5))) {
                const gx = mapX(i);
                ctx.strokeStyle = 'rgba(148, 163, 184, 0.08)';
                ctx.beginPath();
                ctx.moveTo(gx, padT);
                ctx.lineTo(gx, padT + plotH);
                ctx.stroke();
                const mins = (series[i].bar || (i * 3)) * 5;
                ctx.fillText('+' + mins + 'm', Math.max(padL, gx - 12), h - 8);
            }

            // Outer 5th - 95th fan
            const gradOuter = ctx.createLinearGradient(0, padT, 0, padT + plotH);
            gradOuter.addColorStop(0, 'rgba(34, 211, 238, 0.18)');
            gradOuter.addColorStop(0.5, 'rgba(56, 189, 248, 0.08)');
            gradOuter.addColorStop(1, 'rgba(239, 68, 68, 0.14)');
            ctx.fillStyle = gradOuter;
            ctx.beginPath();
            for (let i = 0; i <= maxStepIdx; i++) {
                const x = mapX(i), y = mapY(series[i].p95);
                i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
            }
            for (let i = maxStepIdx; i >= 0; i--) {
                ctx.lineTo(mapX(i), mapY(series[i].p05));
            }
            ctx.closePath();
            ctx.fill();

            // Inner 25th - 75th IQR fan
            ctx.fillStyle = 'rgba(56, 189, 248, 0.16)';
            ctx.beginPath();
            for (let i = 0; i <= maxStepIdx; i++) {
                const x = mapX(i), y = mapY(series[i].p75 ?? series[i].p95);
                i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
            }
            for (let i = maxStepIdx; i >= 0; i--) {
                ctx.lineTo(mapX(i), mapY(series[i].p25 ?? series[i].p05));
            }
            ctx.closePath();
            ctx.fill();

            // Real Monte Carlo sample paths
            samplePaths.forEach(path => {
                const pts = path.points || [];
                if (!pts.length) return;
                const limit = Math.min(pts.length - 1, maxStepIdx);
                ctx.beginPath();
                for (let i = 0; i <= limit; i++) {
                    const x = mapX(i), y = mapY(pts[i]);
                    i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
                }
                if (path.outcome === 'target') {
                    ctx.strokeStyle = 'rgba(74, 222, 128, 0.38)';
                } else if (path.outcome === 'stop') {
                    ctx.strokeStyle = 'rgba(248, 113, 113, 0.25)';
                } else {
                    ctx.strokeStyle = 'rgba(56, 189, 248, 0.35)';
                }
                ctx.lineWidth = 1;
                ctx.stroke();
            });

            // TP / Entry / SL threshold lines
            const drawLevel = (val, color, label, dashed) => {
                const y = mapY(val);
                ctx.save();
                ctx.strokeStyle = color;
                ctx.lineWidth = 1.3;
                ctx.setLineDash(dashed);
                ctx.beginPath();
                ctx.moveTo(padL, y);
                ctx.lineTo(w - padR, y);
                ctx.stroke();
                ctx.restore();

                ctx.fillStyle = color;
                ctx.font = 'bold 10px ui-monospace, SFMono-Regular, Menlo, monospace';
                ctx.fillText(label, padL + 6, Math.max(padT + 10, Math.min(padT + plotH - 4, y - 4)));
            };
            drawLevel(tp, '#4ade80', 'TP ' + tp.toFixed(decimals), [5, 4]);
            drawLevel(entry, 'rgba(226, 232, 240, 0.5)', 'ENTRY ' + entry.toFixed(decimals), [2, 3]);
            drawLevel(sl, '#f87171', 'SL ' + sl.toFixed(decimals), [5, 4]);

            // Glowing P50 Median Line
            ctx.save();
            ctx.strokeStyle = '#38bdf8';
            ctx.lineWidth = 2.6;
            ctx.shadowColor = '#38bdf8';
            ctx.shadowBlur = 10;
            ctx.beginPath();
            for (let i = 0; i <= maxStepIdx; i++) {
                const x = mapX(i), y = mapY(series[i].p50);
                i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
            }
            ctx.stroke();
            ctx.restore();

            // Interactive crosshair
            if (hoverIdx !== null && series[hoverIdx]) {
                const hx = mapX(hoverIdx);
                const hy = mapY(series[hoverIdx].p50);
                ctx.save();
                ctx.strokeStyle = 'rgba(248, 250, 252, 0.45)';
                ctx.setLineDash([3, 3]);
                ctx.beginPath();
                ctx.moveTo(hx, padT);
                ctx.lineTo(hx, padT + plotH);
                ctx.stroke();
                ctx.restore();

                ctx.fillStyle = '#f8fafc';
                ctx.beginPath();
                ctx.arc(hx, hy, 4, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        function animate() {
            if (animProgress < 1) {
                animProgress = Math.min(1, animProgress + 0.045);
                render();
                rafId = requestAnimationFrame(animate);
            } else {
                render();
            }
        }

        if (replayBtn) {
            replayBtn.addEventListener('click', () => {
                if (rafId) cancelAnimationFrame(rafId);
                animProgress = reducedMotion ? 1 : 0;
                animate();
            });
        }

        canvas.addEventListener('mousemove', e => {
            const rect = canvas.getBoundingClientRect();
            const padL = 14, padR = 72;
            const plotW = Math.max(1, rect.width - padL - padR);
            const relX = Math.min(plotW, Math.max(0, e.clientX - rect.left - padL));
            const idx = Math.round((relX / plotW) * (series.length - 1));
            hoverIdx = idx;
            const pt = series[idx];
            if (pt && tooltip) {
                const mins = (pt.bar || (idx * 3)) * 5;
                tooltip.hidden = false;
                tooltip.innerHTML = `
                    <div style="color:#38bdf8;font-weight:700;margin-bottom:2px;">Bar #${pt.bar ?? idx * 3} (+${mins}m)</div>
                    <div>P95: <span class="mono">${Number(pt.p95).toFixed(decimals)}</span></div>
                    <div>P75: <span class="mono">${Number(pt.p75 ?? pt.p95).toFixed(decimals)}</span></div>
                    <div style="color:#7dd3fc;font-weight:700;">P50: <span class="mono">${Number(pt.p50).toFixed(decimals)}</span></div>
                    <div>P25: <span class="mono">${Number(pt.p25 ?? pt.p05).toFixed(decimals)}</span></div>
                    <div>P05: <span class="mono">${Number(pt.p05).toFixed(decimals)}</span></div>
                `;
                const tx = Math.min(rect.width - 145, Math.max(8, e.clientX - rect.left + 12));
                const ty = Math.max(8, e.clientY - rect.top - 95);
                tooltip.style.left = tx + 'px';
                tooltip.style.top = ty + 'px';
            }
            render();
        });

        canvas.addEventListener('mouseleave', () => {
            hoverIdx = null;
            if (tooltip) tooltip.hidden = true;
            render();
        });

        window.addEventListener('resize', render);
        animate();
    })();
    </script>

    <div class="card">
        <h3>Historical Backtest &amp; Gemini Verdict</h3>
        @php $hist = $sim->history_stats ?? ['fires' => 0, 'wins' => 0, 'losses' => 0, 'neither' => 0]; @endphp
        <p>Aturan yang sama dijalankan pada <strong>{{ $sim->sessions_used }}</strong> sesi historis 5m terakhir:
            terpicu <strong>{{ $hist['fires'] }}x</strong> &rarr;
            <span style="color:var(--green)">{{ $hist['wins'] }} Win</span>,
            <span style="color:var(--red)">{{ $hist['losses'] }} Loss</span>,
            {{ $hist['neither'] }} Neither.
        </p>

        <div style="display:flex; gap:0.5rem; flex-wrap:wrap; margin-bottom:0.85rem;">
            <form method="POST" action="{{ route('timemachine.analyze', (string)$sim->_id) }}">
                @csrf
                <button class="btn btn-primary" type="submit">Minta Gemini AI Verdict</button>
            </form>
            <a class="btn" href="{{ route('broker.index', ['plan_id' => $sim->trade_plan_id]) }}">Lanjut ke Risk Check &amp; Sizing &rarr;</a>
        </div>

        @if($sim->ai_verdict)
            <div class="ai-box">{{ $sim->ai_verdict }}</div>
        @endif
    </div>
</div>
@endif
@endsection

