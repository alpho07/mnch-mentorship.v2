<?php

namespace App\Notifications;

use App\Models\StockRequest;
use App\Notifications\Concerns\FilamentDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StockRequestVeryOverdue extends Notification implements ShouldQueue
{
    use FilamentDatabasePayload;
    use Queueable;

    public function __construct(
        public StockRequest $stockRequest
    ) {}

    public function eventKey(): string
    {
        return \App\Support\NotificationEvents::STOCK_VERY_OVERDUE_ALERT;
    }

    public function toMail($notifiable): MailMessage
    {
        $daysPending = $this->stockRequest->days_pending;

        return (new MailMessage)
            ->subject("🚨 URGENT: Your Stock Request is Severely Delayed - {$this->stockRequest->request_number}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("We want to update you on the status of your stock request that has been pending for **{$daysPending} days**.")
            ->line('')
            ->line('**Request Details:**')
            ->line("- Request #: {$this->stockRequest->request_number}")
            ->line("- Facility: {$this->stockRequest->requestingFacility->name}")
            ->line("- Central Store: {$this->stockRequest->centralStore->name}")
            ->line("- Submitted: {$this->stockRequest->created_at->format('M j, Y')}")
            ->line("- Days Pending: **{$daysPending} days**")
            ->line('- Total Value: KES '.number_format($this->stockRequest->total_requested_value, 2))
            ->line('')
            ->line('**We understand the urgency** and sincerely apologize for this delay.')
            ->line('')
            ->line('**Possible Reasons for Delay:**')
            ->line('• Stock shortages at the central store')
            ->line('• High demand for requested items')
            ->line('• Supply chain disruptions')
            ->line('• Processing bottlenecks')
            ->line('')
            ->line('**Recommended Actions:**')
            ->line('• Contact your central store manager directly')
            ->line('• Consider alternative or substitute items')
            ->line('• Check if items are available at other stores')
            ->line('• Submit a new request with modified quantities')
            ->line('• Escalate to regional management if critical')
            ->line('')
            ->line('**Contact Information:**')
            ->line("Central Store: {$this->stockRequest->centralStore->name}")
            ->when($this->stockRequest->centralStore->phone, function ($message) {
                return $message->line("Phone: {$this->stockRequest->centralStore->phone}");
            })
            ->action('View Request Status', url("/admin/stock-requests/{$this->stockRequest->id}"))
            ->line('We are working to resolve this delay and appreciate your patience.');
    }

    public function toDatabase($notifiable): array
    {
        return $this->filamentPayload([
            'type' => 'stock_request_very_overdue',
            'message' => "Your request #{$this->stockRequest->request_number} has been pending for {$this->stockRequest->days_pending} days",
            'stock_request_id' => $this->stockRequest->id,
            'request_number' => $this->stockRequest->request_number,
            'days_pending' => $this->stockRequest->days_pending,
            'central_store_name' => $this->stockRequest->centralStore->name,
            'action_url' => "/admin/stock-requests/{$this->stockRequest->id}",
        ]);
    }

    protected function notificationTitle(): string
    {
        return 'Stock Request Severely Delayed';
    }

    protected function notificationBody(): string
    {
        return "Your request #{$this->stockRequest->request_number} has been pending for {$this->stockRequest->days_pending} days";
    }

    protected function notificationIcon(): string
    {
        return 'heroicon-o-clock';
    }

    protected function notificationColor(): string
    {
        return 'danger';
    }
}
