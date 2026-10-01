<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlaceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_autocomplete_normalizes_places_api_new_response(): void
    {
        Http::fake(['places.googleapis.com/v1/places:autocomplete' => Http::response([
            'suggestions' => [['placePrediction' => [
                'placeId' => 'ChIJ123',
                'text' => ['text' => 'Kafe Jakarta'],
                'structuredFormat' => ['mainText' => ['text' => 'Kafe Jakarta'], 'secondaryText' => ['text' => 'Jakarta, Indonesia']],
            ]]],
        ])]);

        $this->actingAs(User::factory()->create())->getJson(route('places.autocomplete', ['query' => 'kafe']))
            ->assertOk()->assertJsonPath('0.place_id', 'ChIJ123')->assertJsonPath('0.name', 'Kafe Jakarta');
    }
}
