<?php

namespace App\Filament\Admin\Widgets;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class ProfileCompletionWidget extends Widget
{
    protected static ?string $heading = 'Complete Your Profile';

    protected string $view = 'filament.widgets.profile-completion-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1; // Display at the top

    public static function canView(): bool
    {
        $user = Auth::user();

        // Only show for Parents
        if (! $user || ! $user->hasRole('Parent')) {
            return false;
        }

        // Show if profile is completed but missing documents or children
        if ($user->profile_completed) {
            return $user->getMedia('photo')->isEmpty() ||
                   $user->getMedia('mykad')->isEmpty() ||
                   $user->getMedia('immunization')->isEmpty() ||
                   $user->children()->count() === 0;
        }

        return false;
    }

    public function getMissingItems(): array
    {
        $user = Auth::user();
        $missing = [];

        if ($user->getMedia('photo')->isEmpty()) {
            $missing[] = [
                'title' => 'Profile Photo',
                'description' => 'Upload your profile photo to personalize your account.',
                'icon' => 'heroicon-o-user-circle',
                'action' => 'uploadProfilePhoto',
            ];
        }

        if ($user->getMedia('mykad')->isEmpty()) {
            $missing[] = [
                'title' => 'MyKad Document',
                'description' => 'Upload a copy of your MyKad for verification purposes.',
                'icon' => 'heroicon-o-identification',
                'action' => 'uploadMykad',
            ];
        }

        if ($user->getMedia('immunization')->isEmpty()) {
            $missing[] = [
                'title' => 'Immunization Card',
                'description' => 'Upload your child\'s immunization card for health records.',
                'icon' => 'heroicon-o-document-text',
                'action' => 'uploadImmunization',
            ];
        }

        if ($user->children()->count() === 0) {
            $missing[] = [
                'title' => 'Add Children',
                'description' => 'Add your children to enroll them in our programmes.',
                'icon' => 'heroicon-o-user-group',
                'action' => 'addChild',
            ];
        }

        return $missing;
    }

    public function uploadProfilePhotoAction(): Action
    {
        return Action::make('uploadProfilePhoto')
            ->label('Upload Profile Photo')
            ->icon('heroicon-o-arrow-up-tray')
            ->form([
                FileUpload::make('profile_photo')
                    ->label('Profile Photo')
                    ->image()
                    ->maxSize(5120)
                    ->required(),
            ])
            ->action(function (array $data) {
                $user = Auth::user();

                if (isset($data['profile_photo'])) {
                    $user->clearMediaCollection('photo');
                    $user->addMedia($data['profile_photo'])->toMediaCollection('photo');

                    Notification::make()
                        ->title('Profile Photo Uploaded')
                        ->body('Your profile photo has been uploaded successfully.')
                        ->success()
                        ->send();
                }
            });
    }

    public function uploadMykadAction(): Action
    {
        return Action::make('uploadMykad')
            ->label('Upload MyKad')
            ->icon('heroicon-o-arrow-up-tray')
            ->form([
                FileUpload::make('mykad_image')
                    ->label('MyKad Document')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                    ->maxSize(10240)
                    ->required(),
            ])
            ->action(function (array $data) {
                $user = Auth::user();

                if (isset($data['mykad_image'])) {
                    $user->clearMediaCollection('mykad');
                    $user->addMedia($data['mykad_image'])->toMediaCollection('mykad');

                    Notification::make()
                        ->title('MyKad Uploaded')
                        ->body('Your MyKad document has been uploaded successfully.')
                        ->success()
                        ->send();
                }
            });
    }

    public function uploadImmunizationAction(): Action
    {
        return Action::make('uploadImmunization')
            ->label('Upload Immunization Card')
            ->icon('heroicon-o-arrow-up-tray')
            ->form([
                FileUpload::make('immunization_card')
                    ->label('Immunization Card')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                    ->maxSize(10240)
                    ->required(),
            ])
            ->action(function (array $data) {
                $user = Auth::user();

                if (isset($data['immunization_card'])) {
                    $user->clearMediaCollection('immunization');
                    $user->addMedia($data['immunization_card'])->toMediaCollection('immunization');

                    Notification::make()
                        ->title('Immunization Card Uploaded')
                        ->body('The immunization card has been uploaded successfully.')
                        ->success()
                        ->send();
                }
            });
    }

    public function addChildAction(): Action
    {
        return Action::make('addChild')
            ->label('Add Child')
            ->icon('heroicon-o-user-plus')
            ->url(fn () => route('filament.app.resources.children.create'))
            ->openUrlInNewTab(false);
    }
}
