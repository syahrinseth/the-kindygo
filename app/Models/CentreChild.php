<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot model for Centre-Child many-to-many relationship.
 *
 * Usage examples:
 *
 * // Add a child to a centre
 * $centre->addChild($child);
 * // or
 * $child->addToCentre($centre);
 *
 * // Remove a child from a centre
 * $centre->removeChild($child);
 * // or
 * $child->removeFromCentre($centre);
 *
 * // Check relationships
 * $centre->hasChild($child);
 * $child->isInCentre($centre);
 *
 * // Get all children in a centre
 * $centre->children;
 *
 * // Get all centres for a child
 * $child->centres;
 */
class CentreChild extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'centre_child';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Get the centre that the child belongs to.
     */
    public function centre(): BelongsTo
    {
        return $this->belongsTo(Centre::class);
    }

    /**
     * Get the child that belongs to the centre.
     */
    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }
}
