<?php

namespace Tests\Feature;

use App\Models\ReviewQr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewQrTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_qr_records_scan_then_redirects(): void
    {
        $qr = ReviewQr::create([
            'name' => 'Kasir', 'place_id' => 'abc', 'place_name' => 'Kafe', 'place_address' => 'Jakarta',
            'maps_url' => 'https://maps.google.com', 'review_url' => 'https://google.com/review', 'is_active' => true,
        ]);

        $this->get(route('redirect', $qr->public_id))->assertRedirect('https://google.com/review');
        $this->assertDatabaseHas('qr_scans', ['review_qr_id' => $qr->id]);
        $this->assertSame(1, $qr->fresh()->total_scans);
    }

    public function test_inactive_qr_does_not_redirect_or_record_scan(): void
    {
        $qr = ReviewQr::create([
            'name' => 'Kasir', 'place_id' => 'abc', 'place_name' => 'Kafe', 'place_address' => 'Jakarta',
            'maps_url' => 'https://maps.google.com', 'review_url' => 'https://google.com/review', 'is_active' => false,
        ]);

        $this->get(route('redirect', $qr->public_id))->assertNotFound();
        $this->assertDatabaseMissing('qr_scans', ['review_qr_id' => $qr->id]);
    }

    public function test_qr_downloads_as_png_and_svg(): void
    {
        $qr = ReviewQr::create([
            'name' => 'Kasir', 'place_id' => 'abc', 'place_name' => 'Kafe', 'place_address' => 'Jakarta',
            'maps_url' => 'https://maps.google.com', 'review_url' => 'https://google.com/review', 'is_active' => true,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('qrs.download', [$qr, 'png']))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
        $this->get(route('qrs.download', [$qr, 'svg']))
            ->assertOk()
            ->assertHeader('content-type', 'image/svg+xml');
    }
}
