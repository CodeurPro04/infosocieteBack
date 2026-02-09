<?php

namespace App\Http\Controllers;

use App\Services\ContentStore;
use App\Services\SubmissionStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function __construct(
        private ContentStore $contentStore,
        private SubmissionStore $submissionStore
    ) {
    }

    public function login(Request $request)
    {
        $email = (string) $request->input('email', '');
        $password = (string) $request->input('password', '');

        $expectedEmail = (string) env('ADMIN_EMAIL', 'admin@infosociete.pro');
        $expectedPassword = (string) env('ADMIN_PASSWORD', 'Infosociete2026!');

        if (!hash_equals($expectedEmail, $email) || !hash_equals($expectedPassword, $password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = Str::random(48);
        $ttl = now()->addHours(8);
        Cache::put("admin_token:{$token}", true, $ttl);

        return response()->json([
            'token' => $token,
            'expires_at' => $ttl->toDateTimeString(),
        ]);
    }

    public function updateContent(Request $request)
    {
        $payload = $request->all();

        if (!is_array($payload) || empty($payload)) {
            return response()->json(['message' => 'Invalid content'], 422);
        }

        $this->contentStore->write($payload);

        return response()->json(['status' => 'ok']);
    }

    public function submissions()
    {
        return response()->json([
            'submissions' => $this->submissionStore->all(),
        ]);
    }
}
