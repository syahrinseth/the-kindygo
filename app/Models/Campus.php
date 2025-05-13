<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campus extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
        'address_1',
        'address_2',
        'postal_code',
        'city',
        'state',
    ];

    /**
     * Get the tenant that owns the campus.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the centres for the campus.
     */
    public function centres(): HasMany
    {
        return $this->hasMany(Centre::class);
    }
}
