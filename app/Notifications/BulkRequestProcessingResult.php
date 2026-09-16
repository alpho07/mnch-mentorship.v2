<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BulkRequestProcessingResult extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public array $results,
        public string $action // 'approved' or 'rejected'
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $actionText = ucfirst($this->action);
        $successCount = count($this->results['approved'] ?? $this->results['rejected'] ?? []);
        $failedCount = count($this->results['failed'] ?? []);
        $errorCount = count($this->results['errors'] ?? []);

        $successList = '';
        if ($successCount > 0) {
            $requests = $this->results['approved'] ?? $this->results['rejected'] ?? [];
            $successList = collect($requests)->take(10)->map(fn($req) => "• {$req}")->join("\n");
            if (count($requests) > 10) {
                $successList .= "\n• ... and " . (count($requests) - 10) . " more";
            }
        }

        $failedList = '';
        if ($failedCount > 0) {
            $failed = collect($this->results['failed'])->take(5);
            $failedList = $failed->map(fn($item) => "• {$item['request_number']}: {$item['reason']}")->join("\n");
            if (count($this->results['failed']) > 5) {
                $failedList .= "\n• ... and " . (count($this->results['failed']) - 5) . " more";
            }
        }

        return (new MailMessage)
            ->subject("📋 Bulk Stock Request Processing Complete - {$successCount} {$actionText}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Your bulk stock request processing operation has been completed.")
            ->line("")
            ->line("**Processing Summary:**")
            ->line("- Action: {$actionText}")
            ->line("- Successfully {$this->action}: {$successCount} requests")
            ->line("- Failed: {$failedCount} requests")
            ->line("- Errors: {$errorCount} requests")
            ->when($successCount > 0, function ($message) use ($successList) {
                return $message->line("")
                    ->line("**Successfully {$this->action} Requests:**")
                    ->line($successList);
            })
            ->when($failedCount > 0, function ($message) use ($failedList) {
                return $message->line("")
                    ->line("**Failed Requests:**")
                    ->line($failedList);
            })
            ->when($errorCount > 0, function ($message) {
                return $message->line("")
                    ->line("**⚠️ Errors Encountered:**")
                    ->line("Some requests could not be processed due to system errors.")
                    ->line("Please check individual requests and try again if needed.");
            })
            ->action('View Requests', url('/admin/stock-request-notifications'))
            ->line('Thank you for efficiently managing our inventory system!');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'bulk_request_processing',
            'title' => 'Bulk Processing Complete',
            'message' => "Bulk {$this->action} completed: " . count($this->results['approved'] ?? $this->results['rejected'] ?? []) . " successful",
            'action' => $this->action,
            'results' => $this->results,
            'success_count' => count($this->results['approved'] ?? $this->results['rejected'] ?? []),
            'failed_count' => count($this->results['failed'] ?? []),
            'error_count' => count($this->results['errors'] ?? []),
            'action_url' => '/admin/stock-request-notifications',
        ];
    }
}
