<?php

namespace App\Http\Controllers;

use App\Models\Recommendation;
use App\Models\DivisionProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RecommendationController extends Controller
{
    /**
     * M2 — Minta rekomendasi magang berdasarkan skill & passion divisi.
     * Alur: ambil data user → kirim ke Python NLP → simpan hasil → return ke FE.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'passion_division' => 'required|string', // misal: "Frontend", "Backend", dll
        ]);

        $user = auth()->user()->load('skills');

        if ($user->skills->isEmpty()) {
            return response()->json([
                'message' => 'Kamu belum menambahkan skill. Silakan lengkapi profil terlebih dahulu.',
            ], 422);
        }

        // Kumpulkan semua division profile yang ada (sudah approved)
        $divisionProfiles = DivisionProfile::with('division.company')
            ->whereHas('division.company', fn($q) => $q->where('is_verified', true))
            ->get();

        if ($divisionProfiles->isEmpty()) {
            return response()->json([
                'message' => 'Belum ada data divisi yang tersedia untuk rekomendasi.',
            ], 404);
        }

        // Siapkan payload untuk dikirim ke Python NLP service
        $payload = [
            'user_id'          => $user->id,
            'user_skills'      => $user->skills->pluck('name')->toArray(),
            'passion_division' => $request->passion_division,
            'division_profiles' => $divisionProfiles->map(fn($dp) => [
                'division_id'          => $dp->division_id,
                'division_name'        => $dp->division->name,
                'company_name'         => $dp->division->company->name,
                'combined_skills'      => $dp->combined_skills,
                'combined_experience'  => $dp->combined_experience,
                'avg_suitability'      => $dp->avg_suitability,   // ← skor rating relevansi
                'feedback_count'       => $dp->feedback_count,
            ])->toArray(),
        ];

        // Panggil Python NLP microservice
        try {
            $nlpResponse = Http::timeout(30)
                ->post(config('services.nlp.url') . '/recommend', $payload);

            if ($nlpResponse->failed()) {
                return response()->json(['message' => 'Gagal menghubungi NLP service'], 500);
            }

            $results = $nlpResponse->json('recommendations'); // array of top-5

        } catch (\Exception $e) {
            return response()->json(['message' => 'NLP service tidak tersedia: ' . $e->getMessage()], 503);
        }

        // Simpan hasil ke tabel recommendations (overwrite jika sudah ada)
        Recommendation::where('user_id', $user->id)->delete();

        $saved = [];
        foreach ($results as $item) {
            $saved[] = Recommendation::create([
                'user_id'            => $user->id,
                'division_id'        => $item['division_id'],
                'similarity_score'   => $item['similarity_score'],
                'suitability_avg'    => $item['suitability_avg'],
                'experience_summary' => $item['experience_summary'],
                'matched_skills'     => $item['matched_skills'], // akan di-cast ke JSON
            ]);
        }

        return response()->json([
            'message' => 'Rekomendasi berhasil dihasilkan',
            'data'    => Recommendation::where('user_id', $user->id)
                ->with('division.company')
                ->orderByDesc('similarity_score')
                ->get(),
        ]);
    }

    // M2 — Ambil hasil rekomendasi terakhir miliknya
    public function myRecommendations()
    {
        $recommendations = Recommendation::where('user_id', auth()->id())
            ->with('division.company')
            ->orderByDesc('similarity_score')
            ->get();

        return response()->json([
            'message' => 'Berhasil ambil rekomendasi',
            'data'    => $recommendations,
        ]);
    }
}