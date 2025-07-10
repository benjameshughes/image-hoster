<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CloudUploadProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $sessionId,
        public string $filename,
        public int $bytesUploaded,
        public int $totalBytes,
        public float $percentage,
        public ?float $speed = null,
        public ?int $eta = null,
        public string $phase = 'uploading'
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
            'filename' => $this->filename,
            'bytes_uploaded' => $this->bytesUploaded,
            'total_bytes' => $this->totalBytes,
            'percentage' => $this->percentage,
            'speed' => $this->speed,
            'eta' => $this->eta,
            'phase' => $this->phase,
            'formatted_uploaded' => $this->formatFileSize($this->bytesUploaded),
            'formatted_total' => $this->formatFileSize($this->totalBytes),
            'formatted_speed' => $this->speed ? $this->formatFileSize((int) $this->speed) . '/s' : null,
        ];
    }

    public function broadcastAs(): string
    {
        return 'upload.cloud.progress';
    }

    /**
     * Format file size for display
     */
    private function formatFileSize(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 ** 3 => number_format($bytes / (1024 ** 3), 2) . ' GB',
            $bytes >= 1024 ** 2 => number_format($bytes / (1024 ** 2), 2) . ' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 2) . ' KB',
            default => $bytes . ' B',
        };
    }
}