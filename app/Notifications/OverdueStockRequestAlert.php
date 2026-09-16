<?php

namespace App\Notifications;

use App\Models\StockRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OverdueStockRequestAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public StockRequest $stockRequest
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $daysPending = $this->stockRequest->days_pending;
        $urgencyLevel = match(true) {
            $daysPending >= 7 => '🚨 CRITICALLY OVERDUE',
            $daysPending >= 5 => '⚠️ SEVERELY OVERDUE',
            default => '⏰ OVERDUE'
        };

        $itemsList = $this->stockRequest->items->take(3)
            ->map(fn($item) => "• {$item->inventoryItem->name}: {$item->quantity_requested} {$item->inventoryItem->unit_of_measure}")
            ->join("\n");

        if ($this->stockRequest->items->count() > 3) {
            $itemsList .= "\n• ... and " . ($this->stockRequest->items->count() - 3) . " more items";
        }

        return (new MailMessage)
            ->subject("[$urgencyLevel] Stock Request Overdue - {$this->stockRequest->request_number}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("⚠️ **URGENT ATTENTION REQUIRED**")
            ->line("The following stock request has been pending for **{$daysPending} days** and requires immediate action.")
            ->line("")
            ->line("**Request Details:**")
            ->line("- Request #: {$this->stockRequest->request_number}")
            ->line("- From: {$this->stockRequest->requestingFacility->name}")
            ->line("- Requested by: {$this->stockRequest->requestedBy->full_name}")
            ->line("- Priority: " . ucfirst($this->stockRequest->priority))
            ->line("- Total Items: {$this->stockRequest->total_items}")
            ->line("- Total Value: KES " . number_format($this->stockRequest->total_requested_value, 2))
            ->line("- Submitted: {$this->stockRequest->created_at->format('M j, Y g:i A')}")
            ->line("- **Days Pending: {$daysPending} days**")
            ->line("")
            ->line("**Key Items:**")
            ->line($itemsList)
            ->line("")
            ->line("**Potential Impact:**")
            ->line("• Patient care services may be affected")
            ->line("• Critical facility operations could be disrupted")
            ->line("• Service delivery quality may be compromised")
            ->line("• Staff productivity may be reduced")
            ->when($this->stockRequest->priority === 'urgent', function ($message) {
                return $message->line("• **URGENT PRIORITY**: Immediate health/safety impact possible");
            })
            ->line("")
            ->line("**Required Actions:**")
            ->line("• Review stock availability immediately")
            ->line("• Approve or provide clear rejection reason")
            ->line("• If stock unavailable, suggest alternatives")
            ->line("• Coordinate with other central stores if needed")
            ->line("• Update the requesting facility on status")
            ->action('Process Request NOW', url("/admin/stock-request-notifications/{$this->stockRequest->id}/review"))
            ->line("**This request requires your immediate attention to prevent service disruption.**");
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'overdue_stock_request',
            'title' => 'OVERDUE: Stock Request Alert',
            'message' => "Request #{$this->stockRequest->request_number} is {$this->stockRequest->days_pending} days overdue and needs immediate attention",
            'stock_request_id' => $this->stockRequest->id,
            'request_number' => $this->stockRequest->request_number,
            'facility_name' => $this->stockRequest->requestingFacility->name,
            'days_pending' => $this->stockRequest->days_pending,
            'priority' => $this->stockRequest->priority,
            'total_value' => $this->stockRequest->total_requested_value,
            'urgency_level' => match(true) {
                $this->stockRequest->days_pending >= 7 => 'critical',
                $this->stockRequest->days_pending >= 5 => 'severe',
                default => 'moderate'
            },
            'action_url' => "/admin/stock-request-notifications/{$this->stockRequest->id}/review",
        ];
    }
}
