<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    protected $table = 'feedbacks'; 
    protected $fillable = [
        'user_id',
        'division_id',
        'status',
        'reject_reason',
        'skills_used',
        'experience',
        'suitability',
        'rating_reason',
        'jobdesk',
        'duration',
        'location',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }
}