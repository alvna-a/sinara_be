<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name',
        'is_verified',
        'city',
        'province',
        'address',
    ];

    public function divisions()
    {
        return $this->hasMany(Division::class);
    }
}