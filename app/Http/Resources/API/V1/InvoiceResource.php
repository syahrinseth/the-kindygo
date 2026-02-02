<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\Invoice $resource
 */
class InvoiceResource extends JsonResource
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
            'invoice_number' => $this->resource->invoice_number,
            'status' => $this->resource->status,
            'subtotal' => $this->resource->subtotal,
            'subtotal_formatted' => $this->formatPrice($this->resource->subtotal),
            'tax_amount' => $this->resource->tax_amount,
            'tax_amount_formatted' => $this->formatPrice($this->resource->tax_amount),
            'total' => $this->resource->total,
            'total_formatted' => $this->formatPrice($this->resource->total),
            'amount_paid' => $this->resource->amount_paid,
            'amount_paid_formatted' => $this->formatPrice($this->resource->amount_paid),
            'amount_due' => $this->resource->amount_due,
            'amount_due_formatted' => $this->formatPrice($this->resource->amount_due),
            'issue_date' => $this->resource->issue_date?->format('Y-m-d'),
            'due_date' => $this->resource->due_date?->format('Y-m-d'),
            'paid_at' => $this->resource->paid_at?->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'centre' => new CentreResource($this->whenLoaded('centre')),
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

    /**
     * Check if the invoice is overdue.
     */
    protected function isOverdue(): bool
    {
        if (! $this->resource->due_date) {
            return false;
        }

        return $this->resource->due_date->isPast()
            && $this->resource->status !== 'paid'
            && $this->resource->amount_due > 0;
    }
}
