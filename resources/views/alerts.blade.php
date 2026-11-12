@extends('layouts.app')
@section('title', 'Price Alerts')

@section('content')
<div class="grid grid-2">
    <div class="card">
        <h2>Tambah Price Alert</h2>
        <form method="POST" action="{{ route('alerts.store') }}">
            @csrf
            <div class="form-row">
                <div>
                    <label for="pair">Pair</label>
                    <select id="pair" name="pair">
                        @foreach($pairs as $p)
                            <option value="{{ $p }}">{{ $p }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="condition">Condition</label>
                    <select id="condition" name="condition">
                        <option value="above">Price Above (&ge;)</option>
                        <option value="below">Price Below (&le;)</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div>
                    <label for="target_price">Target Price</label>
                    <input id="target_price" name="target_price" type="number" step="any" required>
                </div>
                <div>
                    <label for="label">Label</label>
                    <input id="label" name="label" type="text" placeholder="e.g. Breakout London High" required>
                </div>
            </div>
            <button class="btn btn-primary" type="submit">Simpan Alert</button>
        </form>
    </div>

    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
            <h2 style="margin:0;">Daftar Alerts</h2>
            <form method="POST" action="{{ route('alerts.check') }}">
                @csrf
                <button class="btn btn-primary btn-sm" type="submit">Check Live Prices Now</button>
            </form>
        </div>

        @if($alerts->count() > 0)
            <div tabindex="0" role="region" aria-labelledby="alerts-cap" class="table-wrapper">
                <table>
                    <caption id="alerts-cap">Price Alerts (MongoDB)</caption>
                    <thead><tr><th>Pair</th><th>Rule</th><th>Label</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        @foreach($alerts as $a)
                        <tr>
                            <th>{{ $a->pair }}</th>
                            <td data-label="Rule" class="mono">{{ $a->condition === 'above' ? '>=' : '<=' }} {{ $a->target_price }}</td>
                            <td data-label="Label">{{ $a->label }}</td>
                            <td data-label="Status">
                                <span class="badge {{ $a->status === 'triggered' ? 'badge-green' : ($a->status === 'active' ? 'badge-brand' : '') }}">
                                    {{ strtoupper($a->status) }}
                                </span>
                            </td>
                            <td data-label="Action">
                                @if($a->status === 'active')
                                <form method="POST" action="{{ route('alerts.dismiss', (string)$a->_id) }}">
                                    @csrf
                                    <button class="btn btn-sm" type="submit">Dismiss</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="muted">Belum ada price alert.</p>
        @endif
    </div>
</div>
@endsection

