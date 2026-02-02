<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\Product $resource
 */
class ProductResource extends JsonResource
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
            'description' => $this->resource->description,
            'type' => $this->resource->type,
            'price' => $this->resource->price,
            'price_formatted' => $this->formatPrice($this->resource->price),
        ];
    }

    /**
     * Format price as currency.
     */
    protected function formatPrice(?int $priceInCents): ?string
    {
        if ($priceInCents === null) {
            return null;
        }

        return 'RM '.number_format($priceInCents / 100, 2);
    }
}
