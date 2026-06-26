<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'action', 'target_type', 'target_id', 'description', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record an admin action. $target is an optional Eloquent model the action
     * applied to (its class basename + key are stored).
     */
    public static function record(string $action, ?string $description = null, ?Model $target = null): void
    {
        static::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'target_type' => $target ? class_basename($target) : null,
            'target_id' => $target?->getKey(),
            'description' => $description === null ? null : Str::limit($description, 2000, ''),
            'created_at' => now(),
        ]);
    }
}
