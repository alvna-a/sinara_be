<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Kolom ini menyimpan override MANUAL dari admin untuk status magang
     * mahasiswa (lihat fitur "switch user" di Data Mahasiswa).
     *
     * - null              => status magang dihitung OTOMATIS dari program_studi + semester
     * - 'belum_magang'    => admin override manual -> dianggap Mhs2 (Calon Mahasiswa Magang)
     * - 'sudah_magang'    => admin override manual -> dianggap Mhs1 (Alumni Magang)
     *
     * Override ini TIDAK ikut berubah otomatis ketika semester mahasiswa
     * diupdate. Admin harus reset manual (set kembali ke null) lewat
     * endpoint resetRoleOverride kalau ingin balik ke mode otomatis.
     */
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->enum('internship_override', ['belum_magang', 'sudah_magang'])
                ->nullable()
                ->after('semester');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('internship_override');
        });
    }
};
