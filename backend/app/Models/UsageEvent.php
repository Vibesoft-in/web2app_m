<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageEvent extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public $timestamps = false; // only created_at

    protected $fillable = [
        'tenant_id',
        'app_id',
        'type',
        'qty',
        'cost',
        'created_at',
    ];

    protected $casts = [
        'qty'        => 'integer',
        'cost'       => 'decimal:4',
        'created_at' => 'datetime',
    ];

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }
}
