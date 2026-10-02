<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class GooglePlaces
{
    public function autocomplete(string $query): array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['X-Goog-Api-Key' => config('services.google.places_key')])
                ->post('https://places.googleapis.com/v1/places:autocomplete', [
                    'input' => $query,
                    'includedPrimaryTypes' => ['establishment'],
                    'languageCode' => 'id',
                ]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['query' => 'Pencarian bisnis sedang tidak tersedia.']);
        }

        if (! $response->successful()) {
            $this->fail($response, 'query');
        }

        return collect($response->json('suggestions', []))
            ->map(fn (array $suggestion) => $suggestion['placePrediction'] ?? null)
            ->filter()
            ->map(fn (array $place) => [
                'place_id' => $place['placeId'],
                'name' => $place['structuredFormat']['mainText']['text'] ?? $place['text']['text'],
                'address' => $place['structuredFormat']['secondaryText']['text'] ?? '',
            ])->values()->all();
    }

    public function detail(string $placeId): array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'X-Goog-Api-Key' => config('services.google.places_key'),
                    'X-Goog-FieldMask' => 'id,displayName,formattedAddress,googleMapsUri',
                ])
                ->get('https://places.googleapis.com/v1/places/'.rawurlencode($placeId));
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['place_id' => 'Detail bisnis sedang tidak tersedia.']);
        }

        if (! $response->successful()) {
            $this->fail($response, 'place_id');
        }

        $place = $response->json();
        if (blank($place['id'] ?? null) || blank($place['displayName']['text'] ?? null)) {
            throw ValidationException::withMessages(['place_id' => 'Bisnis yang dipilih tidak valid.']);
        }

        return [
            'place_id' => $place['id'],
            'place_name' => $place['displayName']['text'],
            'place_address' => $place['formattedAddress'] ?? '',
            'maps_url' => $place['googleMapsUri'] ?? 'https://www.google.com/maps/place/?q=place_id:'.$place['id'],
            'review_url' => 'https://search.google.com/local/writereview?placeid='.$place['id'],
        ];
    }

    private function fail(Response $response, string $field): never
    {
        Log::warning('Google Places request failed.', ['status' => $response->status(), 'body' => $response->json()]);

        throw ValidationException::withMessages([
            $field => 'Pencarian Google Maps sedang tidak tersedia. Coba lagi nanti atau gunakan link review manual.',
        ]);
    }
}
