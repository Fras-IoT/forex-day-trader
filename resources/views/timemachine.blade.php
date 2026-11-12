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
    <div class="card">
        <h3>Monte Carlo Fan Chart (5th - 95th Percentile)</h3>
        @php
            $series = $sim->fan_series ?? [];
            $allVals = [];
            foreach ($series as $pt) {
                $allVals[] = $pt['p05'];
                $allVals[] = $pt['p95'];
            }
            $allVals[] = (float) $sim->stop_loss;
            $allVals[] = (float) $sim->take_profit;
            $minY = !empty($allVals) ? min($allVals) : 0;
            $maxY = !empty($allVals) ? max($allVals) : 1;
            $spanY = max(1e-6, $maxY - $minY);
            $w = 560; $h = 220; $pad = 24;
            $mapX = fn($idx) => $pad + ($idx / max(1, count($series) - 1)) * ($w - 2 * $pad);
            $mapY = fn($val) => $h - $pad - (($val - $minY) / $spanY) * ($h - 2 * $pad);
            $p50Pts = []; $p95Pts = []; $p05Pts = [];
            foreach ($series as $i => $pt) {
                $x = round($mapX($i), 1);
                $p50Pts[] = "{$x}," . round($mapY($pt['p50']), 1);
                $p95Pts[] = "{$x}," . round($mapY($pt['p95']), 1);
                $p05Pts[] = "{$x}," . round($mapY($pt['p05']), 1);
            }
            $bandPts = array_merge($p95Pts, array_reverse($p05Pts));
            $yTp = round($mapY((float)$sim->take_profit), 1);
            $ySl = round($mapY((float)$sim->stop_loss), 1);
            $yEntry = round($mapY((float)$sim->entry_price), 1);
        @endphp
        <svg viewBox="0 0 {{ $w }} {{ $h }}" style="width:100%; height:auto; background:var(--bg); border-radius:8px; border:1px solid var(--border);">
            <polygon points="{{ implode(' ', $bandPts) }}" fill="currentColor" opacity="0.12" style="color:var(--brand);" />
            <line x1="{{ $pad }}" y1="{{ $yTp }}" x2="{{ $w - $pad }}" y2="{{ $yTp }}" stroke="var(--green)" stroke-dasharray="4 3" stroke-width="1.5" />
            <line x1="{{ $pad }}" y1="{{ $yEntry }}" x2="{{ $w - $pad }}" y2="{{ $yEntry }}" stroke="var(--muted)" stroke-dasharray="2 2" stroke-width="1" />
            <line x1="{{ $pad }}" y1="{{ $ySl }}" x2="{{ $w - $pad }}" y2="{{ $ySl }}" stroke="var(--red)" stroke-dasharray="4 3" stroke-width="1.5" />
            <polyline fill="none" stroke="var(--brand)" stroke-width="2.2" points="{{ implode(' ', $p50Pts) }}" />
            <text x="{{ $pad + 4 }}" y="{{ max(14, $yTp - 4) }}" fill="var(--green)" font-size="10">TP {{ $sim->take_profit }}</text>
            <text x="{{ $pad + 4 }}" y="{{ min($h - 6, $ySl + 12) }}" fill="var(--red)" font-size="10">SL {{ $sim->stop_loss }}</text>
        </svg>
    </div>

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

