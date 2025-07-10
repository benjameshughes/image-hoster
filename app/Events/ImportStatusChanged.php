<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Import;
use App\Enums\ImportStatus;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ImportStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Import $import,
        public readonly ImportStatus $previousStatus,
        public readonly ImportStatus $newStatus,
        public readonly ?string $message = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return collect([
            new PrivateChannel("import.{$this->import->id}"),
            new PrivateChannel("user.{$this->import->user_id}.imports"),
        ])->toArray();
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return collect([
            'import_id' => $this->import->id,
            'name' => $this->import->name,
            'previous_status' => $this->previousStatus->value,
            'new_status' => $this->newStatus->value,
            'status_label' => $this->newStatus->label(),
            'status_color' => $this->newStatus->color(),
            'message' => $this->message,
            'changed_at' => now()->toISOString(),
            'total_items' => $this->import->total_items,
            'processed_items' => $this->import->processed_items,
            'successful_items' => $this->import->successful_items,
            'failed_items' => $this->import->failed_items,
            'duplicate_items' => $this->import->duplicate_items,
        ])->filter(fn($value, $key) => $value !== null || in_array($key, ['total_items', 'processed_items', 'successful_items', 'failed_items', 'duplicate_items']))->toArray();
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'import.status.changed';
    }
}