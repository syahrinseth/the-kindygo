<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentUndertakingAgreement extends Model
{
    use HasFactory;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'parent_undertaking_agreements';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'letter_of_undertaking_id',
        'tenant_id',
        'agreed_at',
        'ip_address',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'agreed_at' => 'datetime',
        ];
    }

    /**
     * Get the user who made the agreement.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the letter of undertaking that was agreed to.
     */
    public function letterOfUndertaking(): BelongsTo
    {
        return $this->belongsTo(LetterOfUndertaking::class);
    }

    /**
     * Get the tenant for this agreement.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope a query to filter by user and tenant.
     */
    public function scopeForUserAndTenant($query, int $userId, int $tenantId)
    {
        return $query->where('user_id', $userId)->where('tenant_id', $tenantId);
    }
}
