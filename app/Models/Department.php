<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'name',
        'code',
    ];

    /**
     * Get the users associated with the department.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
