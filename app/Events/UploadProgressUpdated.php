<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class UploadProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $sessionId,
        public Collection $files,
        public int $currentIndex,
        public int $totalFiles,
        public ?array $currentFile = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->userId}.uploads"),
            new PrivateChannel("upload.{$this->sessionId}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'files' => $this->files->toArray(),
            'current_index' => $this->currentIndex,
            'total_files' => $this->totalFiles,
            'current_file' => $this->currentFile,
            'progress_percentage' => $this->totalFiles > 0 ? round(($this->currentIndex / $this->totalFiles) * 100, 2) : 0,
        ];
    }

    public function broadcastAs(): string
    {
        return 'upload.progress.updated';
    }
}
