<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    protected $fillable = [
        'company_id',
        'name',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'division_skills');
    }

    public function feedbacks()
    {
        return $this->hasMany(Feedback::class);
    }

    public function profile()
    {
        return $this->hasOne(DivisionProfile::class);
    }

    public function recommendations()
    {
        return $this->hasMany(Recommendation::class);
    }
}