<x-layouts.app :title="$qr->exists ? 'Edit QR' : 'Tambah QR'" :heading="$qr->exists ? 'Edit QR Review' : 'Tambah QR Review'">
    <div class="form-wrap">
        <form method="post" action="{{ $qr->exists ? route('qrs.update', $qr) : route('qrs.store') }}" id="qr-form"
            class="card form-card">@csrf @if ($qr->exists)
                @method('put')
            @endif
            <div class="card-head">
                <div>
                    <h2>{{ $qr->exists ? 'Ubah tujuan QR' : 'Buat QR baru' }}</h2>
                    <p>QR fisik tidak berubah saat lokasi diperbarui.</p>
                </div>
            </div><label>Nama QR<input name="name" value="{{ old('name', $qr->name) }}"
                    placeholder="Contoh: Nama Bisnis" required></label><label>Cari bisnis Google Maps<div class="search">
                    <i data-lucide="search"></i><input id="place-search" autocomplete="off"
                        placeholder="Ketik nama bisnis atau lokasi"></div>
                <div id="places"></div>
            </label>
            <div id="place-preview" class="place-preview {{ $qr->exists ? '' : 'hidden' }}"><i
                    data-lucide="map-pin"></i>
                <div><strong id="preview-name">{{ $qr->place_name }}</strong>
                    <p id="preview-address">{{ $qr->place_address }}</p><small><i data-lucide="circle-check"></i>Google
                        Maps ditemukan · Google Review tersedia</small>
                </div>
            </div>
            <p class="section-note"></p><label>Nama bisnis manual<input name="place_name" id="place-name"
                    value="{{ old('place_name', $qr->place_name) }}" maxlength="255"></label><label>Alamat bisnis
                manual<input name="place_address" id="place-address"
                    value="{{ old('place_address', $qr->place_address) }}" maxlength="1000"></label><label>Link Minta
                ulasan Google<input type="url" name="review_url" id="review-url"
                    value="{{ old('review_url', $qr->review_url) }}"
                    placeholder="https://search.google.com/local/writereview?..." maxlength="2000" required></label>
            @error('review_url')
                <p class="error">{{ $message }}</p>
            @enderror
            <input type="hidden" name="place_id" id="place-id" value="{{ old('place_id', $qr->place_id) }}"><input
                type="hidden" name="maps_url" id="maps-url" value="{{ old('maps_url', $qr->maps_url) }}"><label
                class="switch"><input type="checkbox" name="is_active" value="1"
                    {{ old('is_active', $qr->exists ? $qr->is_active : true) ? 'checked' : '' }}><span></span>QR
                aktif</label>
            <div class="form-actions"><a href="{{ $qr->exists ? route('qrs.show', $qr) : route('qrs.index') }}"
                    class="secondary">Batal</a><button class="primary"><i
                        data-lucide="{{ $qr->exists ? 'save' : 'plus' }}"></i>{{ $qr->exists ? 'Simpan Perubahan' : 'Buat Kartu' }}</button>
            </div>
        </form>
    </div>
</x-layouts.app>
