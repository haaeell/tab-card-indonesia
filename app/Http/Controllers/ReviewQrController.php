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
            return DataTables::eloquent(ReviewQr::query()->orderBy('id'))
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
            $numbers = ReviewQr::query()
                ->where('name', 'like', $prefix.' %')
                ->pluck('name')
                ->map(fn (string $name) => preg_match('/^'.preg_quote($prefix, '/').' (\d+)$/', $name, $match) ? (int) $match[1] : 0);
            $start = $numbers->max() + 1;

            return collect(range($start, $start + $data['quantity'] - 1))->map(fn (int $number) => ReviewQr::create([
                'name' => $prefix.' '.str_pad((string) $number, max(3, strlen((string) $number)), '0', STR_PAD_LEFT),
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
            $baseName = preg_replace('/[^A-Za-z0-9._ -]/', '-', $card->name).'-'.$card->public_id;
            $zip->addFromString($baseName.'.png', $this->coloredPng($card, $card->name));
        }
        $zip->close();

        return response()->download($path, '-qrcode-'.now()->format('Ymd-His').'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
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
        if ($format === 'png') {
            return response($this->coloredPng($qr, $qr->name), 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'attachment; filename="'.$qr->public_id.'.png"']);
        }
        $result = $this->qr($qr, new SvgWriter, $qr->name);

        return response($result->getString(), 200, ['Content-Type' => $result->getMimeType(), 'Content-Disposition' => 'attachment; filename="'.$qr->public_id.'.'.$format.'"']);
    }

    private function qr(ReviewQr $qr, PngWriter|SvgWriter $writer, string $label = '')
    {
        return (new Builder(
            writer: $writer,
            data: route('redirect', $qr->public_id),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 800,
            margin: 16,
            labelText: $label,
        ))->build();
    }

    private function coloredPng(ReviewQr $qr, string $label): string
    {
        $matrix = $this->qr($qr, new PngWriter)->getMatrix();
        $labelHeight = 44;
        $image = imagecreatetruecolor($matrix->getOuterSize(), $matrix->getOuterSize() + $labelHeight);
        $white = imagecolorallocate($image, 255, 255, 255);
        $dark = imagecolorallocate($image, 18, 26, 43);
        imagefill($image, 0, 0, $white);

        for ($row = 0; $row < $matrix->getBlockCount(); $row++) {
            for ($column = 0; $column < $matrix->getBlockCount(); $column++) {
                if ($matrix->getBlockValue($row, $column) !== 1) {
                    continue;
                }
                $color = imagecolorallocate($image, ...$this->gradientColor($row, $column, $matrix->getBlockCount()));
                $left = (int) ($matrix->getMarginLeft() + ($matrix->getBlockSize() * $column));
                $top = (int) ($matrix->getMarginLeft() + ($matrix->getBlockSize() * $row));
                imagefilledrectangle($image, $left, $top, (int) ($left + $matrix->getBlockSize()), (int) ($top + $matrix->getBlockSize()), $color);
            }
        }

        $textWidth = imagefontwidth(5) * strlen($label);
        imagestring($image, 5, max(0, (int) (($matrix->getOuterSize() - $textWidth) / 2)), $matrix->getOuterSize() + 13, $label, $dark);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    /** @return array{int, int, int} */
    private function gradientColor(int $row, int $column, int $count): array
    {
        $top = $row < $count / 2;
        $left = $column < $count / 2;

        return match ([$top, $left]) {
            [true, true] => [224, 56, 73],
            [true, false] => [245, 180, 37],
            [false, true] => [45, 166, 94],
            default => [61, 104, 210],
        };
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
