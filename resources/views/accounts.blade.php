@extends('layouts.app')
@section('title', 'Management Akun')

@section('content')
<div class="grid grid-2" style="margin-bottom: 1rem;">
    <div class="card">
        <h2>Profil Akun Saya</h2>
        <p class="muted">Perbarui nama, email, atau ganti password akun yang sedang Anda gunakan.</p>
        <form method="POST" action="{{ route('accounts.profile') }}">
            @csrf
            <div class="form-row">
                <div>
                    <label for="my_name">Nama</label>
                    <input id="my_name" name="name" type="text" value="{{ $me->name }}" required>
                </div>
                <div>
                    <label for="my_email">Email</label>
                    <input id="my_email" name="email" type="email" value="{{ $me->email }}" required>
                </div>
            </div>
            <div class="form-row">
                <div>
                    <label for="my_password">Password Baru (opsional)</label>
                    <input id="my_password" name="password" type="password" minlength="6" placeholder="Kosongkan jika tidak diganti">
                </div>
            </div>
            <button class="btn btn-primary" type="submit">Simpan Profil</button>
        </form>
    </div>

    <div class="card">
        <h2>Tambah Akun Trader Baru</h2>
        <p class="muted">Tambahkan pengguna baru ke koleksi MongoDB <code>users</code>.</p>
        <form method="POST" action="{{ route('accounts.store') }}">
            @csrf
            <div class="form-row">
                <div>
                    <label for="new_name">Nama</label>
                    <input id="new_name" name="name" type="text" value="{{ old('name') }}" placeholder="Nama Trader" required>
                </div>
                <div>
                    <label for="new_email">Email</label>
                    <input id="new_email" name="email" type="email" value="{{ old('email') }}" placeholder="trader@forex.local" required>
                </div>
            </div>
            <div class="form-row">
                <div>
                    <label for="new_role">Role</label>
                    <select id="new_role" name="role">
                        <option value="trader">Trader</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div>
                    <label for="new_password">Password (min 6 karakter)</label>
                    <input id="new_password" name="password" type="password" minlength="6" required>
                </div>
            </div>
            <button class="btn btn-primary" type="submit">Buat Akun</button>
        </form>
    </div>
</div>

<div class="card">
    <h2>Daftar Akun Terdaftar (MongoDB)</h2>
    <div tabindex="0" role="region" aria-labelledby="accounts-cap" class="table-wrapper">
        <table>
            <caption id="accounts-cap">Registered Accounts ({{ $users->count() }})</caption>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $u)
                @php $isMe = (string)$u->_id === (string)$me->_id; @endphp
                <tr>
                    <th>{{ $u->name }} @if($isMe)<span class="badge badge-brand">Anda</span>@endif</th>
                    <td data-label="Email" class="mono">{{ $u->email }}</td>
                    <td data-label="Role"><span class="badge">{{ strtoupper($u->role ?? 'trader') }}</span></td>
                    <td data-label="Status">
                        <span class="badge {{ ($u->is_active ?? true) ? 'badge-green' : 'badge-red' }}">
                            {{ ($u->is_active ?? true) ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                    <td data-label="Aksi" style="display:flex; gap:0.4rem; flex-wrap:wrap;">
                        @if(!$isMe)
                            <form method="POST" action="{{ route('accounts.toggle', (string)$u->_id) }}">
                                @csrf
                                <button class="btn btn-sm" type="submit">
                                    {{ ($u->is_active ?? true) ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </form>
                            <button type="button" class="btn btn-sm btn-danger" onclick="document.getElementById('del-acc-{{ (string)$u->_id }}').showModal()">
                                Hapus
                            </button>

                            <dialog id="del-acc-{{ (string)$u->_id }}" aria-labelledby="del-title-{{ (string)$u->_id }}">
                                <h3 id="del-title-{{ (string)$u->_id }}">Hapus Akun?</h3>
                                <p>Yakin ingin menghapus akun <strong>{{ $u->email }}</strong> secara permanen?</p>
                                <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1rem;">
                                    <form method="dialog">
                                        <button class="btn" value="cancel" autofocus>Batal</button>
                                    </form>
                                    <form method="POST" action="{{ route('accounts.destroy', (string)$u->_id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger" type="submit">Ya, Hapus</button>
                                    </form>
                                </div>
                            </dialog>
                        @else
                            <span class="muted">Sedang aktif</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

