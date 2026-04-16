<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    // Ambil semua perusahaan yang sudah terverifikasi (untuk tampil di FE)
    public function index(Request $request)
    {
        $query = Company::where('is_verified', true)->with('divisions');

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $companies = $query->orderBy('name')->get();

        return response()->json([
            'message' => 'Berhasil ambil data perusahaan',
            'data'    => $companies,
        ]);
    }

    // Ambil detail perusahaan beserta divisi & profile-nya
    public function show($id)
    {
        $company = Company::with(['divisions.profile', 'divisions.feedbacks'])->find($id);

        if (!$company) {
            return response()->json(['message' => 'Perusahaan tidak ditemukan'], 404);
        }

        return response()->json([
            'message' => 'Berhasil ambil detail perusahaan',
            'data'    => $company,
        ]);
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
            'message' => 'Perusahaan berhasil diupdate',
            'data'    => $company,
        ]);
    }

    // Admin — hapus perusahaan beserta divisi & profil terkait
    public function destroy($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Akses ditolak'], 403);
        }

        $company = Company::findOrFail($id);
        $company->delete(); // cascade ke divisions (pastikan foreign key on delete cascade)

        return response()->json(['message' => 'Perusahaan berhasil dihapus']);
    }
}