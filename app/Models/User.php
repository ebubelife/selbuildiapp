<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name', 'first_name', 'last_name', 'email', 'password', 'role', 'phone',
    'country', 'project_country', 'city', 'account_type', 'preferred_currency', 'is_diaspora',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_diaspora' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function supplierProfile(): HasOne
    {
        return $this->hasOne(SupplierProfile::class);
    }

    public function contractorProfile(): HasOne
    {
        return $this->hasOne(ContractorProfile::class);
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function trustScore(): HasOne
    {
        return $this->hasOne(ProcurementTrustScore::class);
    }

    public function trustScoreEvents(): HasMany
    {
        return $this->hasMany(TrustScoreEvent::class);
    }

    public function creditAccount(): HasOne
    {
        return $this->hasOne(CreditAccount::class);
    }

    public function isSupplier(): bool
    {
        return $this->role === 'supplier';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isContractor(): bool
    {
        return $this->role === 'contractor';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }
}
