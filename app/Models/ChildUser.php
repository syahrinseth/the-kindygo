<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ChildUser extends Pivot
{
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'child_id',
        'user_id',
        'relationship_type',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'child_id' => 'integer',
        'user_id' => 'integer',
    ];
}
