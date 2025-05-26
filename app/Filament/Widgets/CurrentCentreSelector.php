<?php

namespace App\Filament\Widgets;

use App\Models\Centre;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class CurrentCentreSelector extends Widget implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.widgets.current-centre-selector';
    
    protected static ?string $heading = 'Current Centre';
    
    // Temporarily disable this widget
    protected static bool $shouldLoad = false;
    
    public ?string $currentCentreId = null;
    
    public $data = [];

    public function mount(): void
    {
        $user = Auth::user();
        $currentCentre = $user->getCurrentCentre();
        $this->currentCentreId = $currentCentre ? (string) $currentCentre->id : null;
        
        $this->form->fill([
            'current_centre_id' => $this->currentCentreId,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('current_centre_id')
                    ->label('Current Centre')
                    ->options(function () {
                        // Use the scope from Centre model to get centres for current tenant and user
                        return Centre::forCurrentUser()->pluck('name', 'id');
                    })
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        $this->updateCurrentCentre($state);
                    })
                    ->placeholder('Select a centre')
                    ->helperText('Select your current working centre'),
            ])
            ->statePath('data');
    }

    public function updateCurrentCentre(?string $centreId): void
    {
        $user = Auth::user();
        
        if ($centreId) {
            // Ensure the centre belongs to the current tenant and user has access to it
            $centre = Centre::forCurrentUser()->find($centreId);
            
            if ($centre) {                
                $user->setCurrentCentre($centre);
                
                $this->currentCentreId = $centreId;
                $this->dispatch('current-centre-updated', centreId: $centreId);
                
                Notification::make()
                    ->success()
                    ->title('Centre updated successfully')
                    ->send();
            }
        }
    }

    public static function canView(): bool
    {
        return false; // Temporarily disable the widget
    }
}
