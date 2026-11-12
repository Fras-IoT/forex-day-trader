@extends('layouts.app')
@section('title', 'Entry & Exit Planner')

@section('content')
<div class="grid grid-2">
    <div class="card">
        <h2>Buat Trade Plan (Entry &amp; Exit)</h2>
        <p class="muted">Tentukan Stop Loss dan Take Profit sebelum entry. Mendukung Buy (Long) dan Sell (Short).</p>
        <form method="POST" action="{{ route('plans.store') }}">
            @csrf
            <div class="form-row">
                <div>
                    <label for="pair">Currency Pair</label>
                    <select id="pair" name="pair">
                        @foreach($pairs as $p)
                            <option value="{{ $p }}" {{ ($prefill['pair'] ?? '') === $p ? 'selected' : '' }}>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="side">Side (Direction)</label>
                    <select id="side" name="side">
                        <option value="buy" {{ ($prefill['side'] ?? '') === 'buy' ? 'selected' : '' }}>BUY (Long)</option>
                        <option value="sell" {{ ($prefill['side'] ?? '') === 'sell' ? 'selected' : '' }}>SELL (Short)</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label for="setup">Setup Name</label>
                    <input id="setup" name="setup" type="text" value="{{ $prefill['setup'] ?? 'Session Range Breakout' }}" required>
                </div>
                <div>
                    <label for="entry_price">Entry Price (kosongkan = market price)</label>
                    <input id="entry_price" name="entry_price" type="number" step="any" value="{{ $prefill['entry_price'] ?? '' }}" placeholder="Auto latest price">
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label for="stop_rule">Stop Loss Rule</label>
                    <select id="stop_rule" name="stop_rule">
                        <option value="atr">Auto ATR(14) Multiplier (e.g. 1.5x ATR)</option>
                        <option value="pips">Fixed Pips (e.g. 20 pips)</option>
                        <option value="price">Exact Price</option>
                    </select>
                </div>
                <div>
                    <label for="stop_value">Stop Value (ATR mult / pips / harga)</label>
                    <input id="stop_value" name="stop_value" type="number" step="any" value="1.5">
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label for="target_rule">Take Profit Rule</label>
                    <select id="target_rule" name="target_rule">
                        <option value="2R" selected>2R (2x Risk)</option>
                        <option value="1R">1R (1x Risk)</option>
                        <option value="1.5R">1.5R</option>
                        <option value="3R">3R</option>
                        <option value="price">Exact Price</option>
                    </select>
                </div>
                <div>
                    <label for="target_value">Target Price (jika Exact Price)</label>
                    <input id="target_value" name="target_value" type="number" step="any" placeholder="Opsional">
                </div>
            </div>

            <button class="btn btn-primary" type="submit">Simpan Plan &amp; Lanjut ke Time Machine</button>
        </form>
    </div>

    <div class="card">
        <h2>Daftar Trade Plans (MongoDB)</h2>
        @if($plans->count() > 0)
            <div tabindex="0" role="region" aria-labelledby="plans-cap" class="table-wrapper">
                <table>
                    <caption id="plans-cap">Saved Trade Plans</caption>
                    <thead>
                        <tr><th>Pair</th><th>Side</th><th>Entry</th><th>SL</th><th>TP</th><th>R:R</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        @foreach($plans as $plan)
                        <tr>
                            <th>{{ $plan->pair }}</th>
                            <td data-label="Side"><span class="badge {{ $plan->side === 'buy' ? 'badge-green' : 'badge-red' }}">{{ strtoupper($plan->side) }}</span></td>
                            <td data-label="Entry" class="mono">{{ $plan->entry_price }}</td>
                            <td data-label="SL" class="mono">{{ $plan->stop_loss }} ({{ $plan->risk_pips }}p)</td>
                            <td data-label="TP" class="mono">{{ $plan->take_profit }} ({{ $plan->reward_pips }}p)</td>
                            <td data-label="R:R" class="mono">{{ $plan->reward_risk }}R</td>
                            <td data-label="Actions" style="display:flex; gap:0.3rem; flex-wrap:wrap;">
                                <a class="btn btn-sm btn-primary" href="{{ route('timemachine.index', ['plan_id' => (string)$plan->_id]) }}">Time Machine</a>
                                <a class="btn btn-sm" href="{{ route('broker.index', ['plan_id' => (string)$plan->_id]) }}">Risk Check</a>
                                <form method="POST" action="{{ route('plans.analyze', (string)$plan->_id) }}" style="display:inline;">
                                    @csrf
                                    <button class="btn btn-sm" type="submit">AI Review</button>
                                </form>
                            </td>
                        </tr>
                        @if($plan->ai_analysis)
                        <tr>
                            <td colspan="7"><div class="ai-box">{{ $plan->ai_analysis }}</div></td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="muted">Belum ada trade plan tersimpan.</p>
        @endif
    </div>
</div>
@endsection

