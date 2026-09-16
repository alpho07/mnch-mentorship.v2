<?php
namespace App\Notifications;

use App\Models\StockRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class StockRequestRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public StockRequest $stockRequest,
        public string $rejectionReason
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail($notifiable): MailMessage
    {
        $itemsList = $this->stockRequest->items->take(5)
            ->map(fn($item) => "• {$item->inventoryItem->name}: {$item->quantity_requested} {$item->inventoryItem->unit_of_measure}")
            ->join("\n");

        if ($this->stockRequest->items->count() > 5) {
            $itemsList .= "\n• ... and " . ($this->stockRequest->items->count() - 5) . " more items";
        }

        return (new MailMessage)
            ->subject("❌ Stock Request Rejected - {$this->stockRequest->request_number}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("We regret to inform you that your stock request has been rejected.")
            ->line("**Request Details:**")
            ->line("- Request #: {$this->stockRequest->request_number}")
            ->line("- Facility: {$this->stockRequest->requestingFacility->name}")
            ->line("- Central Store: {$this->stockRequest->centralStore->name}")
            ->line("- Rejected by: {$this->stockRequest->approvedBy->full_name}")
            ->line("- Rejected on: {$this->stockRequest->approved_date->format('M j, Y g:i A')}")
            ->line("- Total Value: KES " . number_format($this->stockRequest->total_requested_value, 2))
            ->line("")
            ->line("**Requested Items:**")
            ->line($itemsList)
            ->line("")
            ->line("**Reason for Rejection:**")
            ->line($this->rejectionReason)
            ->line("")
            ->line("**What to do next:**")
            ->line("• Review the rejection reason carefully")
            ->line("• Check current stock availability at central store")
            ->line("• Consider reducing quantities or selecting alternative items")
            ->line("• Contact the central store manager for clarification if needed")
            ->line("• Submit a revised request with appropriate modifications")
            ->line("")
            ->line("**Alternative Actions:**")
            ->line("• Check if items are available at other central stores")
            ->line("• Consider splitting the request into smaller batches")
            ->line("• Look for substitute items that serve the same purpose")
            ->action('Create New Request', url("/admin/stock-requests/create"))
            ->line('If you have questions about this rejection, please contact your central store manager directly.');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'stock_request_rejected',
            'title' => 'Stock Request Rejected',
            'message' => "Request #{$this->stockRequest->request_number} was rejected: " . Str::limit($this->rejectionReason, 100),
            'stock_request_id' => $this->stockRequest->id,
            'request_number' => $this->stockRequest->request_number,
            'facility_name' => $this->stockRequest->requestingFacility->name,
            'rejection_reason' => $this->rejectionReason,
            'rejected_by' => $this->stockRequest->approvedBy->full_name,
            'action_url' => "/admin/stock-requests/create",
        ];
    }

    public function toBroadcast($notifiable): array
    {
        return [
            'type' => 'stock_request_rejected',
            'title' => 'Request Rejected',
            'message' => "Request #{$this->stockRequest->request_number} was rejected",
            'request_number' => $this->stockRequest->request_number,
        ];
    }
}
