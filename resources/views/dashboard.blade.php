@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
@if(!$geminiConfigured)
    <div class="alert-banner alert-warn">
        <strong>Gemini API Token belum diisi.</strong> Semua fitur kuantitatif (Scanner, Entry/Exit, Time Machine 10.000x, Risk Guard, Paper Broker) sudah siap dipakai.
        <a href="{{ route('settings.index') }}">Masukkan Gemini Token di Settings</a> saat Anda siap mengaktifkan analisis AI.
    </div>
@endif

<div class="grid grid-4" style="margin-bottom: 1rem;">
    <div class="card">
        <div class="muted">Paper Equity</div>
        <div style="font-size: 1.5rem; font-weight: 700;" class="mono">${{ number_format($effectiveBalance, 2) }}</div>
        <div class="muted">Realized PnL: <span class="{{ $totalRealizedPnl >= 0 ? 'badge-green' : 'badge-red' }}">{{ $totalRealizedPnl >= 0 ? '+' : '' }}${{ number_format($totalRealizedPnl, 2) }}</span></div>
    </div>
    <div class="card">
        <div class="muted">Active FX Sessions</div>
        <div style="margin-top: 0.35rem; display: flex; flex-wrap: wrap; gap: 0.3rem;">
            @foreach($sessions['sessions'] as $s)
                <span class="badge {{ $s['active'] ? 'badge-green' : '' }}">{{ $s['name'] }}</span>
            @endforeach
        </div>
        <div class="muted" style="margin-top: 0.35rem;">{{ $sessions['wib_time'] }}</div>
    </div>
    <div class="card">
        <div class="muted">Open Paper Positions</div>
        <div style="font-size: 1.5rem; font-weight: 700;" class="mono">{{ $openOrders->count() }}</div>
        <div class="muted">Max trades/day: {{ $settings->max_trades_per_day }} | Max loss: ${{ $settings->max_loss_per_trade_usd }}</div>
    </div>
    <div class="card">
        <div class="muted">Active Price Alerts</div>
        <div style="font-size: 1.5rem; font-weight: 700;" class="mono">{{ $activeAlerts->count() }}</div>
        <div class="muted">Flat-by cutoff: {{ $settings->flat_by_utc }} UTC</div>
    </div>
</div>

<div class="card" style="margin-bottom: 1rem;">
    <h2>Workflow Trading Forex (Pipeline)</h2>
    <p class="muted" style="margin-top: 0;">Ikuti alur disiplin seperti <code>day-trader-skill</code> sebelum mengambil risiko:</p>
    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
        <a class="btn btn-primary" href="{{ route('scanner.index') }}">1. Chart Scanner</a>
        <span>&rarr;</span>
        <a class="btn" href="{{ route('news.index') }}">2. News Monitor</a>
        <span>&rarr;</span>
        <a class="btn" href="{{ route('plans.index') }}">3. Entry &amp; Exit Plan</a>
        <span>&rarr;</span>
        <a class="btn" href="{{ route('timemachine.index') }}">4. Time Machine (10,000x)</a>
        <span>&rarr;</span>
        <a class="btn" href="{{ route('broker.index') }}">5. Risk Check &amp; Paper Broker</a>
        <span>&rarr;</span>
        <a class="btn" href="{{ route('alerts.index') }}">6. Alerts</a>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.6rem;">
            <h3 style="margin:0;">Latest Scanner Hits</h3>
            <form method="POST" action="{{ route('scanner.run') }}">
                @csrf
                <button class="btn btn-sm btn-primary" type="submit">Run Scan</button>
            </form>
        </div>
        @if($latestScan && !empty($latestScan->hits))
            <div tabindex="0" role="region" aria-labelledby="dash-scan-cap" class="table-wrapper">
                <table>
                    <caption id="dash-scan-cap">{{ $latestScan->session }} ({{ $latestScan->data_source }})</caption>
                    <thead>
                        <tr><th>Pair</th><th>Side</th><th>Setup</th><th>Price</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        @foreach(array_slice($latestScan->hits, 0, 6) as $h)
                        <tr>
                            <th>{{ $h['pair'] }}</th>
                            <td data-label="Side"><span class="badge {{ $h['side'] === 'buy' ? 'badge-green' : 'badge-red' }}">{{ strtoupper($h['side']) }}</span></td>
                            <td data-label="Setup">{{ $h['setup'] }}</td>
                            <td data-label="Price" class="mono">{{ $h['price'] }}</td>
                            <td data-label="Action">
                                <a class="btn btn-sm" href="{{ route('plans.index', ['pair' => $h['pair'], 'side' => $h['side'], 'setup' => $h['setup'], 'entry_price' => $h['price']]) }}">Plan</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="muted">Belum ada hasil scan. Klik <strong>Run Scan</strong> untuk memindai 11 pasangan Forex utama.</p>
        @endif
    </div>

    <div class="card">
        <h3>Latest Time Machine &amp; Plans</h3>
        @if($latestSim)
            <p>
                <strong>{{ $latestSim->pair }}</strong> ({{ strtoupper($latestSim->side) }}) &mdash;
                <span class="badge badge-brand">{{ $latestSim->verdict }}</span>
            </p>
            <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:0.75rem;" class="mono">
                <span>Target First: <strong style="color:var(--green)">{{ $latestSim->target_first_pct }}%</strong></span>
                <span>Stop First: <strong style="color:var(--red)">{{ $latestSim->stop_first_pct }}%</strong></span>
                <span>Expected R: <strong>{{ $latestSim->expected_r }}R</strong></span>
            </div>
        @endif

        @if($plans->count() > 0)
            <div tabindex="0" role="region" aria-labelledby="dash-plan-cap" class="table-wrapper">
                <table>
                    <caption id="dash-plan-cap">Recent Trade Plans</caption>
                    <thead><tr><th>Pair</th><th>Side</th><th>Entry</th><th>R:R</th><th>Next</th></tr></thead>
                    <tbody>
                        @foreach($plans as $p)
                        <tr>
                            <th>{{ $p->pair }}</th>
                            <td data-label="Side">{{ strtoupper($p->side) }}</td>
                            <td data-label="Entry" class="mono">{{ $p->entry_price }}</td>
                            <td data-label="R:R" class="mono">{{ $p->reward_risk }}R</td>
                            <td data-label="Next">
                                <a class="btn btn-sm" href="{{ route('timemachine.index', ['plan_id' => (string)$p->_id]) }}">Simulate</a>
                                <a class="btn btn-sm" href="{{ route('broker.index', ['plan_id' => (string)$p->_id]) }}">Size</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="muted">Belum ada trade plan. Buat dari Scanner atau halaman Entry &amp; Exit.</p>
        @endif
    </div>
</div>
@endsection

