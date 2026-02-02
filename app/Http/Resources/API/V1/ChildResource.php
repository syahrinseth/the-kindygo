<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\Child $resource
 */
class ChildResource extends JsonResource
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
            'name' => $this->resource->name,
            'date_of_birth' => $this->resource->date_of_birth?->format('Y-m-d'),
            'age' => $this->resource->date_of_birth?->age,
            'gender' => $this->resource->gender,
            'photo_url' => $this->getPhotoUrl(),
            'mykid' => $this->resource->mykid,
            'passport' => $this->resource->passport,
            'nationality' => $this->resource->nationality,
            'allergies' => $this->resource->allergies,
            'medical_conditions' => $this->resource->medical_conditions,
            'emergency_contact_name' => $this->resource->emergency_contact_name,
            'emergency_contact_phone' => $this->resource->emergency_contact_phone,
            'relationship_type' => $this->whenPivotLoaded('child_user', fn () => $this->resource->pivot->relationship_type),
            'enrolments' => ChildEnrolmentResource::collection($this->whenLoaded('enrolments')),
            'centres' => CentreResource::collection($this->whenLoaded('centres')),
            'created_at' => $this->resource->created_at->toIso8601String(),
            'updated_at' => $this->resource->updated_at->toIso8601String(),
        ];
    }

    /**
     * Get the child's photo URL.
     */
    protected function getPhotoUrl(): ?string
    {
        if (method_exists($this->resource, 'getFirstMediaUrl')) {
            $url = $this->resource->getFirstMediaUrl('photo', 'thumb');

            return $url ?: null;
        }

        return null;
    }
}
