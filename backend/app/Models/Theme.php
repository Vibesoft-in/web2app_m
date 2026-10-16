<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Theme extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'preview_url',
        'schema_version',
        'defaults',
        'is_active',
    ];

    protected $casts = [
        'defaults'  => 'array',
        'is_active' => 'boolean',
    ];
}
