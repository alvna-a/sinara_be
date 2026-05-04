<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DivisionProfile extends Model
{
    protected $fillable = [
        'division_id',
        'combined_skills',
        'combined_experience',
        'combined_jobdesk',
        'combined_reasons',
        'feedback_count',
        'avg_suitability',
        'penalty_score',
    ];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }
}