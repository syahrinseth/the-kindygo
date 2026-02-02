<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\ChildResource;
use App\Models\Child;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Children
 */
#[Group('Children', 'Endpoints for accessing child information and enrolments.')]
class ChildController extends Controller
{
    /**
     * Get list of children for the authenticated user.
     *
     * Returns all children associated with the authenticated parent, including their enrolment details.
     */
    #[Endpoint(operationId: 'children.index', title: 'List children')]
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $children = $user->children()
            ->with(['enrolments.centre', 'enrolments.product'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => ChildResource::collection($children),
        ]);
    }

    /**
     * Get details of a specific child.
     *
     * Returns detailed information about a specific child including enrolments and centres.
     * The user must have access to this child (parent relationship).
     */
    #[Endpoint(operationId: 'children.show', title: 'Get child details')]
    #[PathParameter('child', description: 'The child ID', type: 'integer')]
    public function show(Request $request, Child $child): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Check if user has access to this child
        if (! $user->children()->where('children.id', $child->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this child.',
            ], 403);
        }

        $child->load(['enrolments.centre', 'enrolments.product', 'centres']);

        return response()->json([
            'success' => true,
            'data' => new ChildResource($child),
        ]);
    }

    /**
     * Get child photo.
     *
     * Returns the photo URL for a specific child. The URL may be a signed URL with expiry.
     * The user must have access to this child (parent relationship).
     */
    #[Endpoint(operationId: 'children.photo', title: 'Get child photo')]
    #[PathParameter('child', description: 'The child ID', type: 'integer')]
    public function photo(Request $request, Child $child): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Check if user has access to this child
        if (! $user->children()->where('children.id', $child->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this child.',
            ], 403);
        }

        $photoUrl = null;
        if (method_exists($child, 'getFirstMediaUrl')) {
            $photoUrl = $child->getFirstMediaUrl('photo', 'thumb') ?: null;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'photo_url' => $photoUrl,
            ],
        ]);
    }
}
