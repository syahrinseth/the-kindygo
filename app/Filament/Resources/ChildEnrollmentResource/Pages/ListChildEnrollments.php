<?php

namespace App\Filament\Resources\ChildEnrolmentResource\Pages;

use App\Filament\Resources\ChildEnrolmentResource;
use App\Models\ChildEnrolment;
use App\Enums\ChildEnrolmentStatus;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListChildEnrolments extends ListRecords
{
    protected static string $resource = ChildEnrolmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Enrolment'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Enrolments'),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', ChildEnrolmentStatus::ACTIVE))
                ->badge(ChildEnrolment::where('status', ChildEnrolmentStatus::ACTIVE)->count()),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', ChildEnrolmentStatus::PENDING))
                ->badge(ChildEnrolment::where('status', ChildEnrolmentStatus::PENDING)->count()),
            'current' => Tab::make('Current')
                ->modifyQueryUsing(fn(Builder $query) => $query->current())
                ->badge(ChildEnrolment::current()->count()),
        ];
    }
}
