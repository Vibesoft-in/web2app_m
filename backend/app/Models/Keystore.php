<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Keystore extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'app_id',
        'path',
        'alias',
        'passwords',
        'sha1',
        'sha256',
    ];

    protected $hidden = [
        'path',      // encrypted storage path — never expose
        'passwords', // encrypted — never expose
    ];

    protected $casts = [
        'path'      => 'encrypted',       // encrypted storage path
        'passwords' => 'encrypted:array', // { store_password, key_password }
    ];

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }
}
