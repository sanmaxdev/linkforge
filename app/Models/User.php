<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'avatar',
        'google_id',
        'password',
        'role',
        'status',
        'plan_id',
        'ai_credits',
        'settings',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ai_credits' => 'integer',
            'settings' => 'array',
        ];
    }

    // Relationships ---------------------------------------------------------

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function pixels(): HasMany
    {
        return $this->hasMany(Pixel::class);
    }

    public function bioPages(): HasMany
    {
        return $this->hasMany(BioPage::class);
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }

    public function qrTemplates(): HasMany
    {
        return $this->hasMany(QrTemplate::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')->withPivot('role');
    }

    // Helpers ---------------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Public URL of the uploaded avatar, or null to fall back to initials. */
    public function avatarUrl(): ?string
    {
        return $this->avatar && is_file(public_path('uploads/avatars/'.$this->avatar))
            ? asset('uploads/avatars/'.$this->avatar)
            : null;
    }

    /** One or two uppercase initials derived from the name, for the avatar fallback. */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $parts = array_values(array_filter($parts));

        if (empty($parts)) {
            return 'U';
        }
        $first = mb_substr($parts[0], 0, 1);
        $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    /** True when the user has finished setting up app-based 2FA. */
    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_secret) && ! is_null($this->two_factor_confirmed_at);
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', ['trialing', 'active'])
            ->latest()
            ->first();
    }

    private ?Plan $resolvedPlan = null;

    /** The user's current plan, falling back to the default free plan. Memoized per request. */
    public function currentPlan(): ?Plan
    {
        if ($this->resolvedPlan) {
            return $this->resolvedPlan;
        }

        $plan = $this->relationLoaded('plan')
            ? $this->plan
            : ($this->plan_id ? Plan::find($this->plan_id) : null);

        return $this->resolvedPlan = ($plan ?: Plan::where('slug', 'free')->first());
    }
}
