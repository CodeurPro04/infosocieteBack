<?php

namespace App\Http\Controllers;

use App\Services\ContentStore;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function __construct(private ContentStore $store)
    {
    }

    public function show()
    {
        $content = $this->store->read();

        return response()->json($content);
    }

    public function page(string $slug)
    {
        $content = $this->store->read();
        $pages = $content['pages'] ?? [];

        if (!array_key_exists($slug, $pages)) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        return response()->json($pages[$slug]);
    }

    public function search(Request $request)
    {
        $query = strtolower((string) $request->input('query', ''));
        $content = $this->store->read();
        $samples = $content['search']['samples'] ?? [];

        if ($query === '') {
            return response()->json(['results' => $samples]);
        }

        $filtered = array_values(array_filter($samples, function ($item) use ($query) {
            $haystack = strtolower(
                ($item['name'] ?? '').' '.($item['address'] ?? '').' '.($item['status'] ?? '').' '.($item['ape'] ?? '')
            );
            return str_contains($haystack, $query);
        }));

        return response()->json(['results' => $filtered]);
    }
}
