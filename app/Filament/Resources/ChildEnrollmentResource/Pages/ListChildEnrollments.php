<?php

namespace App\Filament\Resources\ChildEnrollmentResource\Pages;

use App\Filament\Resources\ChildEnrollmentResource;
use App\Models\ChildEnrollment;
use App\Enums\ChildEnrollmentStatus;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListChildEnrollments extends ListRecords
{
    protected static string $resource = ChildEnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Enrollment'),
        ];
    }
    
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Enrollments'),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ChildEnrollmentStatus::ACTIVE))
                ->badge(ChildEnrollment::where('status', ChildEnrollmentStatus::ACTIVE)->count()),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ChildEnrollmentStatus::PENDING))
                ->badge(ChildEnrollment::where('status', ChildEnrollmentStatus::PENDING)->count()),
            'current' => Tab::make('Current')
                ->modifyQueryUsing(fn (Builder $query) => $query->current())
                ->badge(ChildEnrollment::current()->count()),
        ];
    }
}
