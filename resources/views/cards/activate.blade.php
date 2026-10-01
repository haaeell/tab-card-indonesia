<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Aktivasi Kartu | Tab Card Indonesia</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="activation-page">
    <main class="activation-wrap">
        <div class="activation-brand"><span class="brand-mark"><i data-lucide="scan-line"></i></span><span>Tab Card<small>INDONESIA</small></span></div>
        <section class="activation-content">
            <p class="eyebrow">KARTU REVIEW</p>
            <h1>Aktivasi Kartu</h1>
            <p class="activation-lead">Pilih bisnis dan buat PIN untuk mengamankan perubahan bisnis.</p>
            <p class="card-code">Kode kartu <strong>{{ $qr->public_id }}</strong></p>
            <form method="post" action="{{ route('cards.activate', $qr->public_id) }}" id="activation-form" class="activation-form">
                @csrf
                <label for="place-search">Nama bisnis</label>
                <div class="search">
                    <i data-lucide="search"></i>
                    <input id="place-search" data-search-url="{{ route('cards.places', $qr->public_id) }}" autocomplete="off" placeholder="Cari bisnis di Google Maps" value="{{ old('selected_name') }}">
                </div>
                <div id="places"></div>
                <input type="hidden" name="place_id" id="place-id" value="{{ old('place_id') }}">
                <input type="hidden" name="selected_name" id="selected-name" value="{{ old('selected_name') }}">
                <div id="selected-place" class="selected-place {{ old('place_id') ? '' : 'hidden' }}"><i data-lucide="map-pin"></i><span>{{ old('selected_name') }}</span></div>
                @error('place_id')<p class="error">{{ $message }}</p>@enderror
                <label for="activation-pin">Buat PIN</label>
                <input id="activation-pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="new-password" placeholder="6 angka" required>
                <label for="activation-pin-confirmation">Konfirmasi PIN</label>
                <input id="activation-pin-confirmation" name="pin_confirmation" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="new-password" placeholder="Ulangi PIN" required>
                @error('pin')<p class="error">{{ $message }}</p>@enderror
                <button class="primary activation-submit" type="submit"><i data-lucide="check"></i>Aktifkan Kartu</button>
            </form>
        </section>
    </main>
</body>
</html>
