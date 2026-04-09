<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('division_id')->constrained()->onDelete('cascade');
            $table->float('similarity_score')->default(0); // hasil cosine similarity 0.0 - 1.0
            $table->float('suitability_avg')->default(0);  // rata-rata rating alumni
            $table->text('experience_summary')->nullable(); // ringkasan pengalaman alumni
            $table->json('matched_skills')->nullable();     // skill yang cocok
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};