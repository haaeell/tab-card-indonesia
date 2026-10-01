<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlaceController extends Controller
{
    public function autocomplete(Request $request): JsonResponse
    {
        $data = $request->validate(['query' => ['required', 'string', 'min:2', 'max:200']]);
        $response = Http::timeout(8)
            ->withHeaders(['X-Goog-Api-Key' => config('services.google.places_key')])
            ->post('https://places.googleapis.com/v1/places:autocomplete', [
                'input' => $data['query'],
                'includedPrimaryTypes' => ['establishment'],
                'languageCode' => 'id',
            ]);

        if (! $response->successful()) {
            return $this->googleError($response);
        }

        return response()->json(collect($response->json('suggestions', []))
            ->map(fn (array $suggestion) => $suggestion['placePrediction'] ?? null)
            ->filter()
            ->map(fn (array $place) => [
                'place_id' => $place['placeId'],
                'name' => $place['structuredFormat']['mainText']['text'] ?? $place['text']['text'],
                'address' => $place['structuredFormat']['secondaryText']['text'] ?? '',
            ])->values());
    }

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate(['place_id' => ['required', 'string', 'max:255']]);
        $response = Http::timeout(8)
            ->withHeaders([
                'X-Goog-Api-Key' => config('services.google.places_key'),
                'X-Goog-FieldMask' => 'id,displayName,formattedAddress,googleMapsUri',
            ])
            ->get('https://places.googleapis.com/v1/places/'.$data['place_id']);
        if (! $response->successful()) {
            return $this->googleError($response);
        }
        $place = $response->json();

        return response()->json([
            'place_id' => $place['id'],
            'name' => $place['displayName']['text'],
            'address' => $place['formattedAddress'],
            'maps_url' => $place['googleMapsUri'] ?? 'https://www.google.com/maps/place/?q=place_id:'.$place['id'],
            'review_url' => 'https://search.google.com/local/writereview?placeid='.$place['id'],
        ]);
    }

    private function googleError($response): JsonResponse
    {
        Log::warning('Google Places request failed.', ['status' => $response->status(), 'body' => $response->json()]);

        return response()->json(['message' => $response->json('error.message', 'Google Places tidak dapat memproses permintaan.')], 422);
    }
}
