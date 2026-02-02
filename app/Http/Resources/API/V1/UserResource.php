<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\User $resource
 */
class UserResource extends JsonResource
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
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'email_verified' => $this->resource->hasVerifiedEmail(),
            'email_verified_at' => $this->resource->email_verified_at?->toIso8601String(),
            'profile_completed' => $this->resource->profile_completed,
            'photo_url' => $this->resource->getFilamentAvatarUrl(),
            'current_tenant_id' => $this->resource->current_tenant_id,
            'profile' => $this->whenLoaded('profile', fn () => new UserProfileResource($this->resource->profile)),
            'address' => $this->whenLoaded('userAddress', fn () => new UserAddressResource($this->resource->userAddress)),
            'created_at' => $this->resource->created_at->toIso8601String(),
            'updated_at' => $this->resource->updated_at->toIso8601String(),
        ];
    }
}
