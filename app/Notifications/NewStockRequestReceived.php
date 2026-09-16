<?php
// 1. New Stock Request Received Notification
// app/Notifications/NewStockRequestReceived.php

namespace App\Notifications;

use App\Models\StockRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class NewStockRequestReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public StockRequest $stockRequest
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail($notifiable): MailMessage
    {
        $priorityText = match($this->stockRequest->priority) {
            'urgent' => '🚨 URGENT',
            'high' => '⚠️ HIGH PRIORITY',
            'medium' => '📋 MEDIUM PRIORITY',
            'low' => '📝 LOW PRIORITY',
            default => '📋 MEDIUM PRIORITY'
        };

        $itemsList = $this->stockRequest->items->take(5)
            ->map(fn($item) => "• {$item->inventoryItem->name}: {$item->quantity_requested} {$item->inventoryItem->unit_of_measure}")
            ->join("\n");

        if ($this->stockRequest->items->count() > 5) {
            $itemsList .= "\n• ... and " . ($this->stockRequest->items->count() - 5) . " more items";
        }

        return (new MailMessage)
            ->subject("[$priorityText] New Stock Request - {$this->stockRequest->request_number}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("A new stock request has been submitted and requires your attention.")
            ->line("**Request Details:**")
            ->line("- Request #: {$this->stockRequest->request_number}")
            ->line("- From: {$this->stockRequest->requestingFacility->name}")
            ->line("- Requested by: {$this->stockRequest->requestedBy->full_name}")
            ->line("- Priority: " . ucfirst($this->stockRequest->priority))
            ->line("- Total Items: {$this->stockRequest->total_items}")
            ->line("- Total Value: KES " . number_format($this->stockRequest->total_requested_value, 2))
            ->line("- Date: {$this->stockRequest->request_date->format('M j, Y')}")
            ->line("")
            ->line("**Requested Items:**")
            ->line($itemsList)
            ->when($this->stockRequest->notes, function ($message) {
                return $message->line("")
                    ->line("**Additional Notes:**")
                    ->line($this->stockRequest->notes);
            })
            ->line("")
            ->line("**Next Steps:**")
            ->line("• Review the request for stock availability")
            ->line("• Approve or reject individual items as needed")
            ->line("• Process the request promptly to avoid delays")
            ->action('Review Request', url("/admin/stock-request-notifications/{$this->stockRequest->id}/review"))
            ->line('Thank you for maintaining our inventory system efficiently!');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'new_stock_request',
            'title' => 'New Stock Request Received',
            'message' => "New {$this->stockRequest->priority} priority request #{$this->stockRequest->request_number} from {$this->stockRequest->requestingFacility->name}",
            'stock_request_id' => $this->stockRequest->id,
            'request_number' => $this->stockRequest->request_number,
            'facility_name' => $this->stockRequest->requestingFacility->name,
            'priority' => $this->stockRequest->priority,
            'total_items' => $this->stockRequest->total_items,
            'total_value' => $this->stockRequest->total_requested_value,
            'action_url' => "/admin/stock-request-notifications/{$this->stockRequest->id}/review",
            'created_at' => now(),
        ];
    }

    public function toBroadcast($notifiable): array
    {
        return [
            'type' => 'new_stock_request',
            'title' => 'New Stock Request',
            'message' => "Request #{$this->stockRequest->request_number} needs approval",
            'priority' => $this->stockRequest->priority,
            'request_number' => $this->stockRequest->request_number,
            'facility_name' => $this->stockRequest->requestingFacility->name,
        ];
    }
}
