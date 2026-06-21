<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;

class DataMahasiswaController extends Controller
{
    /**
     * Maksimal feedback yang ditampilkan di FeedbackBar (FE: maxFeedback).
     * Disamakan dengan nilai dummy FE (12) — silakan sesuaikan kalau ada
     * aturan bisnis lain (misal target feedback per mahasiswa per semester).
     */
    private const MAX_FEEDBACK = 12;

    /**
     * Aturan kapan mahasiswa dianggap "sudah magang" (Mhs1 / alumni),
     * berdasarkan program studi + semester. Ini dipakai HANYA kalau
     * mahasiswa tidak punya internship_override manual dari admin.
     *
     * - D3-Teknik Informatika           : semester 1-5 belum magang, semester 6+ sudah magang
     * - D4-Teknologi Rekayasa Komputer  : semester 1-7 belum magang, semester 8+ sudah magang
     */
    private const INTERNSHIP_THRESHOLD = [
        'D3-Teknik Informatika' => 6,
        'D4-Teknologi Rekayasa Komputer' => 8,
    ];

    /**
     * Hitung status magang OTOMATIS dari program studi + semester.
     * Return true = sudah magang (alumni / Mhs1), false = belum magang (calon / Mhs2).
     *
     * Kalau program studi tidak dikenali / semester kosong, default ke
     * "belum magang" supaya tidak salah menandai mahasiswa sebagai alumni.
     */
    private function calculateAutoInternshipStatus(?string $programStudi, $semester): bool
    {
        $threshold = self::INTERNSHIP_THRESHOLD[$programStudi] ?? null;

        if (!$threshold || $semester === null || $semester === '') {
            return false;
        }

        return (int) $semester >= $threshold;
    }

    /**
     * Tentukan status magang EFEKTIF seorang mahasiswa:
     * pakai override manual admin kalau ada, kalau tidak baru hitung otomatis.
     *
     * Return: ['is_alumni' => bool, 'source' => 'manual'|'otomatis']
     */
    private function resolveInternshipStatus(UserProfile $profile): array
    {
        if ($profile->internship_override === 'sudah_magang') {
            return ['is_alumni' => true, 'source' => 'manual'];
        }

        if ($profile->internship_override === 'belum_magang') {
            return ['is_alumni' => false, 'source' => 'manual'];
        }

        return [
            'is_alumni' => $this->calculateAutoInternshipStatus($profile->program_studi, $profile->semester),
            'source' => 'otomatis',
        ];
    }

    /**
     * Pastikan role di tabel `users` (enum: alumni|calon|admin) selalu
     * sinkron dengan status magang efektif. Dipanggil setiap kali admin
     * melakukan switch atau reset, supaya field role lama (yang dipakai
     * AuthController, FeedbackController, dll) tidak basi.
     */
    private function syncUserRole(User $user, bool $isAlumni): void
    {
        if ($user->role === 'admin') {
            return; // jangan sentuh akun admin
        }

        $user->update(['role' => $isAlumni ? 'alumni' : 'calon']);
    }

    /**
     * Pastikan request berasal dari admin. Mengikuti pola yang sudah
     * dipakai di FeedbackController / CompanyController.
     */
    private function ensureAdmin()
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }
        return null;
    }

    // =========================================================================
    // GET /admin/data-mahasiswa
    // List + stats + search + filter + sort — sesuai tampilan FE.
    // =========================================================================
    public function index(Request $request)
    {
        if ($denied = $this->ensureAdmin()) {
            return $denied;
        }

        $search    = trim((string) $request->get('search', ''));
        $roleFilter = $request->get('role', 'all');       // all | mhs1 | mhs2
        $prodiFilter = $request->get('program_studi', 'all'); // all | <nama prodi>
        $angkatanFilter = $request->get('angkatan', 'all');   // all | <tahun>
        $sortBy = $request->get('sort', 'desc');           // desc | asc | name

        $users = User::where('role', '!=', 'admin')
            ->with('profile')
            ->withCount('feedbacks')
            ->get();

        $mapped = $users->map(function (User $user) {
            // Fallback ke UserProfile kosong kalau mahasiswa belum punya
            // baris profil sama sekali, supaya akses ->nim / ->program_studi
            // di bawah tidak error null property access.
            $profile = $user->profile ?? new UserProfile();
            $status = $this->resolveInternshipStatus($profile);

            // "Angkatan" tidak ada kolom eksplisit di skema saat ini, jadi
            // dipakai tahun akun dibuat sebagai pendekatan. Kalau nanti ada
            // kolom angkatan/tahun_masuk eksplisit, ganti baris ini.
            $angkatanYear = $user->created_at ? $user->created_at->format('Y') : null;

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'nim' => $profile->nim ?? $user->nim,
                'is_alumni' => $status['is_alumni'],
                'role_source' => $status['source'], // 'manual' | 'otomatis'
                'role_type' => $status['is_alumni'] ? 'mhs1' : 'mhs2',
                'role_label' => $status['is_alumni'] ? 'Alumni Magang (Mhs1)' : 'Pencari Magang (Mhs2)',
                'program_studi' => $profile->program_studi ?? null,
                'semester' => $profile->semester ?? null,
                'angkatan' => $angkatanYear,
                'cohort_label' => $angkatanYear ? "Angkatan {$angkatanYear}" : '-',
                // "Aktif" mengikuti status verifikasi email; sesuaikan kalau
                // ada definisi aktif/pasif lain (misal last_login_at).
                'status' => $user->email_verified_at ? 'Aktif' : 'Pasif',
                'feedback_count' => $user->feedbacks_count,
                'max_feedback' => self::MAX_FEEDBACK,
            ];
        });

        // ----- Search (nama / email) -----
        if ($search !== '') {
            $q = mb_strtolower($search);
            $mapped = $mapped->filter(function ($s) use ($q) {
                return str_contains(mb_strtolower($s['name']), $q)
                    || str_contains(mb_strtolower($s['email']), $q);
            });
        }

        // ----- Filter role -----
        if ($roleFilter !== 'all') {
            $mapped = $mapped->filter(fn ($s) => $s['role_type'] === $roleFilter);
        }

        // ----- Filter prodi -----
        if ($prodiFilter !== 'all') {
            $mapped = $mapped->filter(fn ($s) => $s['program_studi'] === $prodiFilter);
        }

        // ----- Filter angkatan -----
        if ($angkatanFilter !== 'all') {
            $mapped = $mapped->filter(fn ($s) => $s['angkatan'] === (string) $angkatanFilter);
        }

        // ----- Sort -----
        $mapped = match ($sortBy) {
            'asc' => $mapped->sortBy('feedback_count')->values(),
            'name' => $mapped->sortBy(fn ($s) => mb_strtolower($s['name']))->values(),
            default => $mapped->sortByDesc('feedback_count')->values(),
        };

        // ----- Stats (dihitung dari keseluruhan data, bukan hasil filter) -----
        $statsBase = $users->map(function (User $user) {
            $status = $this->resolveInternshipStatus($user->profile ?? new UserProfile());
            return $status['is_alumni'];
        });

        $stats = [
            'total_mahasiswa' => $users->count(),
            'total_mhs1' => $statsBase->filter(fn ($isAlumni) => $isAlumni)->count(),
            'total_mhs2' => $statsBase->filter(fn ($isAlumni) => !$isAlumni)->count(),
            'total_feedback' => $users->sum('feedbacks_count'),
        ];

        return response()->json([
            'message' => 'Berhasil ambil data mahasiswa',
            'stats' => $stats,
            'data' => $mapped,
        ]);
    }

    // =========================================================================
    // GET /admin/data-mahasiswa/{id}
    // Detail satu mahasiswa (dipakai modal/halaman detail di FE kalau perlu).
    // =========================================================================
    public function show($id)
    {
        if ($denied = $this->ensureAdmin()) {
            return $denied;
        }

        $user = User::where('role', '!=', 'admin')
            ->with('profile')
            ->withCount('feedbacks')
            ->findOrFail($id);

        $profile = $user->profile ?? new UserProfile();
        $status = $this->resolveInternshipStatus($profile);

        return response()->json([
            'message' => 'Berhasil ambil detail mahasiswa',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'nim' => $profile->nim ?? $user->nim,
                'program_studi' => $profile->program_studi ?? null,
                'semester' => $profile->semester ?? null,
                'is_alumni' => $status['is_alumni'],
                'role_source' => $status['source'],
                'feedback_count' => $user->feedbacks_count,
            ],
        ]);
    }

    // =========================================================================
    // POST /admin/data-mahasiswa/{id}/switch-role
    // Fitur INTI yang diminta: admin override manual status magang mahasiswa.
    // Body: { "status": "belum_magang" | "sudah_magang" }
    // =========================================================================
    public function switchRole(Request $request, $id)
    {
        if ($denied = $this->ensureAdmin()) {
            return $denied;
        }

        $request->validate([
            'status' => 'required|in:belum_magang,sudah_magang',
        ], [
            'status.required' => 'Status magang wajib dipilih.',
            'status.in' => 'Status magang tidak valid.',
        ]);

        $user = User::where('role', '!=', 'admin')->findOrFail($id);

        $profile = UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            ['internship_override' => $request->status]
        );

        $isAlumni = $request->status === 'sudah_magang';
        $this->syncUserRole($user, $isAlumni);

        return response()->json([
            'message' => "Status mahasiswa berhasil diubah menjadi " . ($isAlumni ? 'Alumni Magang (Mhs1)' : 'Pencari Magang (Mhs2)') . " secara manual.",
            'data' => [
                'id' => $user->id,
                'is_alumni' => $isAlumni,
                'role_source' => 'manual',
            ],
        ]);
    }

    // =========================================================================
    // POST /admin/data-mahasiswa/{id}/reset-role
    // Hapus override manual, kembalikan ke perhitungan OTOMATIS dari semester+prodi.
    // =========================================================================
    public function resetRoleOverride($id)
    {
        if ($denied = $this->ensureAdmin()) {
            return $denied;
        }

        $user = User::where('role', '!=', 'admin')->findOrFail($id);
        $profile = $user->profile;

        if (!$profile) {
            return response()->json(['message' => 'Profil mahasiswa belum lengkap.'], 422);
        }

        $profile->update(['internship_override' => null]);

        // Hitung ulang status otomatis lalu sinkronkan ke kolom role di users.
        $isAlumni = $this->calculateAutoInternshipStatus($profile->program_studi, $profile->semester);
        $this->syncUserRole($user, $isAlumni);

        return response()->json([
            'message' => 'Status mahasiswa dikembalikan ke mode otomatis berdasarkan semester.',
            'data' => [
                'id' => $user->id,
                'is_alumni' => $isAlumni,
                'role_source' => 'otomatis',
            ],
        ]);
    }
}
