<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdooConfig extends Model
{
    protected $fillable = [
        'url',
        'db',
        'username',
        'password',
    ];

    protected $casts = [
        'password' => 'encrypted',
    ];
}
