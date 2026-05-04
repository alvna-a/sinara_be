<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('division_profiles', function (Blueprint $table) {
        $table->id();
        $table->foreignId('division_id')->constrained()->onDelete('cascade');
        $table->text('combined_skills');
        $table->text('combined_experience');
        $table->text('combined_jobdesk')->nullable();    // ← tambah ini
        $table->text('combined_reasons')->nullable();    // ← tambah ini
        $table->integer('feedback_count')->default(0);
        $table->float('avg_suitability')->default(0);
        $table->float('penalty_score')->default(0.0);   // ← tambah ini
        $table->timestamps();
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('division_profiles');
    }
};