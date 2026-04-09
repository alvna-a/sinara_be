<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DivisionProfile extends Model
{
    protected $fillable = [
        'division_id',
        'combined_skills',
        'combined_experience',
        'feedback_count',
        'avg_suitability',
    ];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }
}