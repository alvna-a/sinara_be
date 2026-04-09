<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    protected $fillable = ['name'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_skills');
    }

    public function divisions()
    {
        return $this->belongsToMany(Division::class, 'division_skills');
    }
}