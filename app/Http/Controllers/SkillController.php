<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Models\Division;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    // Ambil semua skill (untuk autocomplete/dropdown)
    public function index()
    {
        $skills = Skill::orderBy('name')->get();

        return response()->json([
            'message' => 'Berhasil ambil semua skill',
            'data'    => $skills,
        ]);
    }

    // Ambil skill default berdasarkan divisi (untuk suggestions)
    public function byDivision($divisionId)
    {
        $division = Division::with('skills')->find($divisionId);

        if (!$division) {
            return response()->json(['message' => 'Divisi tidak ditemukan'], 404);
        }

        return response()->json([
            'message'  => 'Berhasil ambil skill divisi',
            'division' => $division->name,
            'data'     => $division->skills,
        ]);
    }

    // Simpan skill yang dimiliki user (bisa multiple)
    public function saveUserSkills(Request $request)
    {
        $request->validate([
            'skills'   => 'required|array|min:1',
            'skills.*' => 'string',
        ]);

        $user = auth()->user();
        $skillIds = [];

        foreach ($request->skills as $skillName) {
            // Kalau skill belum ada di master, auto insert
            $skill = Skill::firstOrCreate(['name' => $skillName]);
            $skillIds[] = $skill->id;
        }

        // Sync — otomatis hapus yang lama, isi yang baru
        $user->skills()->sync($skillIds);

        return response()->json([
            'message' => 'Skill berhasil disimpan',
            'data'    => $user->skills,
        ]);
    }

    // Ambil skill milik user yang sedang login
    public function getUserSkills()
    {
        $user = auth()->user()->load('skills');

        return response()->json([
            'message' => 'Berhasil ambil skill user',
            'data'    => $user->skills,
        ]);
    }
}