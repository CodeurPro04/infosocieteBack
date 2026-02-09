<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class ContentStore
{
    private string $path;

    public function __construct()
    {
        $this->path = storage_path('app/content.json');
    }

    public function read(): array
    {
        if (!File::exists($this->path)) {
            return [];
        }

        $raw = File::get($this->path);
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    public function write(array $data): void
    {
        File::ensureDirectoryExists(dirname($this->path));
        File::put($this->path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
