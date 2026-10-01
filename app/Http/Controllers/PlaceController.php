<?php

namespace App\Http\Controllers;

use App\Services\GooglePlaces;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaceController extends Controller
{
    public function autocomplete(Request $request, GooglePlaces $places): JsonResponse
    {
        $data = $request->validate(['query' => ['required', 'string', 'min:2', 'max:200']]);

        return response()->json($places->autocomplete($data['query']));
    }

    public function show(Request $request, GooglePlaces $places): JsonResponse
    {
        $data = $request->validate(['place_id' => ['required', 'string', 'max:255']]);
        $place = $places->detail($data['place_id']);

        return response()->json([
            'place_id' => $place['place_id'],
            'name' => $place['place_name'],
            'address' => $place['place_address'],
            'maps_url' => $place['maps_url'],
            'review_url' => $place['review_url'],
        ]);
    }
}
