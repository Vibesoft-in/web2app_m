<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class AppUser extends Authenticatable
{
    use BelongsToTenant;
    use HasApiTokens;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'app_id',
        'phone',
        'email',
        'store_customer_id',
        'name',
        'last_login_at',
    ];

    protected $hidden = [
        'store_customer_id', // internal linking ID, not for API exposure
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
    ];

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    public function pushDevices(): HasMany
    {
        return $this->hasMany(PushDevice::class);
    }
}
