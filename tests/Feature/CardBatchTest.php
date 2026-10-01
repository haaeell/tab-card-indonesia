<?php

namespace Tests\Feature;

use App\Models\ReviewQr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_generates_batch_then_downloads_pending_cards(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->post(route('qrs.batch.store'), ['quantity' => 3, 'prefix' => 'Cabang']);

        $response->assertRedirect(route('qrs.index'));
        $this->assertDatabaseCount('review_qrs', 3);
        $this->assertSame(['Cabang 001', 'Cabang 002', 'Cabang 003'], ReviewQr::orderBy('id')->pluck('name')->all());
        $this->assertSame(3, ReviewQr::query()->where('is_active', true)->whereNull('review_url')->count());
        $this->actingAs(User::factory()->create())->get(route('qrs.pending.download'))
            ->assertOk()->assertHeader('content-type', 'application/zip');
    }

    public function test_mass_delete_keeps_owner_activated_cards(): void
    {
        ReviewQr::create(['name' => 'Kosong', 'is_active' => true]);
        $active = ReviewQr::create([
            'name' => 'Aktif', 'place_id' => 'abc', 'place_name' => 'Kafe', 'place_address' => 'Jakarta',
            'maps_url' => 'https://maps.google.com', 'review_url' => 'https://google.com/review', 'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create())->delete(route('qrs.pending.destroy'))->assertRedirect(route('qrs.index'));

        $this->assertDatabaseMissing('review_qrs', ['name' => 'Kosong']);
        $this->assertDatabaseHas('review_qrs', ['id' => $active->id]);
    }
}
