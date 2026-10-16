<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price_monthly',
        'features',
        'limits',
        'is_active',
    ];

    protected $casts = [
        'features'  => 'array',
        'limits'    => 'array',
        'is_active' => 'boolean',
    ];
}
