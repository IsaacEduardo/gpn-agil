<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'guard_name'];

    public function usersDirect()
    {
        return $this->hasMany(User::class, 'role_id');
    }

}

