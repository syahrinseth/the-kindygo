<?php

namespace App\Filament\Parent\Pages;

use App\Actions\Undertaking\CheckParentUndertakingAgreementAction;
use App\Actions\Undertaking\RecordParentUndertakingAgreementAction;
use App\Models\LetterOfUndertaking;
use App\Models\Tenant;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class AgreementPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $slug = 'agreement/pending';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public ?LetterOfUndertaking $pendingLetter = null;

    public ?Tenant $tenant = null;

    public function mount(): void
    {
        $user = auth()->user();
        $this->tenant = Auth::user()->currentTenant();

        // Check for pending letter
        $this->pendingLetter = app(CheckParentUndertakingAgreementAction::class)
            ->execute($user, $this->tenant);

        // Redirect to dashboard if no pending letter
        if (! $this->pendingLetter) {
            redirect()->to('/parent');
        }

        $this->form->fill();
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Letter of Undertaking')
                    ->description('Please review the following Letter of Undertaking and confirm your agreement.')
                    ->schema([
                        ViewComponent::make('filament.parent.components.letter-content')
                            ->viewData([
                                'letter' => $this->pendingLetter,
                                'tenant' => $this->tenant,
                            ]),
                        Checkbox::make('agreed')
                            ->label('I have read and agree to the Letter of Undertaking')
                            ->required()
                            ->accepted(),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('submit')
                ->label('Submit Agreement')
                ->action('submitAgreement')
                ->requiresConfirmation()
                ->modalHeading('Confirm Agreement')
                ->modalDescription('By confirming, you agree to the Letter of Undertaking displayed above.')
                ->modalSubmitActionLabel('I Agree'),
        ];
    }

    public function submitAgreement(): void
    {
        $this->form->validate();

        $user = auth()->user();

        // Record the agreement
        app(RecordParentUndertakingAgreementAction::class)
            ->execute($user, $this->pendingLetter, $this->tenant, request());

        Notification::make()
            ->success()
            ->title('Agreement Submitted')
            ->body('Thank you for agreeing to the Letter of Undertaking.')
            ->send();

        redirect()->to('/parent');
    }

    public function getTitle(): string
    {
        return 'Agreement Required';
    }
}
