<?php

namespace App\Models;

use App\Enums\ChildStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TenantChild extends Pivot
{
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => ChildStatus::class,
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Get the tenant that the child belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the child that belongs to the tenant.
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    /**
     * Update the status of this relationship.
     *
     * @param  ChildStatus  $status  The new status
     * @return bool Whether the update was successful
     */
    public function updateStatus(ChildStatus $status): bool
    {
        $this->status = $status;

        return $this->save();
    }

    /**
     * Set the status to active.
     *
     * @return bool Whether the update was successful
     */
    public function activate(): bool
    {
        return $this->updateStatus(ChildStatus::ACTIVE);
    }

    /**
     * Set the status to return.
     *
     * @return bool Whether the update was successful
     */
    public function markAsReturning(): bool
    {
        return $this->updateStatus(ChildStatus::RETURN);
    }

    /**
     * Set the status to alumni.
     *
     * @return bool Whether the update was successful
     */
    public function markAsAlumni(): bool
    {
        return $this->updateStatus(ChildStatus::ALUMNI);
    }
}
