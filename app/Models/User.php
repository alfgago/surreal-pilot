<?php

namespace App\Models;

// use Filament\Models\Contracts\FilamentUser;
// use Filament\Models\Contracts\HasAvatar;
// use Filament\Models\Contracts\HasDefaultTenant;
// use Filament\Models\Contracts\HasTenants;
// use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable // implements FilamentUser, HasAvatar, HasDefaultTenant, HasTenants
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'selected_engine_type',
        'current_company_id',
        'preferences',
        'avatar_url',
        'bio',
        'timezone',
        'email_notifications',
        'browser_notifications',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [];

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
            'preferences' => 'array',
            'email_notifications' => 'boolean',
            'browser_notifications' => 'boolean',
        ];
    }

    // public function canAccessPanel(Panel $panel): bool
    // {
    //     return true;
    // }

    // public function canAccessTenant(Model $tenant): bool
    // {
    //     return $tenant instanceof Company
    //         ? ($tenant->user_id === $this->id || $this->companies()->whereKey($tenant->getKey())->exists())
    //         : false;
    // }

    // public function getTenants(Panel $panel): array | Collection
    // {
    //     return $this->companies()->get();
    // }

    // public function getDefaultTenant(Panel $panel): ?Model
    // {
    //     return $this->currentCompany()->first() ?: $this->companies()->first();
    // }

    public function getFilamentAvatarUrl(): string
    {
        $initial = strtoupper(substr($this->name ?? 'U', 0, 1));
        // Placeholder avatar via ui-avatars
        return "https://ui-avatars.com/api/?name={$initial}&background=6366f1&color=fff";
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user')->withTimestamps();
    }

    public function ownedCompanies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function currentCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'current_company_id');
    }

    /**
     * Get the user's selected engine type.
     */
    public function getSelectedEngineType(): ?string
    {
        return $this->selected_engine_type;
    }

    /**
     * Set the user's engine preference.
     */
    public function setEnginePreference(string $engineType): void
    {
        $this->update(['selected_engine_type' => $engineType]);
    }

    /**
     * Check if the user has selected an engine.
     */
    public function hasSelectedEngine(): bool
    {
        return !empty($this->selected_engine_type);
    }

    /**
     * Get a user preference value.
     */
    public function getPreference(string $key, mixed $default = null): mixed
    {
        return data_get($this->preferences, $key, $default);
    }

    /**
     * Set a user preference value.
     */
    public function setPreference(string $key, mixed $value): void
    {
        $preferences = $this->preferences ?? [];
        data_set($preferences, $key, $value);
        $this->update(['preferences' => $preferences]);
    }

    /**
     * Get the user's avatar URL with fallback.
     */
    public function getAvatarUrl(): string
    {
        if ($this->avatar_url) {
            return $this->avatar_url;
        }

        return $this->getFilamentAvatarUrl();
    }

    /**
     * Get default AI provider preference.
     */
    public function getDefaultProvider(): string
    {
        return $this->getPreference('ai.default_provider', 'anthropic');
    }

    /**
     * Get AI temperature preference.
     */
    public function getTemperature(): float
    {
        return (float) $this->getPreference('ai.temperature', 0.2);
    }

    /**
     * Check if streaming responses are enabled.
     */
    public function hasStreamingEnabled(): bool
    {
        return (bool) $this->getPreference('ai.stream_responses', true);
    }

    /**
     * Check if chat history saving is enabled.
     */
    public function hasChatHistoryEnabled(): bool
    {
        return (bool) $this->getPreference('ai.save_history', true);
    }

    /**
     * Get the GDevelop game sessions for this user.
     */
    public function gdevelopGameSessions(): HasMany
    {
        return $this->hasMany(GDevelopGameSession::class);
    }
}
