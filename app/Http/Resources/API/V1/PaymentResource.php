<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\Payment $resource
 */
class PaymentResource extends JsonResource
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
            'reference_number' => $this->resource->reference_number,
            'amount' => $this->resource->amount,
            'amount_formatted' => $this->formatPrice($this->resource->amount),
            'status' => $this->resource->status,
            'gateway' => $this->resource->gateway,
            'gateway_reference' => $this->resource->gateway_reference,
            'paid_at' => $this->resource->paid_at?->toIso8601String(),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
            'created_at' => $this->resource->created_at->toIso8601String(),
            'updated_at' => $this->resource->updated_at->toIso8601String(),
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
