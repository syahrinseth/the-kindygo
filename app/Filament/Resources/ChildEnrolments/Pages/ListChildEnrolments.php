<?php

namespace App\Filament\Resources\ChildEnrolments\Pages;

use App\Enums\ChildEnrolmentStatus;
use App\Filament\Resources\ChildEnrolments\ChildEnrolmentResource;
use App\Models\ChildEnrolment;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListChildEnrolments extends ListRecords
{
    protected static string $resource = ChildEnrolmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New Enrolment'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Enrolments'),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ChildEnrolmentStatus::ACTIVE))
                ->badge(ChildEnrolment::where('status', ChildEnrolmentStatus::ACTIVE)->count()),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ChildEnrolmentStatus::PENDING))
                ->badge(ChildEnrolment::where('status', ChildEnrolmentStatus::PENDING)->count()),
            'current' => Tab::make('Current')
                ->modifyQueryUsing(fn (Builder $query) => $query->current())
                ->badge(ChildEnrolment::current()->count()),
        ];
    }
}
