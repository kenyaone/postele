<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;
    protected $fillable = ['user_id', 'method', 'path', 'route', 'subject', 'status', 'ip', 'agent', 'note'];
    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
