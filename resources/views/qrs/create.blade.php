<x-layouts.app title="Siapkan Kartu" heading="Siapkan Kartu">
    <div class="intro">
        <div>
            <h2>Kartu Review Baru</h2>
            <p>Siapkan satu QR dan URL NFC untuk satu kartu fisik.</p>
        </div>
    </div>
    <div class="form-wrap">
        <form method="post" action="{{ route('qrs.store') }}" class="card form-card">
            @csrf
            <label>
                Label kartu
                <input name="name" value="{{ old('name') }}" maxlength="100" placeholder="Contoh: Kartu 001">
                @error('name')<small class="error">{{ $message }}</small>@enderror
            </label>
            <div class="form-actions">
                <a class="secondary" href="{{ route('qrs.index') }}">Batal</a>
                <button class="primary" type="submit"><i data-lucide="plus"></i>Buat Kartu</button>
            </div>
        </form>
    </div>
</x-layouts.app>
