<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UploadFileProcessed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $sessionId,
        public int $fileIndex,
        public string $filename,
        public string $status,
        public ?string $error = null,
        public ?array $fileData = null
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
            'file_index' => $this->fileIndex,
            'filename' => $this->filename,
            'status' => $this->status,
            'error' => $this->error,
            'file_data' => $this->fileData,
        ];
    }

    public function broadcastAs(): string
    {
        return 'upload.file.processed';
    }
}
