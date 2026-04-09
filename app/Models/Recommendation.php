<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recommendation extends Model
{
    protected $fillable = [
        'user_id',
        'division_id',
        'similarity_score',
        'suitability_avg',
        'experience_summary',
        'matched_skills',
    ];

    protected function casts(): array
    {
        return [
            'matched_skills' => 'array', // otomatis decode JSON jadi array
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }
}