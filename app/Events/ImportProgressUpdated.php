<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Import;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ImportProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Import $import,
        public ?Collection $additionalData = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("import.{$this->import->id}"),
            new PrivateChannel("user.{$this->import->user_id}.imports"),
        ];
    }

    public function broadcastWith(): array
    {
        $data = [
            'import' => [
                'id' => $this->import->id,
                'status' => $this->import->status->value,
                'total_items' => $this->import->total_items,
                'processed_items' => $this->import->processed_items,
                'successful_items' => $this->import->successful_items,
                'failed_items' => $this->import->failed_items,
                'duplicate_items' => $this->import->duplicate_items,
                'progress_percentage' => $this->import->progress_percentage,
                'success_rate' => $this->import->success_rate,
                'current_item' => $this->import->current_item?->only(['id', 'title', 'source_url']),
            ]
        ];

        if ($this->additionalData) {
            $data = array_merge($data, $this->additionalData->toArray());
        }

        return $data;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'import.progress.updated';
    }
}