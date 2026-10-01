<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PlaceController extends Controller
{
    public function autocomplete(Request $request): JsonResponse
    {
        $data = $request->validate(['query' => ['required', 'string', 'min:2', 'max:200']]);
        $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/place/autocomplete/json', ['input' => $data['query'], 'types' => 'establishment', 'key' => config('services.google.places_key')]);

        return response()->json($response->json('predictions', []), $response->successful() ? 200 : 422);
    }

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate(['place_id' => ['required', 'string', 'max:255']]);
        $response = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/place/details/json', ['place_id' => $data['place_id'], 'fields' => 'place_id,name,formatted_address,url', 'key' => config('services.google.places_key')]);
        if (! $response->successful() || ! $place = $response->json('result')) {
            return response()->json(['message' => 'Detail tempat tidak tersedia.'], 422);
        }

        return response()->json([
            'place_id' => $place['place_id'],
            'name' => $place['name'],
            'address' => $place['formatted_address'],
            'maps_url' => $place['url'] ?? 'https://www.google.com/maps/place/?q=place_id:'.$place['place_id'],
            'review_url' => 'https://search.google.com/local/writereview?placeid='.$place['place_id'],
        ]);
    }
}
