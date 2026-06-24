<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FileMetadata extends Model
{
    use HasFactory;

    protected $fillable = [
        'metadatable_type',
        'metadatable_id',
        'key',
        'value',
    ];

    public function metadatable()
    {
        return $this->morphTo();
    }
}
