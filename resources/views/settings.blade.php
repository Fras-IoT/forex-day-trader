@extends('layouts.app')
@section('title', 'Settings & Gemini Token')

@section('content')
<div class="grid grid-2">
    <div class="card">
        <h2>Konfigurasi Gemini AI &amp; Risk Limits</h2>
        <p class="muted">Disimpan di MongoDB (collection <code>settings</code>). Anda dapat memasukkan Gemini API Token di sini atau melalui <code>.env</code>.</p>

        <form method="POST" action="{{ route('settings.update') }}">
            @csrf

            <!-- MODE EKSEKUSI TRADING (MANUAL VS AUTO TRADING) -->
            <div style="background: var(--surface-2); border: 2px solid {{ $settings->isAutoTrading() ? 'var(--green)' : 'var(--amber)' }}; border-radius: 8px; padding: 1.1rem; margin-bottom: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.85rem;">
                    <div>
                        <h3 style="margin: 0; font-size: 1.05rem; display: flex; align-items: center; gap: 0.5rem;">
                            <span>⚙️ Mode Eksekusi Trading</span>
                        </h3>
                        <p class="muted" style="margin: 0.2rem 0 0 0; font-size: 0.82rem;">
                            Atur bagaimana order dieksekusi saat sinyal entry memenuhi syarat (Min Score &amp; Min Probabilitas).
                        </p>
                    </div>
                    <div>
                        @if($settings->isAutoTrading())
                            <span class="badge badge-green" style="font-size: 0.84rem; padding: 0.35rem 0.85rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 0 10px rgba(16, 185, 129, 0.25);">
                                <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#10b981; box-shadow:0 0 6px #10b981;"></span>
                                ⚡ SIGN STATUS: AUTO TRADING AKTIF
                            </span>
                        @else
                            <span class="badge badge-amber" style="font-size: 0.84rem; padding: 0.35rem 0.85rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem; box-shadow: 0 0 10px rgba(245, 158, 11, 0.25);">
                                <span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#f59e0b; box-shadow:0 0 6px #f59e0b;"></span>
                                👤 SIGN STATUS: MANUAL TRADING AKTIF
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Toggle Options -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <!-- Opsi Manual -->
                    <label style="display: block; cursor: pointer; border: 2px solid {{ $settings->isManualTrading() ? 'var(--amber)' : 'var(--border)' }}; background: {{ $settings->isManualTrading() ? 'rgba(245, 158, 11, 0.08)' : 'var(--surface)' }}; border-radius: 6px; padding: 0.85rem; transition: all 0.2s ease;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                            <input type="radio" name="trading_mode" value="manual" {{ $settings->isManualTrading() ? 'checked' : '' }} onchange="this.form.submit()" style="width: auto; margin: 0;">
                            <span style="font-weight: 700; color: {{ $settings->isManualTrading() ? 'var(--amber)' : 'var(--text)' }};">👤 MANUAL TRADING</span>
                        </div>
                        <p class="muted" style="margin: 0; font-size: 0.79rem; line-height: 1.45;">
                            Peluang entry dikirim ke <b>Telegram</b> dengan tombol <b>SETUJUI / TOLAK</b>. Posisi <b>hanya</b> masuk MT5 setelah Anda mengklik tombol setuju.
                        </p>
                    </label>

                    <!-- Opsi Auto -->
                    <label style="display: block; cursor: pointer; border: 2px solid {{ $settings->isAutoTrading() ? 'var(--green)' : 'var(--border)' }}; background: {{ $settings->isAutoTrading() ? 'rgba(16, 185, 129, 0.08)' : 'var(--surface)' }}; border-radius: 6px; padding: 0.85rem; transition: all 0.2s ease;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.35rem;">
                            <input type="radio" name="trading_mode" value="auto" {{ $settings->isAutoTrading() ? 'checked' : '' }} onchange="this.form.submit()" style="width: auto; margin: 0;">
                            <span style="font-weight: 700; color: {{ $settings->isAutoTrading() ? 'var(--green)' : 'var(--text)' }};">⚡ AUTO TRADING</span>
                        </div>
                        <p class="muted" style="margin: 0; font-size: 0.79rem; line-height: 1.45;">
                            Peluang entry yang lolos syarat settingan <b>langsung otomatis dieksekusi ke MT5 Finex</b> tanpa butuh konfirmasi tombol di Telegram.
                        </p>
                    </label>
                </div>
            </div>

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
                    <label for="flat_by_wib">Batas Jam Flat-by Harian (WIB)</label>
                    <input id="flat_by_wib" name="flat_by_wib" type="time" value="{{ $settings->resolvedFlatByWib() }}" required style="font-size:0.95rem; padding:0.45rem 0.65rem;">
                </div>
            </div>

            <div style="margin-bottom:0.85rem;">
                <label style="display:flex; align-items:center; gap:0.5rem; font-weight:600;">
                    <input type="checkbox" name="paper_mode" value="1" {{ $settings->paper_mode ? 'checked' : '' }} style="width:auto;">
                    Paper Trading Mode (Default &amp; Aman)
                </label>
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
            <h3 style="margin-top:0; display:flex; align-items:center; gap:0.4rem;">
                <span>🛡️ News Guard (Proteksi Volatilitas Berita High-Impact)</span>
            </h3>
            <p class="muted" style="margin-top:-0.3rem; margin-bottom:0.75rem;">
                Mencegah eksekusi order saat ada rilis berita berdampak besar (seperti NFP, CPI, Keputusan Suku Bunga) untuk melindungi modal dari pelebaran spread liar (*spread spike*) dan ekor candle palsu.
            </p>
            <div style="margin-bottom:0.85rem;">
                <label style="display:flex; align-items:center; gap:0.5rem; font-weight:600;">
                    <input type="checkbox" name="news_guard_enabled" value="1" {{ ($settings->news_guard_enabled ?? true) ? 'checked' : '' }} style="width:auto;">
                    Aktifkan News Guard Risk Filter (Blokir Auto Trading &amp; Beri Peringatan Manual)
                </label>
            </div>
            <div style="margin-bottom:0.85rem;">
                <label for="news_guard_buffer_minutes">Jendela Waktu Pengaman (Buffer Menit)</label>
                <input id="news_guard_buffer_minutes" name="news_guard_buffer_minutes" type="number" min="5" max="180" value="{{ $settings->news_guard_buffer_minutes ?? 30 }}">
                <span class="muted" style="font-size:0.78rem;">Sistem akan memblokir/memperingatkan entry dari <b>{{ $settings->news_guard_buffer_minutes ?? 30 }} menit sebelum</b> hingga <b>{{ $settings->news_guard_buffer_minutes ?? 30 }} menit sesudah</b> rilis berita High Impact.</span>
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
                @else
                    <span class="badge badge-brand">Live Market Feed</span>
                @endif
            </li>
            <li><strong>Sign Status Trading:</strong>
                @if($settings->isAutoTrading())
                    <span class="badge badge-green">⚡ AUTO TRADING (Eksekusi Otomatis)</span>
                @else
                    <span class="badge badge-amber">👤 MANUAL TRADING (Persetujuan Telegram)</span>
                @endif
            </li>
        </ul>

        <div id="settings-ajax-alert" style="display:none; margin-top:1rem;" class="alert-banner"></div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.6rem; margin-top:1.25rem;">
            <form id="form-test-gemini" method="POST" action="{{ route('settings.test-gemini') }}" style="margin:0;">
                @csrf
                <button id="btn-test-gemini" class="btn" type="submit" style="width:100%; height:100%; justify-content:center; text-align:center;">Test Koneksi Gemini API</button>
            </form>
            <form id="form-test-telegram" method="POST" action="{{ route('settings.test-telegram') }}" style="margin:0;">
                @csrf
                <button id="btn-test-telegram" class="btn" type="submit" style="width:100%; height:100%; justify-content:center; text-align:center;">Test Kirim Telegram</button>
            </form>
            <form method="POST" action="{{ route('settings.run-monitor') }}" style="margin:0;">
                @csrf
                <button class="btn btn-primary" type="submit" style="width:100%; height:100%; justify-content:center; text-align:center;">Jalankan Auto-Monitor</button>
            </form>
            <a class="btn" href="{{ route('settings.download-ea') }}" style="width:100%; height:100%; box-sizing:border-box; justify-content:center; text-align:center; display:inline-flex; align-items:center;">Download EA MT5 (.mq5)</a>
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

