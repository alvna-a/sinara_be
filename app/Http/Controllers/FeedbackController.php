<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Division;
use App\Models\Feedback;
use App\Models\DivisionProfile;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    // =========================================================================
    // ALUMNI (M1) — Submit feedback pengalaman magang
    // =========================================================================

    public function store(Request $request)
    {
        $request->validate([
            'company_name'  => 'required|string|max:255',
            'division_name' => 'required|string|max:255',
            'location'      => 'required|string|max:255',
            'duration'      => 'required|in:<3,3-5,>5',
            'skills_used'   => 'required|array|min:1',
            'skills_used.*' => 'string|max:100',
            'suitability'   => 'required|integer|min:1|max:5',
            // Wajib diisi — digunakan NLP untuk penalty score
            'rating_reason' => 'required|string|min:20|max:1000',
            'experience'    => 'required|string|min:20',
            // Jobdesk / project yang dilakukan (sesuai Step 3 FE)
            'jobdesk'       => 'required|array|min:1',
            'jobdesk.*'     => 'string|max:100',
        ], [
            'company_name.required'  => 'Nama perusahaan wajib diisi.',
            'division_name.required' => 'Divisi/posisi magang wajib diisi.',
            'location.required'      => 'Lokasi wajib diisi.',
            'duration.required'      => 'Durasi magang wajib dipilih.',
            'duration.in'            => 'Durasi tidak valid.',
            'skills_used.required'   => 'Tambahkan minimal 1 skill.',
            'skills_used.min'        => 'Tambahkan minimal 1 skill.',
            'suitability.required'   => 'Tingkat kesesuaian wajib dipilih.',
            'suitability.min'        => 'Nilai kesesuaian minimal 1.',
            'suitability.max'        => 'Nilai kesesuaian maksimal 5.',
            'rating_reason.required' => 'Alasan penilaian wajib diisi.',
            'rating_reason.min'      => 'Alasan penilaian minimal 20 karakter.',
            'experience.required'    => 'Pengalaman & ringkasan wajib diisi.',
            'experience.min'         => 'Pengalaman minimal 20 karakter.',
            'jobdesk.required'       => 'Tambahkan minimal 1 jobdesk/project.',
            'jobdesk.min'            => 'Tambahkan minimal 1 jobdesk/project.',
        ]);

        // Pastikan hanya role 'alumni' yang bisa submit
        if (auth()->user()->role !== 'alumni') {
            return response()->json([
                'message' => 'Hanya alumni magang yang dapat mengirim feedback.',
            ], 403);
        }

        // Auto-create company jika belum ada (case-insensitive check)
        $company = Company::whereRaw('LOWER(name) = ?', [strtolower($request->company_name)])
            ->first();

        if (!$company) {
            $company = Company::create([
                'name'        => $request->company_name,
                'is_verified' => false,
                'city'        => $request->location,
            ]);
        }

        // Auto-create division jika belum ada di company ini
        $division = Division::firstOrCreate([
            'company_id' => $company->id,
            'name'       => $request->division_name,
        ]);

        // Simpan feedback
        $feedback = Feedback::create([
            'user_id'       => auth()->id(),
            'division_id'   => $division->id,
            'status'        => 'pending',
            'skills_used'   => implode(', ', $request->skills_used),
            'experience'    => $request->experience,
            'suitability'   => $request->suitability,
            'rating_reason' => $request->rating_reason,
            'jobdesk'       => implode(', ', $request->jobdesk),
            'duration'      => $request->duration,
            'location'      => $request->location,
        ]);

        return response()->json([
            'message' => 'Feedback berhasil dikirim, menunggu verifikasi admin.',
            'data'    => $feedback->load('division.company'),
        ], 201);
    }

    // =========================================================================
    // ALUMNI (M1) — Lihat history feedback miliknya
    // =========================================================================

    public function myFeedbacks()
    {
        $feedbacks = Feedback::where('user_id', auth()->id())
            ->with('division.company')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($fb) {
                return [
                    'id'            => $fb->id,
                    'company_name'  => $fb->division->company->name ?? '-',
                    'division_name' => $fb->division->name ?? '-',
                    'suitability'   => $fb->suitability,
                    'status'        => $fb->status,
                    'reject_reason' => $fb->reject_reason,
                    'skills_used'   => explode(', ', $fb->skills_used),
                    'jobdesk'       => explode(', ', $fb->jobdesk),
                    'experience'    => $fb->experience,
                    'rating_reason' => $fb->rating_reason,
                    'duration'      => $fb->duration,
                    'location'      => $fb->location,
                    'created_at'    => $fb->created_at->format('d M Y'),
                ];
            });

        return response()->json([
            'message' => 'Berhasil ambil history feedback',
            'data'    => $feedbacks,
        ]);
    }

    // =========================================================================
    // ADMIN — List semua feedback dengan filter status
    // =========================================================================

    public function adminIndex(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $status    = $request->get('status', 'pending'); // pending | approved | rejected
        $feedbacks = Feedback::where('status', $status)
            ->with(['user', 'division.company'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($fb) {
                return [
                    'id'            => $fb->id,
                    'user_name'     => $fb->user->name ?? '-',
                    'user_nim'      => $fb->user->nim ?? '-',
                    'company_name'  => $fb->division->company->name ?? '-',
                    'division_name' => $fb->division->name ?? '-',
                    'suitability'   => $fb->suitability,
                    'rating_reason' => $fb->rating_reason,
                    'skills_used'   => explode(', ', $fb->skills_used),
                    'jobdesk'       => explode(', ', $fb->jobdesk ?? ''),
                    'experience'    => $fb->experience,
                    'duration'      => $fb->duration,
                    'location'      => $fb->location,
                    'status'        => $fb->status,
                    'reject_reason' => $fb->reject_reason,
                    'created_at'    => $fb->created_at->format('d M Y'),
                ];
            });

        return response()->json([
            'message' => 'Berhasil ambil data feedback',
            'data'    => $feedbacks,
        ]);
    }

    // =========================================================================
    // ADMIN — Approve feedback + rebuild division profile
    // =========================================================================

    public function approve($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $feedback = Feedback::with('division.company')->findOrFail($id);

        if ($feedback->status === 'approved') {
            return response()->json(['message' => 'Feedback sudah pernah disetujui.'], 422);
        }

        $feedback->update(['status' => 'approved']);

        // Verifikasi company sekalian
        $feedback->division->company->update(['is_verified' => true]);

        // Rebuild division profile (untuk NLP)
        $this->rebuildDivisionProfile($feedback->division_id);

        return response()->json([
            'message' => 'Feedback disetujui dan data divisi diperbarui.',
            'data'    => $feedback,
        ]);
    }

    // =========================================================================
    // ADMIN — Reject feedback
    // =========================================================================

    public function reject(Request $request, $id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $request->validate([
            'reject_reason' => 'required|string|min:5',
        ], [
            'reject_reason.required' => 'Alasan penolakan wajib diisi.',
            'reject_reason.min'      => 'Alasan penolakan minimal 5 karakter.',
        ]);

        $feedback = Feedback::findOrFail($id);

        if ($feedback->status === 'rejected') {
            return response()->json(['message' => 'Feedback sudah pernah ditolak.'], 422);
        }

        $feedback->update([
            'status'        => 'rejected',
            'reject_reason' => $request->reject_reason,
        ]);

        return response()->json([
            'message' => 'Feedback ditolak.',
            'data'    => $feedback,
        ]);
    }

    // =========================================================================
    // PRIVATE — Rebuild DivisionProfile setelah ada feedback baru diapprove
    // Digunakan oleh Python NLP untuk membangun dokumen divisi.
    // =========================================================================

    private function rebuildDivisionProfile(int $divisionId): void
    {
        $approvedFeedbacks = Feedback::where('division_id', $divisionId)
            ->where('status', 'approved')
            ->get();

        if ($approvedFeedbacks->isEmpty()) return;

        // Gabungkan semua teks (NLP akan memproses ini)
        $combinedSkills     = $approvedFeedbacks->pluck('skills_used')->implode(' ');
        $combinedExperience = $approvedFeedbacks->pluck('experience')->implode(' ');
        $combinedJobdesk    = $approvedFeedbacks->pluck('jobdesk')->filter()->implode(' ');
        $combinedReasons    = $approvedFeedbacks->pluck('rating_reason')->implode(' ');

        $avgSuitability  = $approvedFeedbacks->avg('suitability');
        $feedbackCount   = $approvedFeedbacks->count();

        // Hitung penalty_score: proporsi review dengan suitability rendah (1 atau 2)
        // NLP akan mengalikan similarity_score dengan (1 - penalty_score)
        // Sehingga divisi yang sering dilaporkan tidak relevan akan muncul di ranking bawah
        $lowRatingCount = $approvedFeedbacks->where('suitability', '<=', 2)->count();
        $penaltyScore   = $feedbackCount > 0
            ? round($lowRatingCount / $feedbackCount, 2)
            : 0.0;

        DivisionProfile::updateOrCreate(
            ['division_id' => $divisionId],
            [
                'combined_skills'      => $combinedSkills,
                'combined_experience'  => $combinedExperience,
                'combined_jobdesk'     => $combinedJobdesk,   // kolom baru
                'combined_reasons'     => $combinedReasons,   // kolom baru
                'avg_suitability'      => round($avgSuitability, 2),
                'feedback_count'       => $feedbackCount,
                'penalty_score'        => $penaltyScore,       // kolom baru
            ]
        );
    }
}