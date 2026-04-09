<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Division;
use App\Models\Feedback;
use App\Models\DivisionProfile;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    // M1 submit feedback
    public function store(Request $request)
    {
        $request->validate([
            'company_name'  => 'required|string',
            'division_name' => 'required|string',
            'location'      => 'required|string',
            'duration'      => 'required|in:<3,3-5,>5',
            'skills_used'   => 'required|array|min:1',
            'skills_used.*' => 'string',
            'experience'    => 'required|string',
            'suitability'   => 'required|integer|min:1|max:5',
        ]);

        // Auto-create company kalau belum ada
        $company = Company::firstOrCreate(
            ['name' => $request->company_name],
            [
                'is_verified' => false,
                'city'        => $request->location,
            ]
        );

        // Auto-create divisi kalau belum ada di company ini
        $division = Division::firstOrCreate(
            [
                'company_id' => $company->id,
                'name'       => $request->division_name,
            ]
        );

        // Simpan feedback
        $feedback = Feedback::create([
            'user_id'     => auth()->id(),
            'division_id' => $division->id,
            'status'      => 'pending',
            'skills_used' => implode(', ', $request->skills_used),
            'experience'  => $request->experience,
            'suitability' => $request->suitability,
            'duration'    => $request->duration,
            'location'    => $request->location,
        ]);

        return response()->json([
            'message' => 'Feedback berhasil dikirim, menunggu verifikasi admin',
            'data'    => $feedback->load('division.company'),
        ], 201);
    }

    // M1 lihat history feedback miliknya
    public function myFeedbacks()
    {
        $feedbacks = Feedback::where('user_id', auth()->id())
            ->with('division.company')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Berhasil ambil history feedback',
            'data'    => $feedbacks,
        ]);
    }

    // Admin — list semua feedback pending
    public function adminIndex(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $status    = $request->get('status', 'pending');
        $feedbacks = Feedback::where('status', $status)
            ->with(['user', 'division.company'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Berhasil ambil data feedback',
            'data'    => $feedbacks,
        ]);
    }

    // Admin — approve feedback
    public function approve($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $feedback = Feedback::with('division.company')->findOrFail($id);
        $feedback->update(['status' => 'approved']);

        // Verifikasi company sekalian
        $feedback->division->company->update(['is_verified' => true]);

        // Rebuild division profile
        $this->rebuildDivisionProfile($feedback->division_id);

        return response()->json([
            'message' => 'Feedback disetujui',
            'data'    => $feedback,
        ]);
    }

    // Admin — reject feedback
    public function reject(Request $request, $id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $request->validate([
            'reject_reason' => 'required|string',
        ]);

        $feedback = Feedback::findOrFail($id);
        $feedback->update([
            'status'        => 'rejected',
            'reject_reason' => $request->reject_reason,
        ]);

        return response()->json([
            'message' => 'Feedback ditolak',
            'data'    => $feedback,
        ]);
    }

    // Sistem — rebuild division profile setelah ada feedback approved
    private function rebuildDivisionProfile($divisionId)
    {
        $approvedFeedbacks = Feedback::where('division_id', $divisionId)
            ->where('status', 'approved')
            ->get();

        if ($approvedFeedbacks->isEmpty()) return;

        $combinedSkills     = $approvedFeedbacks->pluck('skills_used')->implode(' ');
        $combinedExperience = $approvedFeedbacks->pluck('experience')->implode(' ');
        $avgSuitability     = $approvedFeedbacks->avg('suitability');
        $feedbackCount      = $approvedFeedbacks->count();

        DivisionProfile::updateOrCreate(
            ['division_id' => $divisionId],
            [
                'combined_skills'      => $combinedSkills,
                'combined_experience'  => $combinedExperience,
                'avg_suitability'      => round($avgSuitability, 2),
                'feedback_count'       => $feedbackCount,
            ]
        );
    }
}