<?php

namespace App\Http\Controllers;

use App\Models\Division;
use Illuminate\Http\Request;

class DivisionController extends Controller
{    // Detail divisi + list review-nya (untuk halaman /perusahaan/{id}/divisi/{id})
    public function show($companyId, $divisionId)
{
    $division = Division::with('company')
        ->where('company_id', $companyId)
        ->find($divisionId);

    if (!$division) {
        return response()->json(['message' => 'Divisi tidak ditemukan'], 404);
    }

    $feedbacks = $division->feedbacks()
        ->with('user.profile')
        ->where('status', 'approved')
        ->latest()
        ->get();

    $avgRating = $feedbacks->count() > 0 ? round($feedbacks->avg('suitability'), 1) : 0;

    // Hitung skill yang paling sering disebut di semua review divisi ini
    $skillCounts = [];
    foreach ($feedbacks as $fb) {
        foreach (explode(', ', $fb->skills_used) as $skill) {
            $skill = trim($skill);
            if ($skill === '') continue;
            $skillCounts[$skill] = ($skillCounts[$skill] ?? 0) + 1;
        }
    }
    arsort($skillCounts);
    $topSkill = array_key_first($skillCounts) ?? '-';
    $topSkillCount = $skillCounts[$topSkill] ?? 0;

    $durationLabel = fn ($d) => match ($d) {
        '<3'  => '< 3 bulan',
        '3-5' => '3-5 bulan',
        '>5'  => '> 5 bulan',
        default => $d,
    };

    return response()->json([
        'message' => 'Berhasil ambil detail divisi',
        'data' => [
            'id'               => $division->id,
            'name'             => $division->name,
            'company_id'       => $division->company_id,
            'company_name'     => $division->company->name,
            'total_review'     => $feedbacks->count(),
            'avg_rating'       => $avgRating,
            'top_skill'        => $topSkill,
            'top_skill_detail' => $feedbacks->count() > 0
                ? "Disebutkan di {$topSkillCount} dari {$feedbacks->count()} review"
                : 'Belum ada review',
            'feedbacks' => $feedbacks->map(function ($fb) use ($durationLabel) {
                $nim = $fb->user->nim ?? '';
                $angkatan = strlen($nim) >= 2 ? '20' . substr($nim, 0, 2) : '-';

                return [
                    'id'                => $fb->id,
                    'user_name'         => $fb->user->name ?? 'Anonim',
                    'user_role'         => $fb->user->profile->program_studi ?? 'Mahasiswa',
                    'angkatan'          => $angkatan,
                    'durasi'            => $durationLabel($fb->duration),
                    'rating'            => $fb->suitability,
                    'komentar'          => $fb->experience,
                    'skills_dibutuhkan' => array_map('trim', explode(', ', $fb->skills_used)),
                ];
            }),
        ],
    ]);
}
}