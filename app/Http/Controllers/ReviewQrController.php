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
                ->editColumn('place_name', fn (ReviewQr $qr) => '<strong>'.$qr->place_name.'</strong><small>'.$qr->place_address.'</small>')
                ->editColumn('is_active', fn (ReviewQr $qr) => '<span class="badge '.($qr->is_active ? 'badge-green' : 'badge-gray').'">'.($qr->is_active ? 'Aktif' : 'Nonaktif').'</span>')
                ->addColumn('action', fn (ReviewQr $qr) => view('qrs.partials.actions', compact('qr'))->render())
                ->rawColumns(['place_name', 'is_active', 'action'])
                ->toJson();
        }

        return view('qrs.index');
    }

    public function create(): View
    {
        return view('qrs.form', ['qr' => new ReviewQr]);
    }

    public function store(Request $request): RedirectResponse
    {
        $qr = ReviewQr::create($this->validated($request));

        return redirect()->route('qrs.show', $qr)->with('success', 'QR Review berhasil dibuat.');
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
        $qr->update($this->validated($request));

        return redirect()->route('qrs.show', $qr)->with('success', 'QR Review diperbarui.');
    }

    public function destroy(ReviewQr $qr): RedirectResponse
    {
        $qr->delete();

        return redirect()->route('qrs.index')->with('success', 'QR Review dihapus.');
    }

    public function toggle(ReviewQr $qr): JsonResponse
    {
        $qr->update(['is_active' => ! $qr->is_active]);

        return response()->json(['message' => $qr->is_active ? 'QR diaktifkan.' : 'QR dinonaktifkan.', 'is_active' => $qr->is_active]);
    }

    public function download(ReviewQr $qr, string $format)
    {
        abort_unless(in_array($format, ['png', 'svg'], true), 404);
        $result = (new Builder(
            writer: $format === 'png' ? new PngWriter : new SvgWriter,
            data: route('redirect', $qr->public_id),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 800,
            margin: 16,
        ))->build();

        return response($result->getString(), 200, ['Content-Type' => $result->getMimeType(), 'Content-Disposition' => 'attachment; filename="'.$qr->public_id.'.'.$format.'"']);
    }

    public function redirect(Request $request, string $publicId): RedirectResponse|Response
    {
        $qr = ReviewQr::active()->where('public_id', $publicId)->first();
        if (! $qr) {
            return response()->view('errors.qr-unavailable', [], 404);
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
