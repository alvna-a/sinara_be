<?php

namespace App\Http\Controllers;

use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    // Ambil profil user yang sedang login
    public function show()
    {
        $user = auth()->user()->load('profile', 'skills');

        return response()->json([
            'message' => 'Berhasil ambil profil',
            'data'    => $user,
        ]);
    }

    // Simpan atau update profil (phone, photo, dll) → tabel user_profiles
    public function update(Request $request)
    {
        $request->validate([
            'nim'           => 'nullable|string',
            'program_studi' => 'nullable|string',
            'semester'      => 'nullable|string',
            'phone'         => 'nullable|string',
            'photo'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $user = auth()->user();

        $data = $request->only(['nim', 'program_studi', 'semester', 'phone']);

        // Handle upload foto
        if ($request->hasFile('photo')) {
            // Hapus foto lama kalau ada
            if ($user->profile && $user->profile->photo) {
                Storage::disk('public')->delete($user->profile->photo);
            }
            $data['photo'] = $request->file('photo')->store('photos', 'public');
        }

        // Update atau buat profil baru
        $profile = UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            $data
        );

        return response()->json([
            'message' => 'Profil berhasil disimpan',
            'data'    => $profile,
        ]);
    }

    // Update nama & email → tabel users
    public function updateAccount(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ], [
            'name.required'  => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email'    => 'Format email tidak valid.',
            'email.unique'   => 'Email sudah digunakan oleh akun lain.',
        ]);

        $user->update([
            'name'  => $request->name,
            'email' => $request->email,
        ]);

        return response()->json([
            'message' => 'Akun berhasil diperbarui',
            'data'    => $user->fresh(),
        ]);
    }
}