<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\Tenant $resource
 */
class TenantResource extends JsonResource
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
            'uuid' => $this->resource->uuid,
            'name' => $this->resource->name,
            'slug' => $this->resource->slug,
            'logo_url' => $this->resource->logo_url ?? null,
            'is_current' => $request->user()?->current_tenant_id === $this->resource->id,
            'centres' => CentreResource::collection($this->whenLoaded('centres')),
        ];
    }
}
