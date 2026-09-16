<?php
namespace App\Notifications;

use App\Models\StockRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StockRequestApproved extends Notification implements ShouldQueue
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
        $statusText = $this->stockRequest->status === 'partially_approved' ? 'Partially Approved' : 'Approved';
        $isPartial = $this->stockRequest->status === 'partially_approved';

        // Build items list showing approved vs requested
        $itemsList = $this->stockRequest->items
            ->map(function ($item) {
                $approved = $item->quantity_approved ?? 0;
                $requested = $item->quantity_requested;
                $status = $approved === $requested ? '✅' : ($approved > 0 ? '⚠️' : '❌');

                return "• {$item->inventoryItem->name}: {$approved}/{$requested} {$item->inventoryItem->unit_of_measure} {$status}";
            })
            ->join("\n");

        return (new MailMessage)
            ->subject("✅ Stock Request {$statusText} - {$this->stockRequest->request_number}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Great news! Your stock request has been {$statusText}.")
            ->line("**Request Details:**")
            ->line("- Request #: {$this->stockRequest->request_number}")
            ->line("- Facility: {$this->stockRequest->requestingFacility->name}")
            ->line("- Central Store: {$this->stockRequest->centralStore->name}")
            ->line("- Approved by: {$this->stockRequest->approvedBy->full_name}")
            ->line("- Approved on: {$this->stockRequest->approved_date->format('M j, Y g:i A')}")
            ->line("")
            ->line("**Approved Items (Approved/Requested):**")
            ->line($itemsList)
            ->line("")
            ->line("**Financial Summary:**")
            ->line("- Total Requested Value: KES " . number_format($this->stockRequest->total_requested_value, 2))
            ->line("- Total Approved Value: KES " . number_format($this->stockRequest->total_approved_value, 2))
            ->when($isPartial, function ($message) {
                $savings = $this->stockRequest->total_requested_value - $this->stockRequest->total_approved_value;
                return $message->line("- Difference: KES " . number_format($savings, 2));
            })
            ->line("")
            ->when($this->stockRequest->status === 'dispatched', function ($message) {
                return $message->line("🚚 **ITEMS HAVE BEEN DISPATCHED**")
                    ->line("Your items are on the way! Expected delivery within 2-3 business days.")
                    ->line("Dispatch Date: {$this->stockRequest->dispatch_date->format('M j, Y g:i A')}");
            })
            ->when($this->stockRequest->status !== 'dispatched', function ($message) {
                return $message->line("⏳ **Next Steps:**")
                    ->line("• Items will be prepared for dispatch")
                    ->line("• You will receive a dispatch notification")
                    ->line("• Expected dispatch within 24 hours");
            })
            ->when($isPartial, function ($message) {
                return $message->line("")
                    ->line("ℹ️ **Partial Approval Notice:**")
                    ->line("Some items were not fully approved due to stock limitations.")
                    ->line("You may submit a new request for the remaining quantities.");
            })
            ->action('View Request Details', url("/admin/stock-requests/{$this->stockRequest->id}"))
            ->line('Thank you for using our inventory management system!');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'stock_request_approved',
            'title' => 'Stock Request Approved',
            'message' => "Request #{$this->stockRequest->request_number} has been approved for KES " . number_format($this->stockRequest->total_approved_value, 2),
            'stock_request_id' => $this->stockRequest->id,
            'request_number' => $this->stockRequest->request_number,
            'facility_name' => $this->stockRequest->requestingFacility->name,
            'approved_value' => $this->stockRequest->total_approved_value,
            'status' => $this->stockRequest->status,
            'is_dispatched' => $this->stockRequest->status === 'dispatched',
            'action_url' => "/admin/stock-requests/{$this->stockRequest->id}",
        ];
    }

    public function toBroadcast($notifiable): array
    {
        return [
            'type' => 'stock_request_approved',
            'title' => 'Request Approved!',
            'message' => "Your request #{$this->stockRequest->request_number} has been approved",
            'request_number' => $this->stockRequest->request_number,
            'is_dispatched' => $this->stockRequest->status === 'dispatched',
        ];
    }
}
