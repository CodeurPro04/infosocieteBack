<?php

namespace App\Http\Controllers;

use App\Services\SubmissionStore;
use Illuminate\Http\Request;

class FormController extends Controller
{
    public function __construct(private SubmissionStore $store)
    {
    }

    public function contact(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email',
            'company' => 'nullable|string|max:160',
            'message' => 'required|string|max:4000',
        ]);

        $this->store->append('contact', $payload);

        return response()->json(['status' => 'received']);
    }

    public function cancel(Request $request)
    {
        $payload = $request->validate([
            'email' => 'required|email',
        ]);

        $this->store->append('cancellation', $payload);

        return response()->json(['status' => 'received']);
    }

    public function claim(Request $request)
    {
        $payload = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email',
            'order' => 'nullable|string|max:120',
            'message' => 'required|string|max:4000',
        ]);

        $this->store->append('claim', $payload);

        return response()->json(['status' => 'received']);
    }
}
