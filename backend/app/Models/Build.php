<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BuildStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Build extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'app_id',
        'config_version',
        'status',
        'logs_path',
        'artifact_path',
        'version_code',
        'version_name',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $hidden = [
        'artifact_path', // never expose storage path; use signed URL instead
        'logs_path',
    ];

    protected $casts = [
        'status'      => BuildStatus::class,
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    public function isDownloadable(): bool
    {
        return $this->status === BuildStatus::Ready
            && $this->artifact_path !== null;
    }
}
