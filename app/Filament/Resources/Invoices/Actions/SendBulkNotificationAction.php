<?php

namespace App\Filament\Resources\Invoices\Actions;

use Exception;
use Closure;
use Illuminate\Support\Facades\Log;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Notifications\InvoicePendingNotification;
use App\Notifications\InvoiceOverdueNotification;
use Filament\Notifications\Notification;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class SendBulkNotificationAction
{
    public static function getDefaultName(): ?string
    {
        return 'send-bulk-notification';
    }

    /**
     * Create a bulk action for sending invoice notifications
     */
    public static function make(): BulkAction
    {
        return BulkAction::make(static::getDefaultName())
            ->label('Send Notifications')
            ->icon('heroicon-o-bell')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Send Invoice Notifications')
            ->modalDescription('Are you sure you want to send notifications for the selected invoices?')
            ->action(static::getActionCallback())
            ->deselectRecordsAfterCompletion()
            ->visible(function () {
                $user = Auth::user();
                
                // Check if user can perform bulk operations on invoices
                // We'll use the 'updateAny' permission which is typically used for bulk operations
                try {
                    return $user->can('updateAny', Invoice::class);
                } catch (Exception $e) {
                    // Fallback to role-based check if policy method doesn't exist
                    if (method_exists($user, 'hasAnyRole')) {
                        return $user->hasAnyRole(['Super Admin', 'Admin', 'Principal']);
                    }
                    // Final fallback - check if user can view any invoices
                    return $user->can('viewAny', Invoice::class);
                }
            });
    }

    /**
     * Get the action callback
     */
    protected static function getActionCallback(): Closure
    {
        return function (Collection $records) {
            $successCount = 0;
            $errorCount = 0;
            $skippedCount = 0;

            foreach ($records as $invoice) {
                try {
                    // Skip if invoice is not pending or overdue
                    if (!in_array($invoice->status, [InvoiceStatus::PENDING, InvoiceStatus::OVERDUE])) {
                        $skippedCount++;
                        continue;
                    }

                    // Check if user can update this specific invoice
                    if (!Auth::user()->can('update', $invoice)) {
                        $skippedCount++;
                        continue;
                    }

                    $isOverdue = $invoice->due_at->isPast();
                    
                    if ($isOverdue) {
                        // Calculate days overdue
                        $daysOverdue = $invoice->due_at->diffInDays(now());
                        
                        // Send overdue notification
                        $invoice->user->notify(new InvoiceOverdueNotification($invoice, $daysOverdue));
                    } else {
                        // Send pending payment notification
                        $invoice->user->notify(new InvoicePendingNotification($invoice));
                    }

                    $successCount++;

                    // Log the notification sending
                    Log::info('Bulk invoice notification sent', [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->number,
                        'user_id' => $invoice->user->id,
                        'user_email' => $invoice->user->email,
                        'notification_type' => $isOverdue ? 'overdue' : 'pending',
                        'sent_by' => Auth::user()->id,
                        'sent_at' => now(),
                    ]);

                } catch (Exception $e) {
                    $errorCount++;
                    
                    Log::error('Failed to send bulk invoice notification', [
                        'invoice_id' => $invoice->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Show summary notification
            $message = "Notifications sent: {$successCount}";
            if ($errorCount > 0) {
                $message .= ", Errors: {$errorCount}";
            }
            if ($skippedCount > 0) {
                $message .= ", Skipped: {$skippedCount}";
            }

            if ($successCount > 0) {
                Notification::make()
                    ->title('Bulk notifications completed')
                    ->body($message)
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('No notifications sent')
                    ->body('No eligible invoices found or all operations failed.')
                    ->warning()
                    ->send();
            }
        };
    }
}
