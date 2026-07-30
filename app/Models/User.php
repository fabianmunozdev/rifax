<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\PanelPermission;
use App\Enums\PanelRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const SUPPORTED_PANEL_LOCALES = ['en', 'es'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'preferred_locale',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->roleEnum() !== null;
    }

    public function roleEnum(): ?PanelRole
    {
        return filled($this->role) ? PanelRole::tryFrom($this->role) : null;
    }

    public function roleLabel(): ?string
    {
        return $this->roleEnum()?->label();
    }

    public function hasPanelPermission(PanelPermission|string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $role = $this->roleEnum();

        if ($role === null) {
            return false;
        }

        $permissionValue = $permission instanceof PanelPermission ? $permission->value : $permission;

        return in_array($permissionValue, $role->permissions(), true);
    }

    /**
     * @param  list<PanelPermission|string>  $permissions
     */
    public function hasAnyPanelPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPanelPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    public static function supportedPanelLocaleOptions(): array
    {
        return [
            'en' => __('admin.locales.en'),
            'es' => __('admin.locales.es'),
        ];
    }

    public function preferredPanelLocale(): ?string
    {
        return in_array((string) $this->preferred_locale, self::SUPPORTED_PANEL_LOCALES, true)
            ? (string) $this->preferred_locale
            : null;
    }
}
