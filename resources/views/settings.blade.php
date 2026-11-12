@extends('layouts.app')
@section('title', 'Settings & Gemini Token')

@section('content')
<div class="grid grid-2">
    <div class="card">
        <h2>Konfigurasi Gemini AI &amp; Risk Limits</h2>
        <p class="muted">Disimpan di MongoDB (collection <code>settings</code>). Anda dapat memasukkan Gemini API Token di sini atau melalui <code>.env</code>.</p>

        <form method="POST" action="{{ route('settings.update') }}">
            @csrf
            <div class="form-row">
                <div>
                    <label for="gemini_api_key">Gemini API Token (Access Key)</label>
                    <input id="gemini_api_key" name="gemini_api_key" type="password" placeholder="{{ $maskedKey ? 'Tersimpan: ' . $maskedKey : 'Paste AIza... token di sini' }}">
                </div>
                <div>
                    <label for="gemini_model">Gemini Model</label>
                    <select id="gemini_model" name="gemini_model">
                        <option value="gemini-3.8-flash" {{ $settings->gemini_model === 'gemini-3.8-flash' ? 'selected' : '' }}>gemini-3.8-flash (Recommended)</option>
                        <option value="gemini-4-argon" {{ $settings->gemini_model === 'gemini-4-argon' ? 'selected' : '' }}>gemini-4-argon (Deep Reasoning)</option>
                        <option value="gemini-3.5-flash-lite" {{ $settings->gemini_model === 'gemini-3.5-flash-lite' ? 'selected' : '' }}>gemini-3.5-flash-lite (Fastest)</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label for="account_balance">Paper Account Balance (USD)</label>
                    <input id="account_balance" name="account_balance" type="number" step="0.01" value="{{ $settings->account_balance }}" required>
                </div>
                <div>
                    <label for="leverage">Leverage (1:X)</label>
                    <input id="leverage" name="leverage" type="number" value="{{ $settings->leverage }}" required>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label for="max_loss_per_trade_usd">Max Loss / Trade (USD)</label>
                    <input id="max_loss_per_trade_usd" name="max_loss_per_trade_usd" type="number" step="1" value="{{ $settings->max_loss_per_trade_usd }}" required>
                </div>
                <div>
                    <label for="max_daily_loss_usd">Max Daily Loss (USD)</label>
                    <input id="max_daily_loss_usd" name="max_daily_loss_usd" type="number" step="1" value="{{ $settings->max_daily_loss_usd }}" required>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label for="max_trades_per_day">Max Trades / Day</label>
                    <input id="max_trades_per_day" name="max_trades_per_day" type="number" value="{{ $settings->max_trades_per_day }}" required>
                </div>
                <div>
                    <label for="min_reward_risk">Min Reward:Risk</label>
                    <input id="min_reward_risk" name="min_reward_risk" type="number" step="0.1" value="{{ $settings->min_reward_risk }}" required>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label for="max_position_pct">Max Position % of Equity</label>
                    <input id="max_position_pct" name="max_position_pct" type="number" step="1" value="{{ $settings->max_position_pct }}" required>
                </div>
                <div>
                    <label for="flat_by_utc">Flat-by Session Cutoff (UTC)</label>
                    <input id="flat_by_utc" name="flat_by_utc" type="text" value="{{ $settings->flat_by_utc }}" required>
                </div>
            </div>

            <div style="margin-bottom:0.85rem;">
                <label style="display:flex; align-items:center; gap:0.5rem; font-weight:600;">
                    <input type="checkbox" name="paper_mode" value="1" {{ $settings->paper_mode ? 'checked' : '' }} style="width:auto;">
                    Paper Trading Mode (Default &amp; Aman)
                </label>
            </div>

            <button class="btn btn-primary" type="submit">Simpan Pengaturan</button>
        </form>
    </div>

    <div class="card">
        <h2>Status Koneksi</h2>
        <ul style="padding-left:1.2rem;">
            <li><strong>Database:</strong> MongoDB (<code>{{ config('database.connections.mongodb.database') }}</code>)</li>
            <li><strong>Gemini AI:</strong>
                @if($geminiConfigured)
                    <span class="badge badge-green">Token Terpasang ({{ $maskedKey }})</span>
                @else
                    <span class="badge badge-amber">Belum Diisi</span>
                @endif
            </li>
            <li><strong>Active Model:</strong> <code>{{ $settings->gemini_model }}</code></li>
        </ul>

        <form method="POST" action="{{ route('settings.test-gemini') }}" style="margin-top:1rem;">
            @csrf
            <button class="btn" type="submit">Test Koneksi Gemini API</button>
        </form>
    </div>
</div>
@endsection

