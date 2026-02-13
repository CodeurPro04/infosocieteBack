<?php

namespace App\Services;

use App\Models\Submission;

class SubmissionStore
{
    public function append(string $type, array $payload): void
    {
        $id = $payload['id'] ?? $this->generateId($type);

        unset($payload['id']);
        Submission::create([
            'submission_id' => $id,
            'type' => $type,
            'payload' => $payload,
        ]);
    }

    public function all(): array
    {
        return Submission::query()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Submission $submission) => [
                'id' => $submission->submission_id,
                'type' => $submission->type,
                'payload' => $submission->payload ?? [],
                'created_at' => optional($submission->created_at)->toDateTimeString(),
            ])
            ->all();
    }

    private function generateId(string $type): string
    {
        $prefixes = [
            'contact' => 'CON',
            'cancellation' => 'RES',
            'claim' => 'REC',
            'signup' => 'SIGN',
            'kbis_request' => 'KBIS',
            'payment' => 'PAY',
        ];

        $prefix = $prefixes[$type] ?? 'SUB';
        $random = random_int(100000, 999999);

        return $prefix.'-'.$random;
    }
}
