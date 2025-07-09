<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UploadCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $sessionId,
        public int $totalFiles,
        public int $successfulFiles,
        public int $failedFiles,
        public array $uploadedFiles
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
            'total_files' => $this->totalFiles,
            'successful_files' => $this->successfulFiles,
            'failed_files' => $this->failedFiles,
            'uploaded_files' => $this->uploadedFiles,
            'success_rate' => $this->totalFiles > 0 ? round(($this->successfulFiles / $this->totalFiles) * 100, 2) : 0,
        ];
    }

    public function broadcastAs(): string
    {
        return 'upload.completed';
    }
}
