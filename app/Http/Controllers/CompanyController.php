<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    // Ambil semua perusahaan yang sudah terverifikasi (untuk tampil di FE)
    public function index(Request $request)
    {
        $query = Company::where('is_verified', true)
            ->with(['divisions.feedbacks' => function ($q) {
                $q->where('status', 'approved');
            }]);

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $companies = $query->orderBy('name')->get();

        return response()->json([
            'message' => 'Berhasil ambil data perusahaan',
            'data'    => $companies->map(fn ($c) => $this->formatCompany($c)),
        ]);
    }

    // Ambil detail perusahaan beserta divisi & profile-nya
    public function show($id)
    {
        $company = Company::with([
                'divisions.profile',
                'divisions.feedbacks' => function ($q) {
                    $q->where('status', 'approved');
                },
            ])->find($id);

        if (!$company) {
            return response()->json(['message' => 'Perusahaan tidak ditemukan'], 404);
        }

        return response()->json([
            'message' => 'Berhasil ambil detail perusahaan',
            'data'    => $this->formatCompany($company, detailed: true),
        ]);
    }

    /**
     * Bentuk ulang model Company jadi array sesuai bentuk yang dibutuhkan FE,
     * lengkap dengan field hasil agregasi (avg_rating, total_review, dst)
     * yang tidak ada di kolom database aslinya.
     */
    private function formatCompany(Company $company, bool $detailed = false): array
    {
        $allFeedbacks = $company->divisions->flatMap(fn ($d) => $d->feedbacks);

        $avgRating      = $allFeedbacks->count() > 0 ? round($allFeedbacks->avg('suitability'), 1) : 0;
        $totalMahasiswa = $allFeedbacks->pluck('user_id')->unique()->count();

        $divisions = $company->divisions->map(function ($division) use ($detailed) {
            $feedbacks    = $division->feedbacks;
            $divAvg       = $feedbacks->count() > 0 ? round($feedbacks->avg('suitability'), 1) : 0;
            $divMahasiswa = $feedbacks->pluck('user_id')->unique()->count();

            $base = [
                'id'   => $division->id,
                'name' => $division->name,
            ];

            if ($detailed) {
                $base['description']     = $division->profile->combined_jobdesk ?? 'Belum ada deskripsi untuk divisi ini.';
                $base['total_mahasiswa'] = $divMahasiswa;
                $base['avg_rating']      = $divAvg;
                $base['total_testimoni'] = $feedbacks->count();
                $base['highlight_quote'] = optional($feedbacks->first())->rating_reason ?? '';
            }

            return $base;
        });

        $result = [
            'id'              => $company->id,
            'name'            => $company->name,
            'full_name'       => $company->name,
            'logo_url'        => null,
            'industri'        => optional($company->divisions->first())->name ?? '-',
            'kota'            => $company->city,
            'total_mahasiswa' => $totalMahasiswa,
            'avg_rating'      => $avgRating,
            'divisions'       => $divisions,
        ];

        if ($detailed) {
            $result['total_review'] = $allFeedbacks->count();
            $result['total_divisi'] = $company->divisions->count();
        }

        return $result;
    }

    // Admin — list semua perusahaan (termasuk yang belum verified)
    public function adminIndex(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $companies = Company::with('divisions')
            ->orderBy('is_verified')
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Berhasil ambil semua perusahaan',
            'data'    => $companies,
        ]);
    }

    // Admin — update data perusahaan (nama, kota, provinsi, alamat)
    public function update(Request $request, $id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $request->validate([
            'name'     => 'nullable|string',
            'city'     => 'nullable|string',
            'province' => 'nullable|string',
            'address'  => 'nullable|string',
        ]);

        $company = Company::findOrFail($id);
        $company->update($request->only(['name', 'city', 'province', 'address']));

        return response()->json([
            'message' => 'Berhasil update perusahaan',
            'data'    => $company,
        ]);
    }
}