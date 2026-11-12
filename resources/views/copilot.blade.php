@extends('layouts.app')
@section('title', 'Gemini FX Copilot')

@section('content')
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.75rem;">
        <div>
            <h2 style="margin:0;">Gemini Forex Copilot</h2>
            <div class="muted">Asisten trading Forex multi-turn menggunakan Gemini Interactions API (<code>gemini-3.8-flash</code>) dengan konteks pasar &amp; trade plan Anda.</div>
        </div>
        <form method="POST" action="{{ route('copilot.clear') }}">
            @csrf
            <button class="btn btn-sm" type="submit">Clear Chat</button>
        </form>
    </div>

    @if(!$geminiConfigured)
        <div class="alert-banner alert-warn">
            Token Gemini API belum diisi. <a href="{{ route('settings.index') }}">Buka Settings</a> untuk memasukkan access token Anda.
        </div>
    @endif

    <div style="border:1px solid var(--border); border-radius:8px; padding:1rem; min-height:280px; max-height:480px; overflow-y:auto; margin-bottom:1rem; background:var(--bg);">
        @forelse($messages as $m)
            <div style="margin-bottom:0.85rem; display:flex; flex-direction:column; align-items:{{ $m->role === 'user' ? 'flex-end' : 'flex-start' }};">
                <div class="muted" style="font-size:0.75rem; margin-bottom:0.2rem;">
                    {{ $m->role === 'user' ? 'Anda' : 'Gemini (' . $m->model . ')' }}
                    @if($m->pair) &bull; {{ $m->pair }} @endif
                </div>
                <div style="max-width:85%; padding:0.65rem 0.9rem; border-radius:8px; white-space:pre-wrap; border:1px solid var(--border); background:{{ $m->role === 'user' ? 'var(--brand)' : 'var(--surface)' }}; color:{{ $m->role === 'user' ? 'var(--brand-text)' : 'var(--text)' }};">{{ $m->content }}</div>
            </div>
        @empty
            <p class="muted">Belum ada percakapan. Tanyakan tentang setup EUR/USD, manajemen risiko lot, atau evaluasi hasil Time Machine Anda.</p>
        @endforelse
    </div>

    <form method="POST" action="{{ route('copilot.send') }}">
        @csrf
        <input type="hidden" name="previous_interaction_id" value="{{ $lastInteractionId }}">
        <div class="form-row">
            <div style="max-width:180px;">
                <label for="pair">Fokus Pair</label>
                <select id="pair" name="pair">
                    @foreach($pairs as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex:1;">
                <label for="message">Pesan untuk Gemini Copilot</label>
                <input id="message" name="message" type="text" placeholder="Contoh: Analisa peluang EUR/USD sesi London hari ini dan saran SL/TP..." required>
            </div>
        </div>
        <button class="btn btn-primary" type="submit">Kirim ke Gemini</button>
    </form>
</div>
@endsection

