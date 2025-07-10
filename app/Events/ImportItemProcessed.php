<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ImportItem;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ImportItemProcessed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ImportItem $importItem,
        public readonly bool $successful = true,
        public readonly ?string $errorMessage = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return collect([
            new PrivateChannel("import.{$this->importItem->import_id}"),
            new PrivateChannel("user.{$this->importItem->import->user_id}.imports"),
        ])->toArray();
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return collect([
            'import_id' => $this->importItem->import_id,
            'item_id' => $this->importItem->id,
            'source_id' => $this->importItem->source_id,
            'title' => $this->importItem->title,
            'status' => is_string($this->importItem->status) ? $this->importItem->status : $this->importItem->status->value,
            'successful' => $this->successful,
            'error_message' => $this->errorMessage,
            'file_size' => $this->importItem->file_size,
            'mime_type' => $this->importItem->mime_type,
            'processed_at' => now()->toISOString(),
        ])->filter(fn($value) => $value !== null)->toArray();
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'import.item.processed';
    }
}