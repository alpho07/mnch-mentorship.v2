<?php
namespace App\Notifications;

use App\Models\StockRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StockRequestStatusUpdate extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public StockRequest $stockRequest,
        public string $updateMessage,
        public ?string $actionUrl = null
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail($notifiable): MailMessage
    {
        $statusIcon = match($this->stockRequest->status) {
            'pending' => '⏳',
            'approved', 'partially_approved' => '✅',
            'rejected' => '❌',
            'dispatched', 'partially_dispatched' => '🚚',
            'received', 'partially_received' => '📦',
            'cancelled' => '🚫',
            default => 'ℹ️'
        };

        return (new MailMessage)
            ->subject("{$statusIcon} Stock Request Update - {$this->stockRequest->request_number}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("There's an update on your stock request:")
            ->line("")
            ->line("**Request #:** {$this->stockRequest->request_number}")
            ->line("**Current Status:** " . ucfirst(str_replace('_', ' ', $this->stockRequest->status)))
            ->line("**Update:** {$this->updateMessage}")
            ->line("")
            ->when($this->actionUrl, function ($message) {
                return $message->action('View Details', $this->actionUrl);
            })
            ->line('Thank you for using our inventory management system.');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'stock_request_status_update',
            'title' => 'Stock Request Update',
            'message' => $this->updateMessage,
            'stock_request_id' => $this->stockRequest->id,
            'request_number' => $this->stockRequest->request_number,
            'status' => $this->stockRequest->status,
            'action_url' => $this->actionUrl,
        ];
    }

    public function toBroadcast($notifiable): array
    {
        return [
            'type' => 'stock_request_update',
            'title' => 'Request Update',
            'message' => $this->updateMessage,
            'request_number' => $this->stockRequest->request_number,
            'status' => $this->stockRequest->status,
        ];
    }
}
