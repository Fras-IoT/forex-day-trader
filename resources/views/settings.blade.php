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
                    <input id="gemini_api_key" name="gemini_api_key" type="password" placeholder="{{ $maskedKey ? 'Token tersimpan (tersembunyi)' : 'Masukkan token di sini' }}">
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

            <hr style="border:0; border-top:1px solid var(--border); margin:1rem 0;">
            <h3 style="margin-top:0;">Sumber Harga Spot (Twelve Data — Mendekati MT5 Finex)</h3>
            <div style="margin-bottom:0.85rem;">
                <label for="twelve_data_api_key">Twelve Data API Key (Gratis di twelvedata.com)</label>
                <input id="twelve_data_api_key" name="twelve_data_api_key" type="password" placeholder="{{ $settings->resolvedTwelveDataKey() !== '' ? 'API Key tersimpan (Spot Aktif)' : 'Masukkan API key Twelve Data (kosong = pakai Live Market Feed)' }}">
            </div>

            <hr style="border:0; border-top:1px solid var(--border); margin:1rem 0;">
            <h3 style="margin-top:0;">Telegram Alarm &amp; Auto-Monitor</h3>
            <div class="form-row">
                <div>
                    <label for="telegram_bot_token">Telegram Bot Token (dari @BotFather)</label>
                    <input id="telegram_bot_token" name="telegram_bot_token" type="password" placeholder="{{ $settings->resolvedTelegramToken() !== '' ? 'Token tersimpan (tersembunyi)' : '123456:ABC-DEF...' }}">
                </div>
                <div>
                    <label for="telegram_chat_id">Telegram Chat ID</label>
                    <input id="telegram_chat_id" name="telegram_chat_id" type="text" value="{{ $settings->resolvedTelegramChatId() }}" placeholder="Contoh: 123456789">
                </div>
            </div>
            <div class="form-row">
                <div>
                    <label for="auto_scan_min_score">Min Scanner Score (Kombinasi)</label>
                    <input id="auto_scan_min_score" name="auto_scan_min_score" type="number" min="10" max="99" value="{{ $settings->auto_scan_min_score ?? 70 }}">
                </div>
                <div>
                    <label for="auto_scan_min_prob">Min Monte Carlo Target First (%)</label>
                    <input id="auto_scan_min_prob" name="auto_scan_min_prob" type="number" step="1" min="10" max="95" value="{{ $settings->auto_scan_min_prob ?? 55 }}">
                </div>
            </div>
            <div style="display:flex; flex-direction:column; gap:0.4rem; margin-bottom:0.9rem;">
                <label style="display:flex; align-items:center; gap:0.5rem; font-weight:600;">
                    <input type="checkbox" name="auto_scan_enabled" value="1" {{ ($settings->auto_scan_enabled ?? true) ? 'checked' : '' }} style="width:auto;">
                    Aktifkan Auto-Scan Peluang &gt; 55% ke Telegram (tiap 5 menit)
                </label>
                <label style="display:flex; align-items:center; gap:0.5rem; font-weight:600;">
                    <input type="checkbox" name="auto_close_on_hit" value="1" {{ ($settings->auto_close_on_hit ?? true) ? 'checked' : '' }} style="width:auto;">
                    Otomatis Close PaperOrder saat hit SL / TP + Kirim Notifikasi Telegram
                </label>
            </div>

            <hr style="border:0; border-top:1px solid var(--border); margin:1rem 0;">
            <h3 style="margin-top:0;">MT5 Expert Advisor Bridge (Persetujuan Telegram)</h3>
            <div style="margin-bottom:0.85rem;">
                <label for="mt5_bridge_token">MT5 Bridge Token (Masukkan ke Input EA MT5)</label>
                <input id="mt5_bridge_token" name="mt5_bridge_token" type="text" class="mono" value="{{ $settings->resolvedMt5BridgeToken() }}" readonly onclick="this.select()">
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
                    <span class="badge badge-green">Token Terpasang</span>
                @else
                    <span class="badge badge-amber">Belum Diisi</span>
                @endif
            </li>
            <li><strong>Active Model:</strong> <code>{{ $settings->gemini_model }}</code></li>
            <li><strong>Telegram Bot:</strong>
                @if($telegramConfigured)
                    <span class="badge badge-green">Terhubung</span>
                @else
                    <span class="badge badge-amber">Belum Diisi</span>
                @endif
            </li>
            <li><strong>Price Feed:</strong>
                @php
                    $mt5Hb = \Illuminate\Support\Facades\Cache::get('mt5_bridge_heartbeat');
                    $mt5Conn = $mt5Hb && (time() - $mt5Hb < 25);
                @endphp
                @if($mt5Conn)
                    <span class="badge badge-green">MT5 Finex Live</span>
                @elseif($settings->resolvedTwelveDataKey() !== '')
                    <span class="badge badge-green">Twelve Data Spot</span>
                @else
                    <span class="badge badge-brand">Live Market Feed</span>
                @endif
            </li>
        </ul>

        <div id="settings-ajax-alert" style="display:none; margin-top:1rem;" class="alert-banner"></div>

        <div style="display:flex; gap:0.5rem; flex-wrap:wrap; margin-top:1rem;">
            <form id="form-test-gemini" method="POST" action="{{ route('settings.test-gemini') }}">
                @csrf
                <button id="btn-test-gemini" class="btn" type="submit">Test Koneksi Gemini API</button>
            </form>
            <form id="form-test-telegram" method="POST" action="{{ route('settings.test-telegram') }}">
                @csrf
                <button id="btn-test-telegram" class="btn" type="submit">Test Kirim Telegram</button>
            </form>
            <form method="POST" action="{{ route('settings.run-monitor') }}">
                @csrf
                <button class="btn btn-primary" type="submit">Jalankan Auto-Monitor Sekarang</button>
            </form>
            <a class="btn" href="{{ route('settings.download-ea') }}">Download EA MT5 (.mq5)</a>
        </div>
    </div>
</div>

<script>
(() => {
    const alertBox = document.getElementById('settings-ajax-alert');

    function bindAjaxTest(formId, btnId, loadingText, defaultText) {
        const form = document.getElementById(formId);
        const btn = document.getElementById(btnId);
        if (!form || !btn) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            btn.disabled = true;
            btn.textContent = loadingText;
            if (alertBox) alertBox.style.display = 'none';

            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value
                    }
                });
                const data = await res.json();
                if (alertBox) {
                    alertBox.className = 'alert-banner ' + (data.ok ? 'alert-ok' : 'alert-err');
                    alertBox.textContent = data.message || (data.ok ? 'Berhasil!' : 'Gagal.');
                    alertBox.style.display = 'block';
                }
            } catch (err) {
                form.submit();
            } finally {
                btn.disabled = false;
                btn.textContent = defaultText;
            }
        });
    }

    bindAjaxTest('form-test-telegram', 'btn-test-telegram', '⏳ Mengirim ke Telegram...', 'Test Kirim Telegram');
    bindAjaxTest('form-test-gemini', 'btn-test-gemini', '⏳ Menghubungi Gemini AI...', 'Test Koneksi Gemini API');
})();
</script>
@endsection

