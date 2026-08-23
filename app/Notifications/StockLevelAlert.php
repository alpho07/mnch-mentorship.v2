<?php

namespace App\Notifications;

use App\Models\Facility;
use App\Models\InventoryItem;
use App\Notifications\Concerns\FilamentDatabasePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StockLevelAlert extends Notification implements ShouldQueue
{
    use FilamentDatabasePayload;
    use Queueable;

    public function __construct(
        public Facility $facility,
        public InventoryItem $item,
        public int $currentStock,
        public string $alertType // 'low_stock', 'out_of_stock', 'critical'
    ) {}

    public function eventKey(): string
    {
        return \App\Support\NotificationEvents::STOCK_LEVEL_ALERT;
    }

    public function toMail($notifiable): MailMessage
    {
        $alertConfig = match ($this->alertType) {
            'out_of_stock' => [
                'icon' => '🚨',
                'title' => 'OUT OF STOCK ALERT',
                'color' => 'danger',
                'description' => 'This item is completely out of stock',
            ],
            'critical' => [
                'icon' => '⚠️',
                'title' => 'CRITICAL LOW STOCK ALERT',
                'color' => 'warning',
                'description' => 'Stock level is critically low',
            ],
            'low_stock' => [
                'icon' => '📉',
                'title' => 'LOW STOCK ALERT',
                'color' => 'info',
                'description' => 'Stock level is below reorder point',
            ],
            default => [
                'icon' => 'ℹ️',
                'title' => 'STOCK ALERT',
                'color' => 'info',
                'description' => 'Stock level requires attention',
            ]
        };

        return (new MailMessage)
            ->subject("{$alertConfig['icon']} {$alertConfig['title']} - {$this->item->name}")
            ->greeting("Hello {$notifiable->first_name},")
            ->line("{$alertConfig['icon']} **{$alertConfig['title']}**")
            ->line($alertConfig['description'])
            ->line('')
            ->line('**Item Details:**')
            ->line("- Item: {$this->item->name}")
            ->line("- SKU: {$this->item->sku}")
            ->line("- Category: {$this->item->category->name}")
            ->line("- Facility: {$this->facility->name}")
            ->line("- Current Stock: {$this->currentStock} {$this->item->unit_of_measure}")
            ->line("- Reorder Point: {$this->item->reorder_point} {$this->item->unit_of_measure}")
            ->when($this->item->minimum_stock_level, function ($message) {
                return $message->line("- Minimum Level: {$this->item->minimum_stock_level} {$this->item->unit_of_measure}");
            })
            ->line('')
            ->line('**Recommended Actions:**')
            ->when($this->alertType === 'out_of_stock', function ($message) {
                return $message->line('• Submit an URGENT stock request immediately')
                    ->line('• Check if item is available at other facilities')
                    ->line('• Consider emergency procurement if critical');
            })
            ->when($this->alertType === 'critical', function ($message) {
                return $message->line('• Submit a HIGH PRIORITY stock request')
                    ->line('• Monitor usage closely')
                    ->line('• Prepare for potential stockout');
            })
            ->when($this->alertType === 'low_stock', function ($message) {
                return $message->line('• Submit a stock request to replenish')
                    ->line('• Review usage patterns')
                    ->line('• Consider adjusting reorder points');
            })
            ->line('• Update inventory records if count is incorrect')
            ->line('• Notify relevant staff about stock status')
            ->action('Create Stock Request', url("/admin/stock-requests/create?item_id={$this->item->id}&facility_id={$this->facility->id}"))
            ->line('Prompt action will help prevent service disruptions.');
    }

    public function toDatabase($notifiable): array
    {
        return $this->filamentPayload([
            'type' => 'stock_level_alert',
            'message' => "{$this->item->name} at {$this->facility->name} is {$this->alertType} ({$this->currentStock} units)",
            'facility_id' => $this->facility->id,
            'facility_name' => $this->facility->name,
            'item_id' => $this->item->id,
            'item_name' => $this->item->name,
            'item_sku' => $this->item->sku,
            'current_stock' => $this->currentStock,
            'reorder_point' => $this->item->reorder_point,
            'alert_type' => $this->alertType,
            'action_url' => "/admin/stock-requests/create?item_id={$this->item->id}&facility_id={$this->facility->id}",
        ]);
    }

    protected function notificationTitle(): string
    {
        return match ($this->alertType) {
            'out_of_stock' => 'Out of Stock Alert',
            'critical' => 'Critical Low Stock Alert',
            default => 'Low Stock Alert',
        };
    }

    protected function notificationBody(): string
    {
        return "{$this->item->name} at {$this->facility->name} is {$this->alertType} ({$this->currentStock} units)";
    }

    protected function notificationIcon(): string
    {
        return 'heroicon-o-exclamation-triangle';
    }

    protected function notificationColor(): string
    {
        return match ($this->alertType) {
            'out_of_stock' => 'danger',
            'critical' => 'warning',
            default => 'info',
        };
    }
}
