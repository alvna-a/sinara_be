<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RecommendationController extends Controller
{
    /**
     * URL FastAPI NLP service.
     * Taruh di .env: NLP_SERVICE_URL=http://localhost:8000
     */
    private string $nlpUrl;

    public function __construct()
    {
        $this->nlpUrl = rtrim(env('NLP_SERVICE_URL', 'http://localhost:8000'), '/');
    }

    /**
     * POST /api/recommend
     *
     * Body JSON:
     *   { "skills": "React, Laravel, MySQL", "top_n": 5 }
     *
     * Dipanggil dari Next.js FE.
     */
    public function recommend(Request $request)
    {
        $request->validate([
            'skills' => 'required|string|min:2',
            'top_n'  => 'sometimes|integer|min:1|max:20',
        ]);

        try {
            $response = Http::timeout(15)
                ->post("{$this->nlpUrl}/recommend", [
                    'skills' => $request->skills,
                    'top_n'  => $request->input('top_n', 5),
                ]);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'NLP service error',
                    'detail'  => $response->json('detail') ?? 'Unknown error',
                ], $response->status());
            }

            return response()->json([
                'message' => 'Rekomendasi berhasil',
                'data'    => $response->json(),
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json([
                'message' => 'NLP service tidak dapat dijangkau',
                'detail'  => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * GET /api/nlp/health
     * Cek apakah NLP service aktif (berguna untuk debugging di production).
     */
    public function health()
    {
        try {
            $response = Http::timeout(5)->get("{$this->nlpUrl}/health");
            return response()->json($response->json(), $response->status());
        } catch (\Exception $e) {
            return response()->json(['status' => 'unreachable'], 503);
        }
    }
}
