<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Kartu Aktif | Tab Card Indonesia</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="activation-page">
    <main class="activation-wrap">
        <div class="activation-brand"><span class="brand-mark"><i data-lucide="scan-line"></i></span><span>Tab Card<small>INDONESIA</small></span></div>
        <section class="activation-content activation-success">
            <span class="success-mark"><i data-lucide="circle-check"></i></span>
            <p class="eyebrow">KARTU REVIEW</p>
            <h1>Kartu Sudah Aktif</h1>
            <p class="activation-lead">{{ $card->place_name }}</p>
            <p class="activation-address">{{ $card->place_address }}</p>
            <a class="primary activation-submit" href="{{ route('redirect', $card->public_id) }}"><i data-lucide="external-link"></i>Buka Halaman Review</a>
            <a class="secondary activation-submit" href="{{ route('cards.manage', $card->public_id) }}"><i data-lucide="settings-2"></i>Kelola Bisnis</a>
        </section>
    </main>
</body>
</html>
