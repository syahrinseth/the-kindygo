<?php

namespace App\Http\Resources\API\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for standardised registration progress responses.
 *
 * Used across all registration steps to provide consistent response format.
 *
 * @property-read User $resource
 */
class RegistrationProgressResource extends JsonResource
{
    /**
     * Additional data to include in the response.
     */
    protected array $additionalData = [];

    /**
     * Create a new resource instance with additional data.
     */
    public static function withData($resource, array $additionalData = []): self
    {
        $instance = new static($resource);
        $instance->additionalData = $additionalData;

        return $instance;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge([
            'user' => [
                'id' => $this->resource->id,
                'name' => $this->resource->name,
                'email' => $this->resource->email,
                'email_verified' => $this->resource->hasVerifiedEmail(),
                'profile_completed' => $this->resource->profile_completed,
                'current_tenant_id' => $this->resource->current_tenant_id,
            ],
            'registration' => [
                'current_step' => $this->resource->getCurrentRegistrationStep(),
                'is_complete' => $this->resource->isRegistrationComplete(),
                'next_step' => $this->getNextStep(),
                'steps_completed' => $this->getCompletedSteps(),
            ],
        ], $this->additionalData);
    }

    /**
     * Get the next step in the registration process.
     */
    protected function getNextStep(): ?int
    {
        $currentStep = $this->resource->getCurrentRegistrationStep();

        // If registration is complete, no next step
        if ($this->resource->isRegistrationComplete()) {
            return null;
        }

        // If email not verified, they need to verify email first
        if (! $this->resource->hasVerifiedEmail() && $currentStep >= 1) {
            return null; // They need to verify email, not move to next step
        }

        // Otherwise, next step is current + 1, max 4
        return min($currentStep + 1, 4);
    }

    /**
     * Get list of completed steps.
     *
     * @return array<int>
     */
    protected function getCompletedSteps(): array
    {
        $completed = [];
        $currentStep = $this->resource->getCurrentRegistrationStep();

        for ($i = 1; $i < $currentStep; $i++) {
            $completed[] = $i;
        }

        // If email is verified and on step 1, step 1 is complete
        if ($currentStep >= 1 && $this->resource->hasVerifiedEmail()) {
            $completed[] = 1;
        }

        return array_unique($completed);
    }
}
