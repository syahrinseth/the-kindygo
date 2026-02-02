<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\DeviceToken $resource
 */
class DeviceTokenResource extends JsonResource
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
            'device_name' => $this->resource->device_name,
            'device_type' => $this->resource->device_type,
            'is_verified' => $this->resource->push_token_verified_at !== null,
            'last_used_at' => $this->resource->last_used_at?->toIso8601String(),
            'created_at' => $this->resource->created_at->toIso8601String(),
        ];
    }
}
