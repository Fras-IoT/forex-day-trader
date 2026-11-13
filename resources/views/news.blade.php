@extends('layouts.app')
@section('title', 'Kalender Berita Ekonomi & News Guard')

@section('content')
<!-- HEADER UTAMA & SINKRONISASI KALENDER -->
<div class="card" style="margin-bottom: 1rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1rem;">
        <div>
            <h2 style="margin:0; display:flex; align-items:center; gap:0.5rem;">
                <span>📅 Kalender Berita Ekonomi &amp; News Guard (1 Tahun Penuh)</span>
            </h2>
            <p class="muted" style="margin:0.25rem 0 0 0;">
                Database kalender makro ekonomi tersimpan lokal di MongoDB. Melindungi posisi trading dari lonjakan volatilitas liar (*spread spike* &amp; *whipsaw*).
            </p>
        </div>
        <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap;">
            @if($settings->isNewsGuardEnabled())
                <span class="badge badge-green" style="font-weight:700; padding:0.35rem 0.75rem;" title="News Guard memblokir order otomatis dan memperingatkan order manual saat ada berita High Impact">
                    🛡️ News Guard: AKTIF (Buffer: {{ $settings->newsGuardBufferMinutes() }} Menit)
                </span>
            @else
                <span class="badge badge-amber" style="font-weight:700; padding:0.35rem 0.75rem;" title="News Guard dinonaktifkan di Settings">
                    ⚠️ News Guard: NONAKTIF
                </span>
            @endif

            <form method="POST" action="{{ route('news.sync-calendar') }}" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='⏳ Mengunduh Kalender 1 Tahun...';">
                @csrf
                <button class="btn btn-primary" type="submit" style="font-weight:700;">
                    📥 Sinkronkan Kalender Berita (1 Tahun)
                </button>
            </form>
        </div>
    </div>

    <div style="display:flex; gap:1.5rem; flex-wrap:wrap; background:var(--surface-2); border-radius:6px; padding:0.75rem 1rem; font-size:0.86rem;">
        <div>
            <span class="muted">Total Agenda di Database:</span>
            <strong style="margin-left:0.3rem;">{{ $totalEventsCount }} Event</strong>
        </div>
        <div>
            <span class="muted">Agenda High Impact (Red Folder):</span>
            <strong style="margin-left:0.3rem; color:var(--red);">{{ $highImpactCount }} Event</strong>
        </div>
        <div>
            <span class="muted">Terakhir Disinkronkan:</span>
            <strong style="margin-left:0.3rem;">{{ $lastSyncedAt ? \Carbon\Carbon::parse($lastSyncedAt)->setTimezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB' : 'Belum pernah (Klik tombol sinkron)' }}</strong>
        </div>
        <div style="margin-left:auto;">
            <a href="{{ route('settings.index') }}" class="muted" style="text-decoration:underline;">Ubah Pengaturan Buffer News Guard &rarr;</a>
        </div>
    </div>
</div>

<!-- FILTER KALENDER BERITA & VIEW TOGGLE -->
<div class="card" style="margin-bottom: 1rem; padding: 0.9rem 1.1rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:0.75rem;">
        <!-- TAB VIEW SWITCHER -->
        <div style="display:flex; gap:0.4rem; background:var(--surface-2); padding:0.25rem; border-radius:8px; border:1px solid var(--border);">
            <a href="{{ route('news.index', array_merge(request()->query(), ['view' => 'calendar'])) }}" class="btn btn-sm {{ $viewMode === 'calendar' ? 'btn-primary' : '' }}" style="text-decoration:none; display:flex; align-items:center; gap:0.35rem;">
                <span>📅</span> <strong>Kalender Kotak-Kotak</strong>
            </a>
            <a href="{{ route('news.index', array_merge(request()->query(), ['view' => 'table'])) }}" class="btn btn-sm {{ $viewMode === 'table' ? 'btn-primary' : '' }}" style="text-decoration:none; display:flex; align-items:center; gap:0.35rem;">
                <span>📋</span> <strong>Tabel Agenda List</strong>
            </a>
        </div>

        <div class="muted" style="font-size:0.84rem;">
            <span>Klik tanggal mana saja di kalender untuk melihat detail berita &amp; pair terdampak.</span>
        </div>
    </div>

    <form method="GET" action="{{ route('news.index') }}" style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:end;">
        <input type="hidden" name="view" value="{{ $viewMode }}">
        <input type="hidden" name="year" value="{{ $year }}">
        <input type="hidden" name="month" value="{{ $month }}">

        <div>
            <label for="currency" style="font-size:0.82rem; margin-bottom:0.25rem; display:block;">Mata Uang</label>
            <select id="currency" name="currency" style="padding:0.4rem 0.6rem; font-size:0.86rem;" onchange="this.form.submit()">
                <option value="ALL" {{ $selectedCurrency === 'ALL' ? 'selected' : '' }}>Semua Mata Uang (8 Major)</option>
                @foreach($currencies as $c)
                    <option value="{{ $c }}" {{ $selectedCurrency === $c ? 'selected' : '' }}>{{ $c }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="impact" style="font-size:0.82rem; margin-bottom:0.25rem; display:block;">Tingkat Dampak</label>
            <select id="impact" name="impact" style="padding:0.4rem 0.6rem; font-size:0.86rem;" onchange="this.form.submit()">
                <option value="ALL" {{ in_array($selectedImpact, ['ALL', 'All', ''], true) ? 'selected' : '' }}>Semua Tingkat Dampak</option>
                <option value="High" {{ in_array($selectedImpact, ['HIGH', 'High'], true) ? 'selected' : '' }}>🔴 HIGH IMPACT (Bahaya Volatilitas)</option>
                <option value="Medium" {{ in_array($selectedImpact, ['MEDIUM', 'Medium'], true) ? 'selected' : '' }}>🟠 MEDIUM IMPACT</option>
            </select>
        </div>

        <div>
            <label for="timeframe" style="font-size:0.82rem; margin-bottom:0.25rem; display:block;">Rentang Jadwal Tabel</label>
            <select id="timeframe" name="timeframe" style="padding:0.4rem 0.6rem; font-size:0.86rem;" onchange="this.form.submit()">
                <option value="upcoming" {{ $selectedTimeframe === 'upcoming' ? 'selected' : '' }}>Mendatang &amp; Hari Ini</option>
                <option value="month" {{ $selectedTimeframe === 'month' ? 'selected' : '' }}>Bulan Terpilih ({{ $currentMonthName }})</option>
                <option value="all" {{ $selectedTimeframe === 'all' ? 'selected' : '' }}>Semua Jadwal (1 Tahun Penuh)</option>
            </select>
        </div>

        <button class="btn btn-sm" type="submit" style="height:35px;">Terapkan Filter</button>
        @if($selectedCurrency !== 'ALL' || $selectedImpact !== 'ALL' || $selectedTimeframe !== 'upcoming')
            <a href="{{ route('news.index', ['view' => $viewMode, 'year' => $year, 'month' => $month]) }}" class="btn btn-sm" style="height:35px; line-height:30px;">Reset Filter</a>
        @endif
    </form>
</div>

@if($viewMode === 'calendar')
<!-- ============================================================== -->
<!-- 1. MODEL KALENDER KOTAK-KOTAK INTERAKTIF (MONTH GRID VIEW)     -->
<!-- ============================================================== -->
<div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
    <!-- HEADER NAVIGASI BULAN -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-bottom:1rem; padding:0.6rem 0.8rem; background:var(--surface-2); border:1px solid var(--border); border-radius:8px;">
        <div style="display:flex; align-items:center; gap:0.4rem;">
            <a href="{{ route('news.index', array_merge(request()->query(), ['view' => 'calendar', 'year' => $prevYear, 'month' => $prevMonth])) }}" class="btn btn-sm" title="Geser ke Bulan Sebelumnya">
                &larr; Bulan Lalu
            </a>
            <a href="{{ route('news.index', array_merge(request()->query(), ['view' => 'calendar', 'year' => now('Asia/Jakarta')->year, 'month' => now('Asia/Jakarta')->month])) }}" class="btn btn-sm" title="Lompat ke Bulan Saat Ini">
                Hari Ini
            </a>
            <a href="{{ route('news.index', array_merge(request()->query(), ['view' => 'calendar', 'year' => $nextYear, 'month' => $nextMonth])) }}" class="btn btn-sm" title="Geser ke Bulan Berikutnya">
                Bulan Berikutnya &rarr;
            </a>
        </div>

        <div style="display:flex; align-items:center; gap:0.6rem;">
            <h3 style="margin:0; font-size:1.2rem; color:var(--text); display:flex; align-items:center; gap:0.4rem;">
                <span>📅</span> <strong>{{ $currentMonthName }}</strong>
            </h3>
            <!-- Selector Cepat Bulan (1 Tahun ke Depan) -->
            <select onchange="window.location.href=this.value" style="padding:0.35rem 0.6rem; font-size:0.85rem; border-radius:6px; border:1px solid var(--border); background:var(--surface); color:var(--text); font-weight:600;" title="Pilih Bulan dalam 1 Tahun">
                @foreach($availableMonths as $am)
                    <option value="{{ route('news.index', array_merge(request()->query(), ['view' => 'calendar', 'year' => $am['year'], 'month' => $am['month']])) }}" {{ $am['is_current'] ? 'selected' : '' }}>
                        {{ $am['label'] }}
                    </option>
                @endforeach
            </select>
        </div>

        <div style="font-size:0.84rem;">
            <span class="badge badge-brand">{{ $monthEventsCount }} Event di Bulan Ini</span>
        </div>
    </div>

    <!-- PETUNJUK WARNA & INTERAKSI -->
    <div style="display:flex; gap:1.25rem; flex-wrap:wrap; align-items:center; font-size:0.8rem; margin-bottom:0.75rem; padding:0.25rem 0.5rem;" class="muted">
        <div style="display:flex; align-items:center; gap:0.35rem;">
            <span style="display:inline-block; width:10px; height:10px; border-radius:3px; background:#ef4444;"></span>
            <span><strong>Merah:</strong> High Impact (Volatilitas Ekstrem / News Guard BLOCK)</span>
        </div>
        <div style="display:flex; align-items:center; gap:0.35rem;">
            <span style="display:inline-block; width:10px; height:10px; border-radius:3px; background:#f59e0b;"></span>
            <span><strong>Oranye:</strong> Medium Impact</span>
        </div>
        <div style="display:flex; align-items:center; gap:0.35rem; margin-left:auto;">
            <span>💡 <em>Klik pada kotak tanggal untuk melihat detail &amp; daftar pair yang terdampak</em></span>
        </div>
    </div>

    <!-- GRID KALENDER 7 HARI (Senin - Minggu) -->
    <div style="border:1px solid var(--border); border-radius:8px; overflow:hidden;">
        <!-- Baris Nama Hari -->
        <div style="display:grid; grid-template-columns:repeat(7, minmax(0, 1fr)); background:var(--surface-2); border-bottom:1px solid var(--border); text-align:center; font-weight:700; font-size:0.82rem;">
            <div style="padding:0.6rem 0.25rem;">Senin</div>
            <div style="padding:0.6rem 0.25rem;">Selasa</div>
            <div style="padding:0.6rem 0.25rem;">Rabu</div>
            <div style="padding:0.6rem 0.25rem;">Kamis</div>
            <div style="padding:0.6rem 0.25rem;">Jumat</div>
            <div style="padding:0.6rem 0.25rem; color:var(--red);">Sabtu</div>
            <div style="padding:0.6rem 0.25rem; color:var(--red);">Minggu</div>
        </div>

        <!-- Baris Kotak-Kotak Tanggal -->
        <div style="display:grid; grid-template-columns:repeat(7, minmax(0, 1fr)); background:var(--border); gap:1px;">
            @foreach($calendarDays as $cell)
                @php
                    $cellEvents = $eventsByDate[$cell['date']] ?? [];
                    $hasEvents = count($cellEvents) > 0;
                    $highCount = collect($cellEvents)->where('is_high', true)->count();
                @endphp
                <div 
                    class="calendar-day-cell {{ $cell['is_current_month'] ? 'active-month' : 'other-month' }} {{ $cell['is_today'] ? 'today-cell' : '' }} {{ $cell['is_weekend'] ? 'weekend-cell' : '' }}"
                    style="min-height:115px; padding:0.45rem; background:{{ $cell['is_today'] ? 'rgba(59, 130, 246, 0.08)' : ($cell['is_current_month'] ? 'var(--surface)' : 'var(--surface-2)') }}; {{ !$cell['is_current_month'] ? 'opacity:0.4;' : 'cursor:pointer;' }} display:flex; flex-direction:column; position:relative; transition: background 0.15s ease;"
                    @if($cell['is_current_month'])
                        onclick="openDateModal('{{ $cell['date'] }}', '{{ $cell['formatted'] }}')"
                        title="Klik untuk melihat {{ count($cellEvents) }} agenda pada {{ $cell['formatted'] }}"
                    @endif
                >
                    <!-- Header Kotak Tanggal -->
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.35rem;">
                        <span style="font-weight:{{ $cell['is_today'] ? '800' : '600' }}; font-size:0.88rem; {{ $cell['is_today'] ? 'color:var(--brand); background:rgba(59, 130, 246, 0.2); width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; border-radius:50%;' : ($cell['is_weekend'] ? 'color:var(--muted);' : '') }}">
                            {{ $cell['day'] }}
                        </span>

                        @if($cell['is_today'])
                            <span class="badge badge-brand" style="font-size:0.62rem; padding:0.1rem 0.35rem; line-height:1;">HARI INI</span>
                        @elseif($hasEvents)
                            <span class="muted mono" style="font-size:0.68rem; font-weight:600; {{ $highCount > 0 ? 'color:var(--red);' : '' }}">
                                {{ count($cellEvents) }} ev
                            </span>
                        @endif
                    </div>

                    <!-- List Chip Berita di Dalam Kotak -->
                    <div style="display:flex; flex-direction:column; gap:0.25rem; flex-grow:1;">
                        @if($hasEvents)
                            @foreach(array_slice($cellEvents, 0, 3) as $ev)
                                <div style="font-size:0.72rem; padding:0.2rem 0.35rem; border-radius:4px; border-left:3px solid {{ $ev['is_high'] ? '#ef4444' : '#f59e0b' }}; background:{{ $ev['is_high'] ? 'rgba(239, 68, 68, 0.15)' : 'rgba(245, 158, 11, 0.15)' }}; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $ev['time_wib'] }} WIB - {{ $ev['currency'] }} - {{ $ev['title'] }}">
                                    <span style="font-weight:700; color:{{ $ev['is_high'] ? '#ef4444' : '#f59e0b' }};">{{ $ev['currency'] }}</span>
                                    <span class="mono muted" style="font-size:0.68rem; margin:0 0.15rem;">{{ $ev['time_wib'] }}</span>
                                    <span>{{ Str::limit($ev['title'], 18) }}</span>
                                </div>
                            @endforeach

                            @if(count($cellEvents) > 3)
                                <div style="font-size:0.68rem; text-align:center; padding:0.15rem; color:var(--muted); font-weight:600;">
                                    +{{ count($cellEvents) - 3 }} agenda lagi...
                                </div>
                            @endif
                        @else
                            @if($cell['is_current_month'] && !$cell['is_weekend'])
                                <div style="flex-grow:1; display:flex; align-items:center; justify-content:center;">
                                    <span class="muted" style="font-size:0.68rem; opacity:0.4;">Tenang</span>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif

<!-- ============================================================== -->
<!-- 2. TABEL KALENDER EKONOMI LENGKAP (LIST VIEW)                 -->
<!-- ============================================================== -->
<div class="card" style="margin-bottom: 1.5rem; {{ $viewMode === 'calendar' ? 'display:none;' : '' }}">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
        <h3 style="margin:0;">Jadwal Rilis Berita Ekonomi (WIB)</h3>
        <span class="muted" style="font-size:0.82rem;">Menampilkan {{ $events->count() }} agenda (Halaman {{ $events->currentPage() }})</span>
    </div>

    @if($events->isEmpty())
        <div style="text-align:center; padding:2.5rem 1rem; color:var(--muted);">
            <div style="font-size:2rem; margin-bottom:0.5rem;">📭</div>
            <strong>Tidak ada agenda berita yang sesuai dengan kriteria filter saat ini.</strong>
            <p style="margin:0.4rem 0 1rem 0;">Coba ganti filter atau sinkronkan data kalender 1 tahun ke depan sekarang.</p>
            <form method="POST" action="{{ route('news.sync-calendar') }}">
                @csrf
                <button class="btn btn-primary" type="submit">📥 Sinkronkan Kalender Berita 1 Tahun Sekarang</button>
            </form>
        </div>
    @else
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.86rem;">
                <thead>
                    <tr style="border-bottom:2px solid var(--border); text-align:left;">
                        <th style="padding:0.6rem 0.75rem;">Waktu Rilis (WIB)</th>
                        <th style="padding:0.6rem 0.75rem;">Currency</th>
                        <th style="padding:0.6rem 0.75rem;">Dampak</th>
                        <th style="padding:0.6rem 0.75rem;">Nama Agenda Berita</th>
                        <th style="padding:0.6rem 0.75rem;">Pair Terdampak</th>
                        <th style="padding:0.6rem 0.75rem;">Status Proteksi</th>
                        <th style="padding:0.6rem 0.75rem; text-align:right;">Forecast</th>
                        <th style="padding:0.6rem 0.75rem; text-align:right;">Previous</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $now = now();
                        $buffer = $settings->newsGuardBufferMinutes();
                    @endphp
                    @foreach($events as $e)
                        @php
                            $evTime = \Carbon\Carbon::parse($e->event_time);
                            $diffMin = abs((int) $now->diffInMinutes($evTime));
                            $isInBuffer = ($diffMin <= $buffer) && ($e->impact === 'High');
                            $isToday = $evTime->isSameDay($now);
                        @endphp
                        <tr style="border-bottom:1px solid var(--border); background: {{ $isInBuffer ? 'rgba(239, 68, 68, 0.08)' : ($isToday ? 'rgba(245, 158, 11, 0.04)' : 'transparent') }};">
                            <td style="padding:0.55rem 0.75rem; white-space:nowrap;" class="mono">
                                <strong>{{ $evTime->setTimezone('Asia/Jakarta')->format('d M Y') }}</strong>
                                <span class="muted" style="margin-left:0.25rem;">{{ $evTime->setTimezone('Asia/Jakarta')->format('H:i') }} WIB</span>
                            </td>
                            <td style="padding:0.55rem 0.75rem;">
                                <span class="badge" style="font-weight:700;">{{ $e->currency }}</span>
                            </td>
                            <td style="padding:0.55rem 0.75rem;">
                                @if($e->impact === 'High')
                                    <span class="badge badge-red" style="font-weight:700;">🔴 HIGH IMPACT</span>
                                @elseif($e->impact === 'Medium')
                                    <span class="badge badge-amber">🟠 MEDIUM</span>
                                @else
                                    <span class="badge">🟡 LOW</span>
                                @endif
                            </td>
                            <td style="padding:0.55rem 0.75rem;">
                                <strong>{{ $e->title }}</strong>
                                <span class="muted" style="display:block; font-size:0.75rem;">{{ $e->description }}</span>
                            </td>
                            <td style="padding:0.55rem 0.75rem;">
                                <div style="display:flex; flex-wrap:wrap; gap:0.25rem;">
                                    @foreach($e->affected_pairs as $p)
                                        <span class="badge" style="font-size:0.7rem; padding:0.15rem 0.35rem;">{{ $p }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td style="padding:0.55rem 0.75rem; white-space:nowrap;">
                                @if($isInBuffer)
                                    <span class="badge badge-red" style="font-weight:700; box-shadow:0 0 8px rgba(239, 68, 68, 0.4);">
                                        ⚠️ ZONA BAHAYA ({{ $evTime->isFuture() ? $diffMin . 'm lagi' : $diffMin . 'm lalu' }})
                                    </span>
                                @elseif($isToday)
                                    <span class="badge badge-amber" style="font-weight:600;">Hari Ini</span>
                                @elseif($evTime->isFuture())
                                    <span class="badge muted">Mendatang</span>
                                @else
                                    <span class="badge muted" style="opacity:0.6;">Selesai</span>
                                @endif
                            </td>
                            <td style="padding:0.55rem 0.75rem; text-align:right;" class="mono">{{ $e->forecast ?? '-' }}</td>
                            <td style="padding:0.55rem 0.75rem; text-align:right;" class="mono muted">{{ $e->previous ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($events->hasPages())
            <div style="margin-top:1rem; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    @if($events->onFirstPage())
                        <span class="btn btn-sm disabled">&laquo; Sebelumnya</span>
                    @else
                        <a href="{{ $events->previousPageUrl() }}" class="btn btn-sm">&laquo; Sebelumnya</a>
                    @endif
                </div>
                <span class="muted" style="font-size:0.82rem;">Halaman {{ $events->currentPage() }} dari {{ $events->lastPage() }}</span>
                <div>
                    @if($events->hasMorePages())
                        <a href="{{ $events->nextPageUrl() }}" class="btn btn-sm">Berikutnya &raquo;</a>
                    @else
                        <span class="btn btn-sm disabled">Berikutnya &raquo;</span>
                    @endif
                </div>
            </div>
        @endif
    @endif
</div>

<!-- ============================================================== -->
<!-- 3. MODAL DIALOG DETAIL BERITA & PAIR TERDAMPAK (NATIVE DIALOG) -->
<!-- ============================================================== -->
<dialog id="calendar-day-modal" style="width:min(94vw, 780px); max-height:88vh; border:1px solid var(--border); border-radius:12px; background:var(--surface); color:var(--text); padding:0; box-shadow:0 16px 40px rgba(0,0,0,0.5); overflow:hidden;">
    <!-- MODAL HEADER -->
    <div style="display:flex; justify-content:space-between; align-items:center; padding:1rem 1.25rem; border-bottom:1px solid var(--border); background:var(--surface-2);">
        <div>
            <h3 id="modal-date-title" style="margin:0; font-size:1.15rem; display:flex; align-items:center; gap:0.5rem;">
                <span>📅</span> <span id="modal-date-heading-text">Agenda Berita Ekonomi</span>
            </h3>
            <span id="modal-event-summary" class="muted" style="font-size:0.82rem; margin-top:0.2rem; display:block;"></span>
        </div>
        <form method="dialog">
            <button class="btn btn-sm" value="cancel" style="padding:0.25rem 0.6rem; font-size:0.9rem;" title="Tutup Modal (Esc)">✕</button>
        </form>
    </div>

    <!-- MODAL BODY: LIST BERITA DAN PAIR TERDAMPAK -->
    <div id="modal-events-list" style="padding:1.25rem; max-height:62vh; overflow-y:auto; display:flex; flex-direction:column; gap:1rem;">
        <!-- Diisi secara dinamis oleh JavaScript -->
    </div>

    <!-- MODAL FOOTER -->
    <div style="display:flex; justify-content:flex-end; gap:0.5rem; padding:0.75rem 1.25rem; border-top:1px solid var(--border); background:var(--surface-2);">
        <form method="dialog">
            <button class="btn" value="close">Tutup</button>
        </form>
    </div>
</dialog>

<script>
    // Data events bulanan yang di-pass dari backend controller
    const calendarEventsMap = {!! $eventsByDateJson !!};
    const newsGuardBufferMin = {{ $settings->newsGuardBufferMinutes() }};

    function openDateModal(dateKey, formattedDate) {
        const modal = document.getElementById('calendar-day-modal');
        const titleEl = document.getElementById('modal-date-heading-text');
        const summaryEl = document.getElementById('modal-event-summary');
        const listEl = document.getElementById('modal-events-list');

        if (!modal) return;

        titleEl.textContent = formattedDate || ('Tanggal ' + dateKey);
        const events = calendarEventsMap[dateKey] || [];

        if (events.length === 0) {
            summaryEl.textContent = 'Tidak ada agenda berita ekonomi pada tanggal ini.';
            listEl.innerHTML = `
                <div style="text-align:center; padding:2rem 1rem; color:var(--muted);">
                    <div style="font-size:2rem; margin-bottom:0.5rem;">🍃</div>
                    <strong>Kalender Tenang (Clear Market)</strong>
                    <p style="margin:0.25rem 0 0 0; font-size:0.88rem;">Tidak ada agenda rilis berita makro High/Medium impact yang terjadwal. Kondisi pasar forex normal tanpa ancaman lonjakan spread liar.</p>
                </div>
            `;
        } else {
            const highCount = events.filter(e => e.is_high).length;
            const medCount = events.filter(e => e.is_medium).length;
            summaryEl.innerHTML = `Ditemukan <strong>${events.length} Agenda Berita</strong> (${highCount} High Impact, ${medCount} Medium Impact)`;

            let html = '';
            events.forEach((ev, idx) => {
                const isHigh = ev.is_high;
                const impactBadge = isHigh 
                    ? '<span class="badge badge-red" style="font-weight:700;">🔴 HIGH IMPACT (Volatilitas Ekstrem)</span>'
                    : '<span class="badge" style="background:rgba(245, 158, 11, 0.2); color:#d97706; border:1px solid #f59e0b; font-weight:700;">🟠 MEDIUM IMPACT</span>';

                let bufferWarning = '';
                if (ev.is_in_buffer) {
                    bufferWarning = `
                        <div style="margin-top:0.6rem; padding:0.5rem 0.75rem; border-radius:6px; background:rgba(239, 68, 68, 0.15); border:1px solid var(--red); color:var(--red); font-size:0.82rem; font-weight:700;">
                            ⚠️ ZONA BAHAYA NEWS GUARD: Saat ini berada dalam jendela perlindungan &plusmn;${newsGuardBufferMin} menit. Posisi otomatis diblokir / ditunda untuk menjaga Stop Loss.
                        </div>
                    `;
                }

                // Pairs badges
                const pairsHtml = (ev.affected_pairs && ev.affected_pairs.length > 0)
                    ? ev.affected_pairs.map(p => {
                        const isGold = (p === 'XAU/USD');
                        return `<span class="badge" style="font-size:0.8rem; font-weight:700; padding:0.25rem 0.55rem; ${isGold ? 'background:rgba(234, 179, 8, 0.2); color:#ca8a04; border:1px solid #eab308;' : 'background:var(--surface-2); border:1px solid var(--border);'}">${p}${isGold ? ' (Gold)' : ''}</span>`;
                    }).join(' ')
                    : '<span class="muted" style="font-size:0.82rem;">Semua pasangan mata uang terkait ' + ev.currency + '</span>';

                html += `
                    <div style="border:1px solid var(--border); border-left:4px solid ${isHigh ? '#ef4444' : '#f59e0b'}; border-radius:8px; padding:0.9rem 1rem; background:var(--surface-2);">
                        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.4rem;">
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <span class="mono" style="font-weight:800; font-size:0.95rem; background:var(--surface); border:1px solid var(--border); padding:0.2rem 0.5rem; border-radius:4px;">
                                    ⏰ ${ev.time_wib} WIB
                                </span>
                                <span class="badge" style="font-weight:800; font-size:0.85rem;">${ev.currency}</span>
                                ${impactBadge}
                            </div>
                            <span class="muted mono" style="font-size:0.75rem;">${ev.time_full_wib}</span>
                        </div>

                        <h4 style="margin:0.4rem 0 0.5rem 0; font-size:1.05rem; color:var(--text);">
                            ${ev.title}
                        </h4>

                        <!-- KETERANGAN & PENJELASAN VOLATILITAS -->
                        <div style="margin:0.5rem 0; padding:0.6rem 0.75rem; border-radius:6px; background:var(--surface); border:1px solid var(--border); font-size:0.86rem; line-height:1.45;">
                            <div style="font-weight:700; color:var(--text); margin-bottom:0.2rem; display:flex; align-items:center; gap:0.3rem;">
                                <span>💡</span> <span>Keterangan Berita &amp; Efek Volatilitas:</span>
                            </div>
                            <div style="color:var(--text);">${ev.description}</div>
                        </div>

                        <!-- DAFTAR PAIR YANG TERDAMPAK -->
                        <div style="margin-top:0.6rem;">
                            <div style="font-size:0.8rem; font-weight:700; margin-bottom:0.35rem; color:var(--muted); display:flex; align-items:center; gap:0.3rem;">
                                <span>🎯</span> <span>Pasangan Mata Uang (Pair) yang Akan Terdampak:</span>
                            </div>
                            <div style="display:flex; flex-wrap:wrap; gap:0.35rem; align-items:center;">
                                ${pairsHtml}
                            </div>
                        </div>

                        <!-- DATA FORECAST & PREVIOUS -->
                        <div style="display:flex; gap:1.5rem; margin-top:0.6rem; font-size:0.82rem;" class="muted mono">
                            <div>Forecast: <strong style="color:var(--text);">${ev.forecast}</strong></div>
                            <div>Previous: <strong style="color:var(--text);">${ev.previous}</strong></div>
                            <div>Actual: <strong style="color:var(--text);">${ev.actual}</strong></div>
                        </div>

                        ${bufferWarning}
                    </div>
                `;
            });

            listEl.innerHTML = html;
        }

        modal.showModal();
    }

    // Tutup modal jika user mengklik backdrop di luar box dialog
    document.addEventListener('DOMContentLoaded', () => {
        const modal = document.getElementById('calendar-day-modal');
        if (modal) {
            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    modal.close();
                }
            });
        }
    });
</script>

<!-- ANALISIS MAKRO PER PASANGAN MATA UANG (GEMINI AI) -->
<div class="card" style="margin-bottom: 1rem;">
    <h2>Analisis Makro &amp; Sentimen Berita (Gemini AI)</h2>
    <p class="muted">Pilih pasangan mata uang untuk mengambil headline berita terkini dan ringkasan sentimen makro dari Gemini AI.</p>
    <form method="POST" action="{{ route('news.fetch') }}" style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:end;">
        @csrf
        <div style="min-width:180px;">
            <label for="pair">Currency Pair</label>
            <select id="pair" name="pair">
                @foreach($pairs as $p)
                    <option value="{{ $p }}">{{ $p }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary" type="submit">Ambil Berita &amp; Analisis Gemini</button>
    </form>
</div>

@foreach($briefs as $b)
<div class="card" style="margin-bottom: 1rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.5rem;">
        <h3 style="margin:0;">{{ $b->pair }}</h3>
        <div style="display:flex; gap:0.4rem;">
            <span class="badge badge-brand">Impact: {{ $b->impact_level }}</span>
            <span class="badge {{ $b->bias === 'Bullish' ? 'badge-green' : ($b->bias === 'Bearish' ? 'badge-red' : '') }}">Bias: {{ $b->bias }}</span>
        </div>
    </div>

    @if($b->ai_brief)
        <div class="ai-box" style="margin-bottom:0.75rem;">{!! \App\Services\GeminiService::toHtml($b->ai_brief) !!}</div>
    @elseif(!$geminiConfigured)
        <div class="muted" style="margin-bottom:0.75rem;"><em>Ringkasan AI tidak aktif (Gemini token belum diisi di Settings).</em></div>
    @endif

    <ul style="margin:0; padding-left:1.2rem;">
        @foreach(($b->headlines ?? []) as $h)
            <li style="margin-bottom:0.35rem;">
                <span class="muted mono">[{{ $h['published'] }}]</span>
                <strong>{{ $h['title'] }}</strong>
                <span class="muted">({{ str_replace('Yahoo Finance FX', 'Market News Feed', $h['source'] ?? '') }})</span>
            </li>
        @endforeach
    </ul>
</div>
@endforeach
@endsection
