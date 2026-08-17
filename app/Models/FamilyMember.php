<?php

namespace App\Models;

use App\Support\MyKadNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class FamilyMember extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\FamilyMemberFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'relationship_type',
        'name',
        'nric',
        'phone',
        'email',
        'occupation',
        'address',
        'address_2',
        'city',
        'postal_code',
        'state_code',
        'office_name',
        'office_address',
        'office_address_2',
        'office_city',
        'office_postal_code',
        'office_state_code',
    ];

    public function setNricAttribute(?string $value): void
    {
        $this->attributes['nric'] = MyKadNumber::format($value);
    }

    /**
     * Get the user that this family member belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Register media collections for the family member.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('mykad')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
            ->singleFile()
            ->useDisk('private');

        $this->addMediaCollection('photo')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile()
            ->useDisk('private');
    }

    /**
     * Register media conversions for the family member.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->performOnCollections('photo');

        $this->addMediaConversion('preview')
            ->width(800)
            ->height(600)
            ->quality(90)
            ->performOnCollections('mykad');
    }
}
