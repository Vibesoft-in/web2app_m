<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Integration extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'app_id',
        'type',
        'status',
        'credentials',
        'meta',
    ];

    protected $hidden = [
        'credentials', // encrypted — never expose
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'meta'        => 'array',
    ];

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }
}
