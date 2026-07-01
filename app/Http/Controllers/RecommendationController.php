<?php

namespace App\Http\Controllers;

use App\Models\Recommendation;
use App\Models\DivisionProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RecommendationController extends Controller
{
    // ─── Generate rekomendasi baru — tiap panggil = sesi baru ────────────────
    public function generate(Request $request)
    {
        $request->validate([
            'passion_division' => 'required|string',
        ]);

        $user = auth()->user()->load('skills');

        if ($user->skills->isEmpty()) {
            return response()->json([
                'message' => 'Kamu belum menambahkan skill. Silakan lengkapi profil terlebih dahulu.',
            ], 422);
        }

        $divisionProfiles = DivisionProfile::with('division.company')
            ->whereHas('division.company', fn ($q) => $q->where('is_verified', true))
            ->get();

        if ($divisionProfiles->isEmpty()) {
            return response()->json([
                'message' => 'Belum ada data divisi yang tersedia untuk rekomendasi.',
            ], 404);
        }

        $payload = [
            'user_id'           => $user->id,
            'user_skills'       => $user->skills->pluck('name')->toArray(),
            'passion_division'  => $request->passion_division,
            'division_profiles' => $divisionProfiles->map(fn ($dp) => [
                'division_id'         => $dp->division_id,
                'division_name'       => $dp->division->name,
                'company_name'        => $dp->division->company->name,
                'combined_skills'     => $dp->combined_skills,
                'combined_experience' => $dp->combined_experience,
                'avg_suitability'     => $dp->avg_suitability,
                'feedback_count'      => $dp->feedback_count,
            ])->toArray(),
        ];

        try {
            $nlpResponse = Http::timeout(30)
                ->post(config('services.nlp.url') . '/recommend', $payload);

            if ($nlpResponse->failed()) {
                return response()->json(['message' => 'Gagal menghubungi NLP service'], 500);
            }

            $results = $nlpResponse->json('recommendations');
        } catch (\Exception $e) {
            return response()->json(['message' => 'NLP service tidak tersedia: ' . $e->getMessage()], 503);
        }

        // UUID unik untuk sesi ini — semua top-5 hasil share key yang sama
        $sessionKey   = (string) Str::uuid();
        $sessionLabel = 'Rekomendasi Magang — Profil ' . $request->passion_division;

        // TIDAK hapus data lama — tiap generate = sesi baru yang tersimpan
        $saved = [];
        foreach ($results as $item) {
            $saved[] = Recommendation::create([
                'user_id'            => $user->id,
                'division_id'        => $item['division_id'],
                'session_key'        => $sessionKey,
                'session_label'      => $sessionLabel,
                'passion_division'   => $request->passion_division,
                'similarity_score'   => $item['similarity_score'],
                'suitability_avg'    => $item['suitability_avg'],
                'experience_summary' => $item['experience_summary'],
                'matched_skills'     => $item['matched_skills'],
            ]);
        }

        return response()->json([
            'message'     => 'Rekomendasi berhasil dihasilkan',
            'session_key' => $sessionKey,
            'data'        => collect($saved)->load('division.company'),
        ]);
    }

    // ─── List semua sesi milik user (Halaman Riwayat — gambar 4) ─────────────
    public function mySessions()
    {
        // Ambil 1 baris per session_key (yang paling awal), lalu hitung agregat
        $sessions = Recommendation::where('user_id', auth()->id())
            ->selectRaw('
                session_key,
                session_label,
                passion_division,
                MIN(created_at) as created_at,
                COUNT(*) as total_divisi,
                SUM(CASE WHEN similarity_score >= 0.75 THEN 1 ELSE 0 END) as sangat_cocok
            ')
            ->groupBy('session_key', 'session_label', 'passion_division')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'message' => 'Berhasil ambil riwayat sesi rekomendasi',
            'data'    => $sessions->map(fn ($s) => [
                'session_key'      => $s->session_key,
                'label'            => $s->session_label,
                'passion_division' => $s->passion_division,
                'created_at'       => $s->created_at,
                'total_divisi'     => (int) $s->total_divisi,
                'sangat_cocok'     => (int) $s->sangat_cocok,
            ]),
        ]);
    }

    // ─── List rekomendasi dalam 1 sesi (Ranked List — gambar 2) ──────────────
    public function showSession($sessionKey)
    {
        $items = Recommendation::where('user_id', auth()->id())
            ->where('session_key', $sessionKey)
            ->with(['division.company', 'division.skills'])
            ->orderByDesc('similarity_score')
            ->get();

        if ($items->isEmpty()) {
            return response()->json(['message' => 'Sesi tidak ditemukan'], 404);
        }

        $first = $items->first();

        return response()->json([
            'message' => 'Berhasil ambil hasil rekomendasi sesi ini',
            'session' => [
                'session_key' => $first->session_key,
                'label'       => $first->session_label,
                'created_at'  => $first->created_at,
            ],
            'data' => $items->values()->map(function ($rek, $idx) {
                $division      = $rek->division;
                $location      = collect([$division->company->city, $division->company->province])->filter()->join(', ');
                $requiredSkills = $division->skills->pluck('name')->toArray();
                $matchedSkills  = $rek->matched_skills ?? [];
                $missingSkills  = array_values(array_diff($requiredSkills, $matchedSkills));

                return [
                    'id'               => $rek->id,
                    'rank'             => $idx + 1,
                    'division_id'      => $division->id,
                    'division_name'    => $division->name,
                    'company_name'     => $division->company->name,
                    'company_id'       => $division->company->id,
                    'location'         => $location,
                    'similarity_score' => $rek->similarity_score,
                    'suitability_avg'  => $rek->suitability_avg,
                    'matched_skills'   => $matchedSkills,
                    'missing_skills'   => array_slice($missingSkills, 0, 3),
                ];
            }),
        ]);
    }

    // ─── Detail 1 rekomendasi (Detail page — gambar 1) ───────────────────────
    public function show($id)
    {
        $rek = Recommendation::where('user_id', auth()->id())
            ->with(['division.company', 'division.skills', 'division.profile'])
            ->find($id);

        if (!$rek) {
            return response()->json(['message' => 'Rekomendasi tidak ditemukan'], 404);
        }

        $division       = $rek->division;
        $requiredSkills = $division->skills->pluck('name')->toArray();
        $matchedSkills  = $rek->matched_skills ?? [];
        $missingSkills  = array_values(array_diff($requiredSkills, $matchedSkills));

        $areaProject = [];
        if ($division->profile && $division->profile->combined_jobdesk) {
            $areaProject = array_values(array_filter(
                array_map('trim', explode(',', $division->profile->combined_jobdesk))
            ));
        }

        $reviews = $division->feedbacks()
            ->where('status', 'approved')
            ->with('user:id,name')
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($fb) => [
                'id'        => $fb->id,
                'user_name' => $fb->user->name ?? 'Alumni',
                'rating'    => $fb->suitability ?? 0,
                'komentar'  => $fb->rating_reason,
                'duration'  => $fb->duration,
                'period'    => $fb->created_at->format('M Y'),
            ]);

        $location = collect([$division->company->city, $division->company->province])->filter()->join(', ');

        return response()->json([
            'message' => 'Berhasil ambil detail rekomendasi',
            'data'    => [
                'id'                 => $rek->id,
                'session_key'        => $rek->session_key,
                'division_id'        => $division->id,
                'division_name'      => $division->name,
                'company_name'       => $division->company->name,
                'company_id'         => $division->company->id,
                'location'           => $location,
                'similarity_score'   => $rek->similarity_score,
                'suitability_avg'    => $rek->suitability_avg,
                'experience_summary' => $rek->experience_summary,
                'matched_skills'     => $matchedSkills,
                'missing_skills'     => $missingSkills,
                'total_skills'       => count($requiredSkills),
                'area_project'       => $areaProject,
                'reviews'            => $reviews,
            ],
        ]);
    }
}