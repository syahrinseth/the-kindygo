<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\UserAddress $resource
 */
class UserAddressResource extends JsonResource
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
            'address' => $this->resource->address,
            'address_2' => $this->resource->address_2,
            'city' => $this->resource->city,
            'postal_code' => $this->resource->postal_code,
            'state_code' => $this->resource->state_code,
            'state_name' => $this->resource->getStateName(),
            'country' => $this->resource->country ?? 'Malaysia',
        ];
    }
}
