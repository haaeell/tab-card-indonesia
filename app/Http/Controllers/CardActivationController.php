<?php

namespace App\Http\Controllers;

use App\Models\ReviewQr;
use App\Services\GooglePlaces;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CardActivationController extends Controller
{
    public function autocomplete(Request $request, string $publicId, GooglePlaces $places): JsonResponse
    {
        $card = ReviewQr::where('public_id', $publicId)->firstOrFail();
        abort_unless($card->is_active, 404);
        $data = $request->validate(['query' => ['required', 'string', 'min:2', 'max:200']]);

        return response()->json($places->autocomplete($data['query']));
    }

    public function activate(Request $request, string $publicId, GooglePlaces $places): RedirectResponse
    {
        $card = ReviewQr::where('public_id', $publicId)->firstOrFail();
        abort_unless($card->is_active && ! $card->isActivated(), 404);
        $data = $request->validate(['place_id' => ['nullable', 'required_without:review_url', 'string', 'max:255'], 'place_name' => ['nullable', 'string', 'max:255'], 'place_address' => ['nullable', 'string', 'max:1000'], 'review_url' => ['nullable', 'url', 'max:2000'], 'pin' => ['required', 'digits:6']]);
        $place = filled($data['review_url'] ?? null) ? $this->manualPlace($data) : $places->detail($data['place_id'] ?? '');
        ReviewQr::whereKey($card->id)
            ->where('is_active', true)
            ->whereNull('review_url')
            ->update($place + [
                'activation_pin_hash' => Hash::make($data['pin']),
                'activation_pin_encrypted' => Crypt::encryptString($data['pin']),
                'activated_at' => now(),
            ]);

        return redirect()->route('cards.activated', $publicId);
    }

    public function activated(string $publicId): View|RedirectResponse
    {
        $card = ReviewQr::where('public_id', $publicId)->firstOrFail();
        abort_unless($card->is_active, 404);
        if (! $card->isActivated()) {
            return redirect()->route('redirect', $publicId);
        }

        return view('cards.activated', compact('card'));
    }

    public function manage(string $publicId): View
    {
        $card = ReviewQr::where('public_id', $publicId)->firstOrFail();
        abort_unless($card->is_active && $card->isActivated(), 404);

        return view('cards.manage', compact('card'));
    }

    public function update(Request $request, string $publicId, GooglePlaces $places): RedirectResponse
    {
        $card = ReviewQr::where('public_id', $publicId)->firstOrFail();
        abort_unless($card->is_active && $card->isActivated(), 404);
        $data = $request->validate(['place_id' => ['nullable', 'required_without:review_url', 'string', 'max:255'], 'place_name' => ['nullable', 'string', 'max:255'], 'place_address' => ['nullable', 'string', 'max:1000'], 'review_url' => ['nullable', 'url', 'max:2000'], 'pin' => ['required', 'digits:6']]);
        if ($card->activation_pin_hash && ! Hash::check($data['pin'], $card->activation_pin_hash)) {
            throw ValidationException::withMessages(['pin' => 'PIN tidak sesuai.']);
        }
        $update = filled($data['review_url'] ?? null) ? $this->manualPlace($data) : $places->detail($data['place_id'] ?? '');
        if (! $card->activation_pin_hash) {
            $update['activation_pin_hash'] = Hash::make($data['pin']);
            $update['activation_pin_encrypted'] = Crypt::encryptString($data['pin']);
        }
        $card->update($update);

        return redirect()->route('cards.activated', $publicId);
    }

    private function manualPlace(array $data): array
    {
        $url = $data['review_url'] ?? '';
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! ($host === 'g.page' || str_ends_with($host, '.g.page') || $host === 'search.google.com')) {
            throw ValidationException::withMessages(['review_url' => 'Gunakan link Minta ulasan dari Google Maps.']);
        }

        if (blank($data['place_name'] ?? null)) {
            throw ValidationException::withMessages(['place_name' => 'Nama bisnis wajib diisi untuk link manual.']);
        }

        return [
            'place_id' => 'manual:'.hash('sha256', $url),
            'place_name' => $data['place_name'],
            'place_address' => $data['place_address'] ?? '',
            'maps_url' => $url,
            'review_url' => $url,
        ];
    }
}
