<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AccountController extends Controller
{
    public function index()
    {
        $users = User::orderBy('created_at', 'desc')->get();

        return view('accounts', [
            'users' => $users,
            'me' => Auth::user(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'role' => 'required|in:admin,trader',
            'password' => 'required|string|min:6',
        ]);

        $email = strtolower(trim($data['email']));
        if (User::where('email', $email)->exists()) {
            return redirect()->route('accounts.index')->with('error', "Email {$email} sudah terdaftar.");
        }

        User::create([
            'name' => trim($data['name']),
            'email' => $email,
            'role' => $data['role'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);

        return redirect()->route('accounts.index')->with('status', "Akun {$email} berhasil ditambahkan.");
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'password' => 'nullable|string|min:6',
        ]);

        $email = strtolower(trim($data['email']));
        $exists = User::where('email', $email)->where('_id', '!=', $user->_id)->exists();
        if ($exists) {
            return redirect()->route('accounts.index')->with('error', "Email {$email} sudah digunakan akun lain.");
        }

        $user->name = trim($data['name']);
        $user->email = $email;
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        return redirect()->route('accounts.index')->with('status', 'Profil akun Anda berhasil diperbarui.');
    }

    public function toggle(string $id)
    {
        $target = User::findOrFail($id);
        if ((string) $target->_id === (string) Auth::id()) {
            return redirect()->route('accounts.index')->with('error', 'Tidak dapat menonaktifkan akun yang sedang Anda gunakan.');
        }

        $target->is_active = !((bool) $target->is_active);
        $target->save();

        $state = $target->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->route('accounts.index')->with('status', "Akun {$target->email} berhasil {$state}.");
    }

    public function destroy(string $id)
    {
        $target = User::findOrFail($id);
        if ((string) $target->_id === (string) Auth::id()) {
            return redirect()->route('accounts.index')->with('error', 'Tidak dapat menghapus akun yang sedang aktif digunakan.');
        }

        $email = $target->email;
        $target->delete();

        return redirect()->route('accounts.index')->with('status', "Akun {$email} berhasil dihapus.");
    }
}

