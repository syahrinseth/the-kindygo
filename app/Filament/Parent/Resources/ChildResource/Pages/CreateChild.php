<?php

namespace App\Filament\Parent\Resources\ChildResource\Pages;

use App\Enums\ChildStatus;
use App\Filament\Parent\Resources\ChildResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateChild extends CreateRecord
{
    protected static string $resource = ChildResource::class;

    protected function afterCreate(): void
    {
        // After creating the child, associate it with the current parent
        $child = $this->record;
        $user = Auth::user();

        if ($user) {
            // Associate child with the parent user with 'parent' relationship type
            $child->users()->attach($user->id, [
                'relationship_type' => 'parent',
            ]);

            // If the parent has a current tenant, associate the child with it
            if ($user->current_tenant_id) {
                $child->addToTenant($user->current_tenant_id, ChildStatus::NEW);
            }
        }
    }
}
