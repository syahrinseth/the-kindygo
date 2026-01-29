<?php

namespace App\Filament\Admin\Resources\ChildEnrolments;

use App\Filament\Admin\Resources\ChildEnrolments\Pages\CreateChildEnrolment;
use App\Filament\Admin\Resources\ChildEnrolments\Pages\EditChildEnrolment;
use App\Filament\Admin\Resources\ChildEnrolments\Pages\ListChildEnrolments;
use App\Filament\Admin\Resources\ChildEnrolments\Pages\ViewChildEnrolment;
use App\Filament\Admin\Resources\ChildEnrolments\RelationManagers\InvoiceItemsRelationManager;
use App\Filament\Admin\Resources\ChildEnrolments\Schemas\ChildEnrolmentForm;
use App\Filament\Admin\Resources\ChildEnrolments\Tables\ChildEnrolmentsTable;
use App\Models\ChildEnrolment;
use App\Policies\ChildEnrolmentPolicy;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ChildEnrolmentResource extends Resource
{
    protected static ?string $model = ChildEnrolment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Child Enrolments';

    protected static ?string $modelLabel = 'Child Enrolment';

    protected static ?string $pluralModelLabel = 'Child Enrolments';

    protected static string|\UnitEnum|null $navigationGroup = 'Child Management';

    protected static ?int $navigationSort = 2;

    protected static string $policy = ChildEnrolmentPolicy::class;

    public static function form(Schema $schema): Schema
    {
        return ChildEnrolmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChildEnrolmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            InvoiceItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChildEnrolments::route('/'),
            'create' => CreateChildEnrolment::route('/create'),
            'view' => ViewChildEnrolment::route('/{record}'),
            'edit' => EditChildEnrolment::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getEloquentQuery()->active()->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'success';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if (! $user) {
            return $query->whereRaw('1 = 0'); // Return empty result if no user
        }

        // Super Admin can see all enrolments
        if ($user->hasRole('Super Admin')) {
            return $query;
        }

        // Admin can see all enrolments in their tenant
        if ($user->hasRole('Admin')) {
            return $query->where('tenant_id', $user->current_tenant_id);
        }

        // Principal and Teacher can see enrolments for centres they have access to
        if ($user->hasAnyRole(['Principal', 'Teacher'])) {
            $userCentreIds = $user->centres()
                ->where('centres.tenant_id', $user->current_tenant_id)
                ->pluck('centres.id');

            return $query->where('tenant_id', $user->current_tenant_id)
                ->whereIn('centre_id', $userCentreIds);
        }

        // Parents can see enrolments for their children only
        if ($user->hasRole('Parent')) {
            $childIds = $user->children()->pluck('children.id');

            return $query->where('tenant_id', $user->current_tenant_id)
                ->whereIn('child_id', $childIds);
        }

        // Default: no access
        return $query->whereRaw('1 = 0');
    }
}
