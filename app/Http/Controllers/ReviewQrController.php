<?php

namespace App\Http\Controllers;

use App\Models\ReviewQr;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ReviewQrController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax()) {
            return DataTables::eloquent(ReviewQr::query()->latest())
                ->addIndexColumn()
                ->editColumn('place_name', fn (ReviewQr $qr) => '<strong>'.e($qr->place_name ?: 'Menunggu aktivasi').'</strong><small>'.e($qr->place_address ?: 'Bisnis belum dipilih').'</small>')
                ->editColumn('is_active', fn (ReviewQr $qr) => '<span class="badge '.($qr->is_active && $qr->isActivated() ? 'badge-green' : 'badge-gray').'">'.($qr->is_active ? ($qr->isActivated() ? 'Aktif' : 'Belum aktif') : 'Nonaktif').'</span>')
                ->addColumn('action', fn (ReviewQr $qr) => view('qrs.partials.actions', compact('qr'))->render())
                ->rawColumns(['place_name', 'is_active', 'action'])
                ->toJson();
        }

        return view('qrs.index');
    }

    public function create(): View
    {
        return view('qrs.create');
    }

    public function createBatch(): View
    {
        return view('qrs.batch');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['nullable', 'string', 'max:100']]);
        $qr = ReviewQr::create([
            'name' => ($data['name'] ?? null) ?: 'Kartu Baru',
            'is_active' => true,
        ]);

        return redirect()->route('qrs.show', $qr)
            ->with('success', 'Kartu siap dicetak dan diprogram ke NFC.');
    }

    public function storeBatch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'prefix' => ['nullable', 'string', 'max:80'],
        ]);
        $prefix = trim($data['prefix'] ?? '') ?: 'Kartu';
        DB::transaction(function () use ($data, $prefix) {
            return collect(range(1, $data['quantity']))->map(fn (int $number) => ReviewQr::create([
                'name' => $prefix.' '.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'is_active' => true,
            ]));
        });

        return redirect()->route('qrs.index')->with('success', $data['quantity'].' kartu berhasil dibuat. Download ZIP saat siap.');
    }

    public function downloadPending()
    {
        $cards = ReviewQr::query()->whereNull('review_url')->orderBy('id')->get();
        abort_if($cards->isEmpty(), 404, 'Tidak ada kartu belum aktif untuk diunduh.');

        $path = tempnam(storage_path('app'), 'tab-card-');
        $zip = new \ZipArchive;
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Gagal membuat ZIP kartu.');
        }
        foreach ($cards as $card) {
            $baseName = str_pad((string) $card->id, 5, '0', STR_PAD_LEFT).'-'.$card->public_id;
            $zip->addFromString($baseName.'.png', $this->qr($card, new PngWriter, $card->name)->getString());
        }
        $zip->close();

        return response()->download($path, 'tab-card-belum-aktif-'.now()->format('Ymd-His').'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    public function show(ReviewQr $qr): View
    {
        return view('qrs.show', compact('qr'));
    }

    public function edit(ReviewQr $qr): View
    {
        return view('qrs.form', compact('qr'));
    }

    public function update(Request $request, ReviewQr $qr): RedirectResponse
    {
        $qr->update($this->validated($request) + ['activated_at' => $qr->activated_at ?? now()]);

        return redirect()->route('qrs.show', $qr)->with('success', 'QR Review diperbarui.');
    }

    public function regeneratePin(ReviewQr $qr): RedirectResponse
    {
        $qr->update(['activation_pin_hash' => null]);

        return redirect()->route('qrs.show', $qr)
            ->with('success', 'PIN direset. Owner perlu membuat PIN baru saat aktivasi.');
    }

    public function destroy(ReviewQr $qr): RedirectResponse
    {
        $qr->delete();

        return redirect()->route('qrs.index')->with('success', 'QR Review dihapus.');
    }

    public function destroyPending(): RedirectResponse
    {
        $count = ReviewQr::query()->whereNull('review_url')->count();
        ReviewQr::query()->whereNull('review_url')->delete();

        return redirect()->route('qrs.index')->with('success', $count.' kartu belum aktivasi dihapus.');
    }

    public function toggle(ReviewQr $qr): JsonResponse
    {
        $qr->update(['is_active' => ! $qr->is_active]);

        return response()->json(['message' => $qr->is_active ? 'Kartu tersedia kembali.' : 'Kartu dinonaktifkan.', 'is_active' => $qr->is_active]);
    }

    public function download(ReviewQr $qr, string $format)
    {
        abort_unless(in_array($format, ['png', 'svg'], true), 404);
        $result = $this->qr($qr, $format === 'png' ? new PngWriter : new SvgWriter);

        return response($result->getString(), 200, ['Content-Type' => $result->getMimeType(), 'Content-Disposition' => 'attachment; filename="'.$qr->public_id.'.'.$format.'"']);
    }

    private function qr(ReviewQr $qr, PngWriter|SvgWriter $writer, string $label = '')
    {
        return (new Builder(
            writer: $writer,
            data: route('redirect', $qr->public_id),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 800,
            margin: 16,
            labelText: $label,
        ))->build();

    }

    public function redirect(Request $request, string $publicId): RedirectResponse|Response
    {
        $qr = ReviewQr::where('public_id', $publicId)->first();
        if (! $qr || ! $qr->is_active) {
            return response()->view('errors.qr-unavailable', [], 404);
        }
        if (! $qr->isActivated()) {
            return response()->view('cards.activate', compact('qr'));
        }

        DB::transaction(function () use ($qr, $request): void {
            $qr->increment('total_scans');
            $qr->scans()->create(['user_agent' => substr((string) $request->userAgent(), 0, 1000), 'ip_hash' => hash('sha256', (string) $request->ip()), 'scanned_at' => now()]);
        });

        return redirect()->away($qr->review_url, 302);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'place_id' => ['required', 'string', 'max:255'],
            'place_name' => ['required', 'string', 'max:255'],
            'place_address' => ['required', 'string', 'max:1000'],
            'maps_url' => ['required', 'url', 'max:2000'],
            'review_url' => ['required', 'url', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
