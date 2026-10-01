<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CardActivationController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\ReviewQrController;
use App\Models\QrScan;
use App\Models\ReviewQr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
Route::get('/r/{publicId}', [ReviewQrController::class, 'redirect'])->name('redirect');
Route::get('/r/{publicId}/places', [CardActivationController::class, 'autocomplete'])->middleware('throttle:20,1')->name('cards.places');
Route::post('/r/{publicId}/activate', [CardActivationController::class, 'activate'])->middleware('throttle:5,1')->name('cards.activate');
Route::get('/r/{publicId}/activated', [CardActivationController::class, 'activated'])->name('cards.activated');
Route::get('/r/{publicId}/manage', [CardActivationController::class, 'manage'])->name('cards.manage');
Route::post('/r/{publicId}/manage', [CardActivationController::class, 'update'])->middleware('throttle:5,1')->name('cards.manage.update');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', function () {
        return view('dashboard', [
            'total' => ReviewQr::count(), 'active' => ReviewQr::active()->count(),
            'today' => QrScan::whereDate('scanned_at', today())->count(), 'scans' => QrScan::count(),
            'recent' => ReviewQr::latest()->take(5)->get(),
        ]);
    })->name('dashboard');
    Route::resource('qrs', ReviewQrController::class);
    Route::post('/qrs/{qr}/toggle', [ReviewQrController::class, 'toggle'])->name('qrs.toggle');
    Route::get('/qrs/{qr}/download/{format}', [ReviewQrController::class, 'download'])->name('qrs.download');
    Route::post('/qrs/{qr}/pin', [ReviewQrController::class, 'regeneratePin'])->name('qrs.pin');
    Route::prefix('places')->middleware('throttle:30,1')->group(function (): void {
        Route::get('/autocomplete', [PlaceController::class, 'autocomplete'])->name('places.autocomplete');
        Route::get('/detail', [PlaceController::class, 'show'])->name('places.detail');
    });
    Route::get('/settings', fn () => view('settings'))->name('settings');
    Route::put('/settings', function (Request $request) {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'], 'password' => ['nullable', 'confirmed', 'min:8']]);
        $user = $request->user();
        $user->name = $data['name'];
        $user->email = $data['email'];
        if ($data['password'] ?? false) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        return back()->with('success', 'Pengaturan disimpan.');
    })->name('settings.update');
});
