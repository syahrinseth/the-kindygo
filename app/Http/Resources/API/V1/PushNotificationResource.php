<?php

namespace App\Http\Resources\API\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\PushNotification $resource
 */
class PushNotificationResource extends JsonResource
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
            'type' => $this->resource->type,
            'title' => $this->resource->title,
            'message' => $this->resource->message,
            'data' => $this->resource->data,
            'is_read' => $this->resource->is_read,
            'read_at' => $this->resource->read_at?->toIso8601String(),
            'sent_at' => $this->resource->sent_at?->toIso8601String(),
            'created_at' => $this->resource->created_at->toIso8601String(),
        ];
    }
}
