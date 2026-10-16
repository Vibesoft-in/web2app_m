<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushDevice extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'app_id',
        'app_user_id',
        'fcm_token',
        'platform',
    ];

    protected $hidden = [
        'fcm_token', // device token — never return raw in list responses
    ];

    public function appUser(): BelongsTo
    {
        return $this->belongsTo(AppUser::class);
    }
}
