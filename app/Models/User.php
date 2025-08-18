<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
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

class User extends Authenticatable implements FilamentUser, HasAvatar, HasDefaultTenant, HasTenants
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
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Company
            ? ($tenant->user_id === $this->id || $this->companies()->whereKey($tenant->getKey())->exists())
            : false;
    }

    public function getTenants(Panel $panel): array | Collection
    {
        return $this->companies()->get();
    }

    public function getDefaultTenant(Panel $panel): ?Model
    {
        return $this->currentCompany()->first() ?: $this->companies()->first();
    }

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
}
