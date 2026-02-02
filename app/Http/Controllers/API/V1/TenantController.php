<?php

namespace App\Http\Controllers\API\V1;

use App\Actions\Auth\SwitchTenantAction;
use App\Http\Controllers\Controller;
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
}
