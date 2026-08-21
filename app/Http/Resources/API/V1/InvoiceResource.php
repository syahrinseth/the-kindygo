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
        $subtotalAmount = $this->resource->subtotal_amount;
        $taxAmount = $this->resource->getAttribute('tax_amount');
        $totalAmount = $this->resource->total_amount;
        $amountPaid = $this->resource->getTotalPaid();
        $amountDue = $totalAmount - $amountPaid;

        return [
            'id' => $this->resource->id,
            'invoice_number' => $this->resource->number,
            'status' => $this->resource->status,
            'total_items' => $this->resource->total_items,
            'subtotal_amount' => $subtotalAmount,
            'subtotal_amount_formatted' => $this->formatPrice($subtotalAmount),
            'discount_amount' => $this->resource->discount_amount,
            'discount_amount_formatted' => $this->formatPrice($this->resource->discount_amount),
            'total_amount' => $totalAmount,
            'total_amount_formatted' => $this->formatPrice($totalAmount),
            'amount_paid' => $amountPaid,
            'amount_paid_formatted' => $this->formatPrice($amountPaid),
            'amount_due' => $amountDue,
            'amount_due_formatted' => $this->formatPrice($amountDue),
            'subtotal' => $subtotalAmount,
            'subtotal_formatted' => $this->formatPrice($subtotalAmount),
            'tax_amount' => $taxAmount,
            'tax_amount_formatted' => $this->formatPrice($taxAmount),
            'total' => $totalAmount,
            'total_formatted' => $this->formatPrice($totalAmount),
            'issue_date' => $this->resource->date?->format('Y-m-d'),
            'due_date' => $this->resource->due_at?->format('Y-m-d'),
            'paid_at' => $this->resource->paid_at?->toIso8601String(),
            'is_overdue' => $this->isOverdue($amountDue),
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
    protected function isOverdue(int $amountDue): bool
    {
        return $amountDue > 0 && $this->resource->isOverdue();
    }
}
