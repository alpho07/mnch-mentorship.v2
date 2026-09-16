<?php

namespace App\Notifications;

use App\Models\StockRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StockRequestReceived extends Notification implements ShouldQueue
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
        $isPartialReceipt = $this->stockRequest->status === 'partially_received';
        $statusText = $isPartialReceipt ? 'Partially Received' : 'Fully Received';

        // Build received items list
        $itemsList = $this->stockRequest->items
            ->filter(fn($item) => ($item->quantity_received ?? 0) > 0)
            ->map(function ($item) {
                $received = $item->quantity_received ?? 0;
                $dispatched = $item->quantity_dispatched ?? 0;
                $status = $received === $dispatched ? '✅' : ($received > 0 ? '⚠️' : '❌');

                return "• {$item->inventoryItem->name}: {$received}/{$dispatched} {$item->inventoryItem->unit_of_measure} {$status}";
            })
            ->join("\n");

        return (new MailMessage)
            ->subject("📦 Items {$statusText} - {$this->stockRequest->request_number}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("This is to confirm that items from your central store have been received by the requesting facility.")
            ->line("**Receipt Details:**")
            ->line("- Request #: {$this->stockRequest->request_number}")
            ->line("- Received by: {$this->stockRequest->receivedBy->full_name}")
            ->line("- Receipt Date: {$this->stockRequest->received_date->format('M j, Y g:i A')}")
            ->line("- Receiving Facility: {$this->stockRequest->requestingFacility->name}")
            ->line("")
            ->line("**Received Items (Received/Dispatched):**")
            ->line($itemsList)
            ->line("")
            ->line("**Value Summary:**")
            ->line("- Dispatched Value: KES " . number_format($this->stockRequest->total_dispatched_value, 2))
            ->line("- Received Value: KES " . number_format($this->stockRequest->total_received_value, 2))
            ->when($isPartialReceipt, function ($message) {
                $variance = $this->stockRequest->total_dispatched_value - $this->stockRequest->total_received_value;
                return $message->line("- Variance: KES " . number_format($variance, 2))
                    ->line("")
                    ->line("⚠️ **Partial Receipt Notice:**")
                    ->line("Not all dispatched items were received. Please investigate any discrepancies.");
            })
            ->line("")
            ->line("**Transaction Complete:**")
            ->line("• Stock has been deducted from your central store")
            ->line("• Items have been added to the receiving facility's inventory")
            ->line("• All inventory transactions have been recorded")
            ->action('View Request Details', url("/admin/stock-requests/{$this->stockRequest->id}"))
            ->line('Thank you for supporting our inventory distribution network!');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'stock_request_received',
            'title' => 'Items Received Confirmation',
            'message' => "Items from request #{$this->stockRequest->request_number} have been received by {$this->stockRequest->requestingFacility->name}",
            'stock_request_id' => $this->stockRequest->id,
            'request_number' => $this->stockRequest->request_number,
            'facility_name' => $this->stockRequest->requestingFacility->name,
            'received_date' => $this->stockRequest->received_date,
            'received_value' => $this->stockRequest->total_received_value,
            'is_partial' => $this->stockRequest->status === 'partially_received',
            'action_url' => "/admin/stock-requests/{$this->stockRequest->id}",
        ];
    }
}
