<?php

namespace App\Filament\Resources\InvoiceResource\Actions;

use Closure;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportException;
use Illuminate\Mail\MailManager;
use Exception;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Notifications\InvoicePendingNotification;
use App\Notifications\InvoiceOverdueNotification;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;

class SendNotificationAction
{
    public static function getDefaultName(): ?string
    {
        return 'send-notification';
    }

    /**
     * Create a table action for sending invoice notifications
     */
    public static function make(): Action
    {
        return Action::make(static::getDefaultName())
            ->label('Send Notification')
            ->icon('heroicon-o-bell')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Send Invoice Notification')
            ->modalDescription(function (Invoice $record) {
                $isOverdue = $record->due_at->isPast();
                $type = $isOverdue ? 'overdue' : 'pending payment';
                return "Are you sure you want to send a {$type} notification to {$record->user->name}?";
            })
            ->action(static::getActionCallback())
            ->visible(function (Invoice $record) {
                $user = Auth::user();

                // Only show for pending or overdue invoices
                if (!in_array($record->status, [InvoiceStatus::PENDING, InvoiceStatus::OVERDUE])) {
                    return false;
                }

                // Check if user can update the invoice (admin/principal permissions)
                return $user->can('update', $record);
            });
    }

    /**
     * Get the action callback
     */
    protected static function getActionCallback(): Closure
    {
        return function (Invoice $record) {
            try {
                $isOverdue = $record->due_at->isPast();
                
                if ($isOverdue) {
                    // Calculate days overdue
                    $daysOverdue = $record->due_at->diffInDays(now());
                    
                    // Send overdue notification
                    $record->user->notify(new InvoiceOverdueNotification($record, $daysOverdue));
                    
                    Notification::make()
                        ->title('Overdue notification sent successfully')
                        ->body("Overdue notification sent to {$record->user->name} for invoice #{$record->number}")
                        ->success()
                        ->send();
                } else {
                    // Send pending payment notification
                    $record->user->notify(new InvoicePendingNotification($record));
                    
                    Notification::make()
                        ->title('Payment reminder sent successfully')
                        ->body("Payment reminder sent to {$record->user->name} for invoice #{$record->number}")
                        ->success()
                        ->send();
                }

                // Log the notification sending
                Log::info('Invoice notification sent', [
                    'invoice_id' => $record->id,
                    'invoice_number' => $record->number,
                    'user_id' => $record->user->id,
                    'user_email' => $record->user->email,
                    'notification_type' => $isOverdue ? 'overdue' : 'pending',
                    'sent_by' => Auth::user()->id,
                    'sent_at' => now(),
                ]);

            } catch (TransportException $e) {
                // Handle mailer configuration issues specifically
                Log::error('Mailer configuration error while sending invoice notification', [
                    'invoice_id' => $record->id,
                    'error' => $e->getMessage(),
                    'suggestion' => 'Check mailer configuration and credentials',
                ]);

                Notification::make()
                    ->title('Email configuration error')
                    ->body('Email service is not properly configured. Please contact the system administrator.')
                    ->danger()
                    ->send();
                    
            } catch (MailManager $e) {
                // Handle Laravel mail manager issues
                Log::error('Mail manager error while sending invoice notification', [
                    'invoice_id' => $record->id,
                    'error' => $e->getMessage(),
                    'suggestion' => 'Check MAIL_MAILER configuration',
                ]);

                Notification::make()
                    ->title('Email service error')
                    ->body('Email service configuration issue. Please contact the system administrator.')
                    ->danger()
                    ->send();
                    
            } catch (Exception $e) {
                // Handle any other exceptions
                Log::error('Failed to send invoice notification', [
                    'invoice_id' => $record->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                Notification::make()
                    ->title('Failed to send notification')
                    ->body('An error occurred while sending the notification. Please try again or contact support.')
                    ->danger()
                    ->send();
            }
        };
    }
}
