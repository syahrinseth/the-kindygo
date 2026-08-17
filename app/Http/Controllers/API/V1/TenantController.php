<?php

namespace App\Http\Controllers\API\V1;

use App\Actions\Auth\SwitchTenantAction;
use App\Actions\Tenant\UpdateTenantChipConfigurationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\UpdateTenantChipConfigurationRequest;
use App\Http\Resources\API\V1\TenantResource;
use App\Models\Tenant;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Tenants
 */
#[Group('Tenants', 'Endpoints for managing tenant (company) context and switching between tenants.')]
class TenantController extends Controller
{
    public function __construct(
        protected SwitchTenantAction $switchTenant
    ) {}

    /**
     * Get list of tenants the authenticated user belongs to.
     *
     * Returns all tenants (companies) that the authenticated user has access to,
     * including their centres (branches).
     */
    #[Endpoint(operationId: 'tenants.index', title: 'List tenants')]
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $tenants = $user->tenants()
            ->with(['centres'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => TenantResource::collection($tenants),
        ]);
    }

    /**
     * Switch to a different tenant.
     *
     * Switches the user's current tenant context. A new access token is issued
     * that is scoped to the new tenant. The user must be a member of the target tenant.
     */
    #[Endpoint(operationId: 'tenants.switch', title: 'Switch tenant')]
    #[PathParameter('tenant', description: 'The tenant ID', type: 'integer')]
    public function switch(Request $request, Tenant $tenant): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $this->switchTenant->execute(
            user: $user,
            tenant: $tenant,
            deviceName: $request->input('device_name')
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'tenant' => new TenantResource($tenant->load('centres')),
                'token' => $result['token_result']->toArray(),
            ],
        ]);
    }

    /**
     * Get the CHIP configuration status for the current tenant.
     */
    #[Endpoint(operationId: 'tenants.chip-configuration.show', title: 'Get CHIP configuration')]
    public function chipConfiguration(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! $user->hasAnyRole(['super-admin', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorised to manage CHIP configuration.',
            ], 403);
        }

        $tenant = $user->currentTenant();

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No current organisation is selected.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => $tenant->hasChipCredentials(),
                'brand_id' => $tenant->chip_brand_id,
            ],
        ]);
    }

    /**
     * Enable or update CHIP credentials for the current tenant.
     */
    #[Endpoint(operationId: 'tenants.chip-configuration.update', title: 'Update CHIP configuration')]
    public function updateChipConfiguration(
        UpdateTenantChipConfigurationRequest $request,
        UpdateTenantChipConfigurationAction $updateChipConfiguration,
    ): JsonResponse {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $tenant = $user->currentTenant();

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No current organisation is selected.',
            ], 404);
        }

        $validated = $request->validated();
        $tenant = $updateChipConfiguration->execute(
            tenant: $tenant,
            enabled: $validated['enabled'],
            brandId: $validated['brand_id'] ?? null,
            apiKey: $validated['api_key'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => $tenant->hasChipCredentials()
                ? 'CHIP configuration updated successfully.'
                : 'CHIP payments have been disabled.',
            'data' => [
                'enabled' => $tenant->hasChipCredentials(),
                'brand_id' => $tenant->chip_brand_id,
            ],
        ]);
    }

    /**
     * Disable CHIP payments for the current tenant.
     */
    #[Endpoint(operationId: 'tenants.chip-configuration.delete', title: 'Disable CHIP configuration')]
    public function destroyChipConfiguration(
        Request $request,
        UpdateTenantChipConfigurationAction $updateChipConfiguration,
    ): JsonResponse {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! $user->hasAnyRole(['super-admin', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorised to manage CHIP configuration.',
            ], 403);
        }

        $tenant = $user->currentTenant();

        if (! $tenant) {
            return response()->json([
                'success' => false,
                'message' => 'No current organisation is selected.',
            ], 404);
        }

        $updateChipConfiguration->execute($tenant, false);

        return response()->json([
            'success' => true,
            'message' => 'CHIP payments have been disabled.',
            'data' => [
                'enabled' => false,
                'brand_id' => null,
            ],
        ]);
    }
}
