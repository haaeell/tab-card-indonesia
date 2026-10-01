<?php

namespace Tests\Feature;

use App\Models\ReviewQr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CardActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_prepares_card_without_owner_pin(): void
    {
        $this->actingAs(User::factory()->create())->post(route('qrs.store'), ['name' => 'Kartu 001'])->assertRedirect();
        $card = ReviewQr::sole();

        $this->assertFalse($card->isActivated());
        $this->assertNull($card->activation_pin_hash);
        $this->get(route('redirect', $card->public_id))->assertOk()->assertSee('Aktivasi Kartu');
    }

    public function test_owner_activation_requires_six_digit_pin(): void
    {
        $card = $this->pendingCard();
        Http::fake();

        $this->post(route('cards.activate', $card->public_id), ['place_id' => 'ChIJ123', 'pin' => '123'])
            ->assertSessionHasErrors('pin');
        Http::assertNothingSent();
    }

    public function test_owner_activates_card_and_sets_own_pin(): void
    {
        $card = $this->pendingCard();
        Http::fake(['places.googleapis.com/v1/places/*' => Http::response([
            'id' => 'ChIJ123', 'displayName' => ['text' => 'Kafe Contoh'], 'formattedAddress' => 'Jakarta, Indonesia', 'googleMapsUri' => 'https://maps.google.com/example',
        ])]);

        $this->post(route('cards.activate', $card->public_id), ['place_id' => 'ChIJ123', 'pin' => '123456'])
            ->assertRedirect(route('cards.activated', $card->public_id));

        $card->refresh();
        $this->assertTrue(Hash::check('123456', $card->activation_pin_hash));
        $this->assertSame('123456', Crypt::decryptString($card->activation_pin_encrypted));
        $this->get(route('redirect', $card->public_id))->assertRedirect($card->review_url);
    }

    public function test_owner_cannot_change_business_with_wrong_pin(): void
    {
        $card = $this->activatedCard();
        Http::fake();

        $this->post(route('cards.manage.update', $card->public_id), ['place_id' => 'ChIJnew', 'pin' => '000000'])
            ->assertSessionHasErrors('pin');
        Http::assertNothingSent();
    }

    public function test_admin_reset_removes_owner_pin(): void
    {
        $card = $this->activatedCard();

        $this->actingAs(User::factory()->create())->post(route('qrs.pin', $card))->assertRedirect();
        $this->assertNull($card->fresh()->activation_pin_hash);
        $this->assertNull($card->fresh()->activation_pin_encrypted);
    }

    public function test_admin_can_set_owner_pin(): void
    {
        $card = $this->activatedCard();

        $this->actingAs(User::factory()->create())->put(route('qrs.pin.update', $card), ['pin' => '654321'])->assertRedirect(route('qrs.show', $card));

        $card->refresh();
        $this->assertTrue(Hash::check('654321', $card->activation_pin_hash));
        $this->assertSame('654321', Crypt::decryptString($card->activation_pin_encrypted));
    }

    private function pendingCard(): ReviewQr
    {
        return ReviewQr::create(['name' => 'Kartu 001', 'is_active' => true]);
    }

    private function activatedCard(): ReviewQr
    {
        return ReviewQr::create([
            'name' => 'Kartu 001', 'place_id' => 'ChIJ123', 'place_name' => 'Kafe', 'place_address' => 'Jakarta',
            'maps_url' => 'https://maps.google.com', 'review_url' => 'https://google.com/review', 'is_active' => true,
            'activation_pin_hash' => Hash::make('123456'), 'activated_at' => now(),
        ]);
    }
}
