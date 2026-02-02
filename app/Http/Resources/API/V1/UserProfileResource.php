<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\UserProfile $resource
 */
class UserProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'phone' => $this->resource->phone,
            'nric' => $this->when(
                $request->user()?->id === $this->resource->user_id,
                fn () => $this->resource->nric
            ),
            'passport' => $this->when(
                $request->user()?->id === $this->resource->user_id,
                fn () => $this->resource->passport
            ),
            'gender' => $this->resource->gender,
            'date_of_birth' => $this->resource->date_of_birth?->format('Y-m-d'),
            'nationality' => $this->resource->nationality,
            'occupation' => $this->resource->occupation,
            'relationship_to_child' => $this->resource->relationship_to_child,
        ];
    }
}
