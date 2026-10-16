<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'source',
        'event_id',   // unique — blocks replay attacks
        'payload',
        'processed_at',
    ];

    protected $hidden = [
        'payload', // raw third-party payload — never expose in API responses
    ];

    protected $casts = [
        'payload'      => 'array',
        'processed_at' => 'datetime',
    ];

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }
}
