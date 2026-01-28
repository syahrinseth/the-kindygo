<?php

namespace App\DataTransferObjects;

use App\Models\Payment;

class PaymentResult
{
    public function __construct(
        public ?Payment $payment,
        public ?string $checkoutUrl,
        public array $allocationSummary,
        public bool $requiresRedirect,
        public bool $success,
        public ?string $message = null,
    ) {}

    /**
     * Create a successful result
     */
    public static function success(
        Payment $payment,
        ?string $checkoutUrl = null,
        array $allocationSummary = [],
        bool $requiresRedirect = false,
        ?string $message = null
    ): self {
        return new self(
            payment: $payment,
            checkoutUrl: $checkoutUrl,
            allocationSummary: $allocationSummary,
            requiresRedirect: $requiresRedirect,
            success: true,
            message: $message
        );
    }

    /**
     * Create a failed result
     */
    public static function failure(
        string $message,
        ?Payment $payment = null,
        array $allocationSummary = []
    ): self {
        return new self(
            payment: $payment,
            checkoutUrl: null,
            allocationSummary: $allocationSummary,
            requiresRedirect: false,
            success: false,
            message: $message
        );
    }
}
