@extends('layouts.app')
@section('title', 'Risk & Paper Broker')

@section('content')
<div class="grid grid-2" style="margin-bottom: 1rem;">
    <div class="card">
        <h2>Budget Sizer &amp; Pre-Trade Risk Guard</h2>
        <p class="muted">Menghitung ukuran lot Forex otomatis sesuai batas risiko Anda dan memvalidasi 9 aturan risiko (PASS / BLOCK).</p>
        <form method="POST" action="{{ route('broker.size') }}">
            @csrf
            <div class="form-row">
                <div>
                    <label for="trade_plan_id">Pilih Trade Plan</label>
                    <select id="trade_plan_id" name="trade_plan_id" required>
                        @foreach($plans as $p)
                            <option value="{{ (string)$p->_id }}" {{ ($activePlan && (string)$activePlan->_id === (string)$p->_id) ? 'selected' : '' }}>
                                {{ $p->pair }} ({{ strtoupper($p->side) }}) @ {{ $p->entry_price }} | {{ $p->risk_pips }} pips | {{ $p->reward_risk }}R
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="max_loss_usd">Max Loss / Trade (USD)</label>
                    <input id="max_loss_usd" name="max_loss_usd" type="number" step="1" value="{{ $settings->max_loss_per_trade_usd }}">
                </div>
                <div>
                    <label for="order_type">Order Type</label>
                    <select id="order_type" name="order_type">
                        <option value="market">Market</option>
                        <option value="limit">Limit</option>
                    </select>
                </div>
            </div>
            <button class="btn btn-primary" type="submit" {{ $plans->isEmpty() ? 'disabled' : '' }}>Hitung Lot &amp; Cek Risiko</button>
        </form>
    </div>

    <div class="card">
        <h2>Pending Order Tickets (Butuh Persetujuan Anda)</h2>
        @if($pendingOrders->count() > 0)
            @foreach($pendingOrders as $ord)
                <div style="border:1px solid var(--border); border-radius:8px; padding:0.75rem; margin-bottom:0.75rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                        <div>
                            <span class="badge badge-green">PASS</span>
                            <strong>{{ strtoupper($ord->side) }} {{ $ord->lots }} lot {{ $ord->pair }}</strong>
                            <span class="mono">@ {{ $ord->entry_price }}</span>
                        </div>
                        <div style="display:flex; gap:0.4rem; align-items:center;">
                            <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('confirm-dialog-{{ (string)$ord->_id }}').showModal()">
                                Approve &amp; Eksekusi ke MT5
                            </button>
                            <form method="POST" action="{{ route('broker.destroy', (string)$ord->_id) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus ticket pending ini?')" style="margin:0;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" title="Hapus Ticket Pending">Hapus</button>
                            </form>
                        </div>
                    </div>
                    <div class="muted mono" style="margin-top:0.35rem;">
                        SL: {{ $ord->stop_loss }} | TP: {{ $ord->take_profit }} | Max Loss: ${{ $ord->max_loss_usd }} | R:R {{ $ord->reward_risk }}x
                    </div>
                    <div class="muted mono" style="font-size:0.75rem; margin-top:0.2rem;">
                        Fingerprint: {{ substr($ord->fingerprint, 0, 20) }}...
                    </div>

                    <!-- Native modal dialog per modern-web-guidance -->
                    <dialog id="confirm-dialog-{{ (string)$ord->_id }}" aria-labelledby="dlg-title-{{ (string)$ord->_id }}">
                        <h3 id="dlg-title-{{ (string)$ord->_id }}">Konfirmasi Eksekusi Order (Web &amp; MT5)</h3>
                        <p>Anda akan menyetujui order ini. Robot MT5 akan langsung membuka posisi di MT5 sesuai spesifikasi berikut:</p>
                        <ul class="mono" style="font-size:0.88rem;">
                            <li>Pair: {{ $ord->pair }} ({{ strtoupper($ord->side) }})</li>
                            <li>Size: {{ $ord->lots }} lot ({{ number_format($ord->units) }} units)</li>
                            <li>Entry: {{ $ord->entry_price }} | SL: {{ $ord->stop_loss }} | TP: {{ $ord->take_profit }}</li>
                            <li>Max Loss at Stop: ${{ $ord->max_loss_usd }}</li>
                        </ul>
                        <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1rem;">
                            <form method="dialog">
                                <button class="btn" value="cancel" autofocus>Batal</button>
                            </form>
                            <form method="POST" action="{{ route('broker.execute', (string)$ord->_id) }}">
                                @csrf
                                <input type="hidden" name="confirm_yes" value="YES">
                                <button class="btn btn-primary" type="submit">Ya, Eksekusi ke MT5</button>
                            </form>
                        </div>
                    </dialog>
                </div>
            @endforeach
        @else
            <p class="muted">Tidak ada ticket order yang menunggu persetujuan.</p>
        @endif

        @if(isset($rejectedOrders) && $rejectedOrders->count() > 0)
            <div style="margin-top:1.25rem; border-top:1px dashed var(--border); padding-top:1rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                    <h4 style="color:var(--red); margin:0;">Ticket Ditolak Sistem (Risk Check BLOCK)</h4>
                    <form method="POST" action="{{ route('broker.clear-rejected') }}" onsubmit="return confirm('Hapus semua riwayat ticket yang ditolak sistem?')" style="margin:0;">
                        @csrf
                        <button type="submit" class="btn btn-sm" style="font-size:0.75rem; color:var(--red); border-color:var(--red);">Bersihkan Semua ({{ $rejectedOrders->count() }})</button>
                    </form>
                </div>
                @foreach($rejectedOrders as $rej)
                    <div style="border:1px solid #e53e3e33; background:rgba(229, 62, 62, 0.05); border-radius:8px; padding:0.75rem; margin-bottom:0.6rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <span class="badge badge-red">BLOCKED</span>
                                <strong>{{ strtoupper($rej->side) }} {{ $rej->pair }}</strong>
                                <span class="mono" style="font-size:0.85rem;">(SL: {{ $rej->stop_loss }} / TP: {{ $rej->take_profit }})</span>
                            </div>
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <span class="muted" style="font-size:0.75rem;">{{ $rej->created_at ? $rej->created_at->diffForHumans() : '' }}</span>
                                <form method="POST" action="{{ route('broker.destroy', (string)$rej->_id) }}" style="margin:0;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm" style="padding:0.15rem 0.45rem; font-size:0.72rem; color:var(--red);" title="Hapus ticket ditolak ini">✕ Hapus</button>
                                </form>
                            </div>
                        </div>
                        @if(!empty($rej->risk_checks))
                            <ul style="margin:0.5rem 0 0 1.2rem; font-size:0.82rem;">
                                @foreach($rej->risk_checks as $chk)
                                    @if(($chk['status'] ?? '') === 'BLOCK')
                                        <li style="color:var(--red); font-weight:600;">
                                            ❌ {{ $chk['rule'] ?? 'Risk Rule' }}: <span style="font-weight:normal;">{{ $chk['detail'] ?? '' }}</span>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<div class="card" style="margin-bottom: 1rem;">
    <h3>Open Paper Positions</h3>
    @if($openOrders->count() > 0)
        <div tabindex="0" role="region" aria-labelledby="open-pos-cap" class="table-wrapper">
            <table>
                <caption id="open-pos-cap">Active Paper Positions</caption>
                <thead><tr><th>Pair</th><th>Side</th><th>Lots</th><th>Entry</th><th>Current</th><th>SL / TP</th><th>Action</th></tr></thead>
                <tbody>
                    @foreach($openOrders as $o)
                    <tr>
                        <th>{{ $o->pair }}</th>
                        <td data-label="Side"><span class="badge {{ $o->side === 'buy' ? 'badge-green' : 'badge-red' }}">{{ strtoupper($o->side) }}</span></td>
                        <td data-label="Lots" class="mono">{{ $o->lots }}</td>
                        <td data-label="Entry" class="mono">{{ $o->entry_price }}</td>
                        <td data-label="Current" class="mono" data-live-pair="{{ $o->pair }}">{{ $livePrices[$o->pair] ?? $o->entry_price }}</td>
                        <td data-label="SL/TP" class="mono">{{ $o->stop_loss }} / {{ $o->take_profit }}</td>
                        <td data-label="Action">
                            <form method="POST" action="{{ route('broker.close', (string)$o->_id) }}">
                                @csrf
                                <button class="btn btn-sm btn-danger" type="submit">Close Position</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="muted">Belum ada posisi terbuka.</p>
    @endif
</div>

<div class="card">
    <h3>Closed Trades History</h3>
    @if($closedOrders->count() > 0)
        <div tabindex="0" role="region" aria-labelledby="closed-pos-cap" class="table-wrapper">
            <table>
                <caption id="closed-pos-cap">Closed Paper Trades</caption>
                <thead>
                    <tr>
                        <th>Tanggal &amp; Jam (WIB)</th>
                        <th>Pair</th>
                        <th>Side</th>
                        <th>Lots</th>
                        <th>Entry</th>
                        <th>Close</th>
                        <th>Pips</th>
                        <th>PnL (USD)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($closedOrders as $c)
                    <tr>
                        <td data-label="Waktu" class="mono" style="font-size:0.83rem; white-space:nowrap;">
                            {{ $c->closed_at ? $c->closed_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB' : ($c->updated_at ? $c->updated_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB' : '-') }}
                        </td>
                        <th>{{ $c->pair }}</th>
                        <td data-label="Side">{{ strtoupper($c->side) }}</td>
                        <td data-label="Lots" class="mono">{{ $c->lots }}</td>
                        <td data-label="Entry" class="mono">{{ $c->entry_price }}</td>
                        <td data-label="Close" class="mono">{{ $c->close_price }}</td>
                        <td data-label="Pips" class="mono">{{ $c->pnl_pips }}</td>
                        <td data-label="PnL" class="mono" style="color: {{ $c->pnl_usd >= 0 ? 'var(--green)' : 'var(--red)' }}; font-weight:600;">
                            {{ $c->pnl_usd >= 0 ? '+' : '' }}${{ number_format($c->pnl_usd, 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="muted">Belum ada riwayat trade yang ditutup.</p>
    @endif
</div>
@endsection

