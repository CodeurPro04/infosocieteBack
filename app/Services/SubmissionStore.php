<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class SubmissionStore
{
    private string $path;

    public function __construct()
    {
        $this->path = storage_path('app/submissions.json');
    }

    public function append(string $type, array $payload): void
    {
        $data = $this->all();
        $data[] = [
            'type' => $type,
            'payload' => $payload,
            'created_at' => now()->toDateTimeString(),
        ];

        File::ensureDirectoryExists(dirname($this->path));
        File::put($this->path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function all(): array
    {
        if (!File::exists($this->path)) {
            return [];
        }

        $raw = File::get($this->path);
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }
}
