<?php

namespace App\Models\Banhang;

use App\Models\User; // ✅ Bổ sung dòng này
use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany; // Import HasMany for users relationship

class Role extends SpatieRole
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'guard_name',
        'accessible_apps', // <-- PHẢI CÓ DÒNG NÀY
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'accessible_apps' => 'array', // <-- PHẢI CÓ DÒNG NÀY
    ];

    /**
     * Get the users that belong to the role.
     */
    public function users(): BelongsToMany // Changed to BelongsToMany to correctly represent Spatie's relationship
    {
        return $this->belongsToMany(User::class, 'model_has_roles', 'role_id', 'model_id');
    }
}
