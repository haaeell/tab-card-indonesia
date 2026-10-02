<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Aktivasi Kartu | Tab Card Indonesia</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="activation-page">
    <main class="activation-wrap">
        <div class="activation-brand"><span class="brand-mark"><i data-lucide="scan-line"></i></span><span>Tab
                Card<small>INDONESIA</small></span></div>
        <section class="activation-content">
            <p class="eyebrow">KARTU REVIEW</p>
            <h1>Aktivasi Kartu</h1>
            <p class="activation-lead">Pilih bisnis dan buat PIN untuk mengamankan perubahan bisnis.</p>
            <p class="card-code">Kode kartu <strong>{{ $qr->public_id }}</strong></p>
            <form method="post" action="{{ route('cards.activate', $qr->public_id) }}" id="activation-form"
                class="activation-form">
                @csrf
                <div class="activation-section">
                    <div class="activation-section-head">
                        <span class="activation-step">01</span>
                        <div>
                            <label for="place-search">Pilih bisnis</label>
                            <p class="section-note">Cari bisnis Anda di Google Maps.</p>
                        </div>
                    </div>
                    <div class="search"><i data-lucide="search"></i><input id="place-search"
                            data-search-url="{{ route('cards.places', $qr->public_id) }}" autocomplete="off"
                            placeholder="Cari bisnis di Google Maps" value="{{ old('selected_name') }}"></div>
                    <div id="places"></div><input type="hidden" name="place_id" id="place-id"
                        value="{{ old('place_id') }}"><input type="hidden" name="selected_name" id="selected-name"
                        value="{{ old('selected_name') }}">
                    <div id="selected-place" class="selected-place {{ old('place_id') ? '' : 'hidden' }}"><i
                            data-lucide="map-pin"></i>
                        <div><strong>{{ old('selected_name') }}</strong><small></small></div>
                    </div>
                    @error('place_id')
                        <p class="error">{{ $message }}</p>
                    @enderror
                    <details class="manual-review"
                        @if ($errors->has('review_url') || $errors->has('place_name') || old('review_url') || old('place_name') || old('place_address')) open @endif>
                        <summary>Pencarian Maps tidak bisa digunakan? Input manual di sini</summary>
                        <p class="manual-hint"></p>
                        <div class="manual-grid">
                            <label>Nama bisnis manual<input name="place_name" value="{{ old('place_name') }}"
                                    maxlength="255"></label>
                            <label>Alamat bisnis manual<input name="place_address"
                                    value="{{ old('place_address') }}" maxlength="1000"></label>
                            <label class="manual-url">Link Minta ulasan Google<input type="url" name="review_url"
                                    value="{{ old('review_url') }}"
                                    placeholder="https://search.google.com/local/writereview?..." maxlength="2000"></label>
                        </div>
                    </details>
                    @error('review_url')
                        <p class="error">{{ $message }}</p>
                    @enderror
                    @error('place_name')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="activation-section pin-section">
                    <div class="activation-section-head">
                        <span class="activation-step">02</span>
                        <div>
                            <label for="activation-pin">Buat PIN</label>
                            <p class="section-note">Gunakan 6 angka untuk mengubah bisnis nanti.</p>
                        </div>
                    </div>
                    <div class="pin-input" data-pin-target="activation-pin"><input aria-label="Digit 1"
                            inputmode="numeric" maxlength="1" autocomplete="one-time-code"><input
                            aria-label="Digit 2" inputmode="numeric" maxlength="1"><input aria-label="Digit 3"
                            inputmode="numeric" maxlength="1"><input aria-label="Digit 4" inputmode="numeric"
                            maxlength="1"><input aria-label="Digit 5" inputmode="numeric" maxlength="1"><input
                            aria-label="Digit 6" inputmode="numeric" maxlength="1"></div><input id="activation-pin"
                        name="pin" type="hidden" required>
                    @error('pin')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </div>
                <button class="primary activation-submit" type="submit"><i data-lucide="check"></i>Aktifkan
                    Kartu</button>
            </form>
        </section>
    </main>
</body>

</html>
