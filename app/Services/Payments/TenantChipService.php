<?php

namespace App\Services\Payments;

use App\Models\Tenant;
use Chip\ChipApi;
use Chip\Model\ClientDetails;
use Chip\Model\Product;
use Chip\Model\Purchase;
use Chip\Model\PurchaseDetails;
use RuntimeException;

class TenantChipService
{
    /**
     * @param  array<int, Product>  $products
     */
    public function createPurchase(
        Tenant $tenant,
        string $email,
        array $products,
        string $successRedirect,
        string $failureRedirect,
        string $successCallback,
        ?string $cancelRedirect = null,
        bool $sendReceipt = false,
        ?string $fullName = null,
    ): mixed {
        $client = new ClientDetails;
        $client->email = $email;
        $client->full_name = $fullName;

        $purchase = new Purchase;
        $purchase->client = $client;

        $details = new PurchaseDetails;
        $details->products = $products;

        $purchase->purchase = $details;
        $purchase->brand_id = $tenant->chip_brand_id;
        $purchase->success_redirect = $successRedirect;
        $purchase->success_callback = $successCallback;
        $purchase->failure_redirect = $failureRedirect;
        $purchase->cancel_redirect = $cancelRedirect;
        $purchase->send_receipt = $sendReceipt;

        return $this->makeClient($tenant)->createPurchase($purchase);
    }

    public function getPurchase(Tenant $tenant, string $purchaseId): mixed
    {
        return $this->makeClient($tenant)->getPurchase($purchaseId);
    }

    protected function makeClient(Tenant $tenant): ChipApi
    {
        if (! $tenant->hasChipCredentials()) {
            throw new RuntimeException('CHIP payments are not configured for this organisation.');
        }

        return new ChipApi(
            $tenant->chip_brand_id,
            $tenant->chip_api_key,
            config('chiplaravel.endpoint'),
        );
    }
}
