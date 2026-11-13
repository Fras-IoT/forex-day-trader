@extends('layouts.app')
@section('title', 'Chart Scanner')

@section('content')
<div class="card" style="margin-bottom: 1rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
        <div>
            <h2 style="margin:0;">Forex Chart Scanner <span id="live-price-status" class="badge badge-green" style="font-size:0.72rem; margin-left:0.4rem;">Live 5s</span></h2>
            <div class="muted">Memindai 11 pair Forex (Asian/Session Range Breakout, 20D Breakout, EMA 50/200 Trend, RSI Bounce, MA20 Reclaim).</div>
        </div>
        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
            <form method="POST" action="{{ route('scanner.run') }}">
                @csrf
                <input type="hidden" name="mode" value="intraday">
                <button class="btn btn-primary" type="submit">Scan Setups Only</button>
            </form>
            <form method="POST" action="{{ route('scanner.run') }}">
                @csrf
                <input type="hidden" name="mode" value="all">
                <button class="btn" type="submit">Scan All Pairs</button>
            </form>
            <form method="POST" action="{{ route('scanner.analyze') }}">
                @csrf
                <button class="btn" type="submit">Analisis Gemini AI</button>
            </form>
        </div>
    </div>
</div>

@if($latestScan && $latestScan->ai_summary)
    <div class="card" style="margin-bottom: 1rem;">
        <h3>Gemini AI Scanner Brief</h3>
        <div class="ai-box">{!! \App\Services\GeminiService::toHtml($latestScan->ai_summary) !!}</div>
    </div>
@endif

<div class="card">
    @if($latestScan && !empty($latestScan->hits))
        <div tabindex="0" role="region" aria-labelledby="scan-table-cap" class="table-wrapper">
            <table>
                <caption id="scan-table-cap">Session: {{ $latestScan->session }} | Source: {{ str_replace(['mt5_finex_live', 'yahoo_live', 'synthetic_offline'], ['MT5 Finex Live', 'Live Market Feed', 'Offline Simulation'], $latestScan->data_source) }} | Scanned: {{ $latestScan->pairs_scanned }} pairs</caption>
                <thead>
                    <tr>
                        <th>Pair</th>
                        <th>Side</th>
                        <th>Setup</th>
                        <th>EMA Trend</th>
                        <th>Price</th>
                        <th>Asian/Sess Range</th>
                        <th>RSI(14)</th>
                        <th>ATR (pips)</th>
                        <th>Score</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($latestScan->hits as $h)
                    <tr>
                        <th>{{ $h['pair'] }}</th>
                        <td data-label="Side"><span class="badge {{ $h['side'] === 'buy' ? 'badge-green' : 'badge-red' }}">{{ strtoupper($h['side']) }}</span></td>
                        <td data-label="Setup">{{ $h['setup'] }}</td>
                        <td data-label="EMA Trend">
                            @php $tb = $h['trend_bias'] ?? 'Neutral'; @endphp
                            <span class="badge {{ $tb === 'Bullish' ? 'badge-green' : ($tb === 'Bearish' ? 'badge-red' : '') }}">{{ $tb }}</span>
                        </td>
                        <td data-label="Price" class="mono" data-live-pair="{{ $h['pair'] }}">{{ $h['price'] }}</td>
                        <td data-label="Range" class="mono">{{ $h['range_low'] ?? '-' }} – {{ $h['range_high'] ?? '-' }}</td>
                        <td data-label="RSI(14)" class="mono">{{ $h['rsi14'] }}</td>
                        <td data-label="ATR" class="mono">{{ $h['atr_pips'] }}</td>
                        <td data-label="Score" class="mono"><strong>{{ $h['score'] }}</strong></td>
                        <td data-label="Action">
                            <a class="btn btn-sm btn-primary" href="{{ route('plans.index', ['pair' => $h['pair'], 'side' => $h['side'], 'setup' => $h['setup'], 'entry_price' => $h['price']]) }}">Buat Plan</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="muted">Belum ada data scan. Klik <strong>Scan Setups Only</strong> atau <strong>Scan All Pairs</strong> di atas.</p>
    @endif
</div>
@endsection

