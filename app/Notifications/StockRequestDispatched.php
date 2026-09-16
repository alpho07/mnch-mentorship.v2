<?php
namespace App\Notifications;

use App\Models\StockRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StockRequestDispatched extends Notification implements ShouldQueue
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
        $isPartialDispatch = $this->stockRequest->status === 'partially_dispatched';
        $statusText = $isPartialDispatch ? 'Partially Dispatched' : 'Dispatched';

        // Build dispatched items list
        $itemsList = $this->stockRequest->items
            ->filter(fn($item) => ($item->quantity_dispatched ?? 0) > 0)
            ->map(function ($item) {
                $dispatched = $item->quantity_dispatched ?? 0;
                $approved = $item->quantity_approved ?? 0;
                $status = $dispatched === $approved ? '✅' : '⚠️';

                return "• {$item->inventoryItem->name}: {$dispatched} {$item->inventoryItem->unit_of_measure} {$status}";
            })
            ->join("\n");

        // Build pending items list if partial dispatch
        $pendingItems = '';
        if ($isPartialDispatch) {
            $pendingItems = $this->stockRequest->items
                ->filter(fn($item) => ($item->balance_quantity ?? 0) > 0)
                ->map(fn($item) => "• {$item->inventoryItem->name}: {$item->balance_quantity} {$item->inventoryItem->unit_of_measure}")
                ->join("\n");
        }

        return (new MailMessage)
            ->subject("🚚 Items {$statusText} - {$this->stockRequest->request_number}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("Great news! Your stock request items have been {$statusText} and are on their way to your facility.")
            ->line("**Dispatch Details:**")
            ->line("- Request #: {$this->stockRequest->request_number}")
            ->line("- Dispatched by: {$this->stockRequest->dispatchedBy->full_name}")
            ->line("- Dispatch Date: {$this->stockRequest->dispatch_date->format('M j, Y g:i A')}")
            ->line("- From: {$this->stockRequest->centralStore->name}")
            ->line("- To: {$this->stockRequest->requestingFacility->name}")
            ->when($this->stockRequest->transport_method, function ($message) {
                return $message->line("- Transport: {$this->stockRequest->transport_method}");
            })
            ->when($this->stockRequest->estimated_arrival, function ($message) {
                return $message->line("- Expected Arrival: {$this->stockRequest->estimated_arrival->format('M j, Y g:i A')}");
            })
            ->when(!$this->stockRequest->estimated_arrival, function ($message) {
                return $message->line("- Expected Arrival: Within 2-3 business days");
            })
            ->line("")
            ->line("**Dispatched Items:**")
            ->line($itemsList)
            ->when($isPartialDispatch && $pendingItems, function ($message) use ($pendingItems) {
                return $message->line("")
                    ->line("**⚠️ Pending Items (Stock Not Available):**")
                    ->line($pendingItems)
                    ->line("")
                    ->line("**Note:** The remaining items will be dispatched when stock becomes available.");
            })
            ->line("")
            ->line("**Value Summary:**")
            ->line("- Dispatched Value: KES " . number_format($this->stockRequest->total_dispatched_value, 2))
            ->when($isPartialDispatch, function ($message) {
                $pending = $this->stockRequest->total_approved_value - $this->stockRequest->total_dispatched_value;
                return $message->line("- Pending Value: KES " . number_format($pending, 2));
            })
            ->line("")
            ->line("**Next Steps:**")
            ->line("• Prepare to receive the items at your facility")
            ->line("• Ensure proper storage space is available")
            ->line("• Verify all items upon arrival")
            ->line("• Check quantities and condition of received items")
            ->line("• Update your inventory records after receipt")
            ->line("• Report any discrepancies immediately")
            ->when($this->stockRequest->tracking_number, function ($message) {
                return $message->line("• Track shipment using: {$this->stockRequest->tracking_number}");
            })
            ->action('Track Request', url("/admin/stock-requests/{$this->stockRequest->id}"))
            ->line('Please confirm receipt of items once they arrive at your facility.');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'stock_request_dispatched',
            'title' => 'Items Dispatched',
            'message' => "Items for request #{$this->stockRequest->request_number} have been dispatched (KES " . number_format($this->stockRequest->total_dispatched_value, 2) . ")",
            'stock_request_id' => $this->stockRequest->id,
            'request_number' => $this->stockRequest->request_number,
            'facility_name' => $this->stockRequest->requestingFacility->name,
            'dispatch_date' => $this->stockRequest->dispatch_date,
            'estimated_arrival' => $this->stockRequest->estimated_arrival,
            'dispatched_value' => $this->stockRequest->total_dispatched_value,
            'is_partial' => $this->stockRequest->status === 'partially_dispatched',
            'action_url' => "/admin/stock-requests/{$this->stockRequest->id}",
        ];
    }

    public function toBroadcast($notifiable): array
    {
        return [
            'type' => 'stock_request_dispatched',
            'title' => 'Items Dispatched!',
            'message' => "Items for request #{$this->stockRequest->request_number} are on the way",
            'request_number' => $this->stockRequest->request_number,
            'estimated_arrival' => $this->stockRequest->estimated_arrival?->format('M j'),
        ];
    }
}
