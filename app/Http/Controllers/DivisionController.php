<?php

namespace App\Http\Controllers;

use App\Models\Division;
use Illuminate\Http\Request;

class DivisionController extends Controller
{
    // Ambil semua divisi (untuk search/autocomplete M1 & M2)
    public function index(Request $request)
    {
        $query = Division::with('company');

        // Kalau ada parameter search
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $divisions = $query->orderBy('name')->get();

        return response()->json([
            'message' => 'Berhasil ambil divisi',
            'data'    => $divisions,
        ]);
    }
}