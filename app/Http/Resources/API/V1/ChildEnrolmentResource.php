<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\ChildEnrolment $resource
 */
class ChildEnrolmentResource extends JsonResource
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
            'status' => $this->resource->status,
            'start_date' => $this->resource->start_date?->format('Y-m-d'),
            'end_date' => $this->resource->end_date?->format('Y-m-d'),
            'centre' => new CentreResource($this->whenLoaded('centre')),
            'product' => new ProductResource($this->whenLoaded('product')),
            'created_at' => $this->resource->created_at->toIso8601String(),
        ];
    }
}
