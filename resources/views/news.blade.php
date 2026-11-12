@extends('layouts.app')
@section('title', 'News & Macro Monitor')

@section('content')
<div class="card" style="margin-bottom: 1rem;">
    <h2>Forex News &amp; Macro Impact Monitor</h2>
    <p class="muted">Memantau berita &amp; sentimen makro per pasangan mata uang, diringkas oleh Gemini AI.</p>
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
                <span class="muted">({{ $h['source'] }})</span>
            </li>
        @endforeach
    </ul>
</div>
@endforeach
@endsection

