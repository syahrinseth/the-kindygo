<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\InvoiceItem $resource
 */
class InvoiceItemResource extends JsonResource
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
            'description' => $this->resource->description,
            'quantity' => $this->resource->quantity,
            'unit_price' => $this->resource->unit_price,
            'unit_price_formatted' => $this->formatPrice($this->resource->unit_price),
            'total' => $this->resource->total,
            'total_formatted' => $this->formatPrice($this->resource->total),
            'child' => new ChildResource($this->whenLoaded('child')),
            'product' => new ProductResource($this->whenLoaded('product')),
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
