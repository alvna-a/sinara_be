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

    // Simpan atau update profil (onboarding)
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
                Storage::delete($user->profile->photo);
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
}