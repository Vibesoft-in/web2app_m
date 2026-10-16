<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppConfig extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'app_id',
        'version',
        'state',          // draft | published
        'config',         // validated JSON object
        'published_at',
    ];

    protected $casts = [
        'config'       => 'array',
        'published_at' => 'datetime',
    ];

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    public function isDraft(): bool
    {
        return $this->state === 'draft';
    }

    public function isPublished(): bool
    {
        return $this->state === 'published';
    }

    public function getConfigDataAttribute(): ?array
    {
        return $this->config;
    }
}
