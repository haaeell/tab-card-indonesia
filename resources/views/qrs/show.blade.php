<x-layouts.app :title="$qr->name" heading="Detail Kartu">
    @php($newPin = session('activation_pin'))
    <div class="detail-grid qr-detail">
        <div class="card qr-card">
            <div class="qr-image"><img src="{{ route('qrs.download', [$qr, 'png']) }}" alt="QR kartu {{ $qr->public_id }}"></div>
            <strong>{{ $qr->name }}</strong>
            <p>QR dan NFC memakai URL kartu yang sama.</p>
            <div class="download">
                <a class="secondary" href="{{ route('qrs.download', [$qr, 'png']) }}"><i data-lucide="download"></i>PNG</a>
                <a class="secondary" href="{{ route('qrs.download', [$qr, 'svg']) }}"><i data-lucide="download"></i>SVG</a>
            </div>
        </div>
        <div class="card detail-card">
            <div class="card-head detail-head">
                <div>
                    <span class="badge {{ $qr->is_active && $qr->isActivated() ? 'badge-green' : 'badge-gray' }}">{{ ! $qr->is_active ? 'Nonaktif' : ($qr->isActivated() ? 'Aktif' : 'Belum aktif') }}</span>
                    <h2>{{ $qr->name }}</h2>
                    <p>{{ $qr->place_name ?: 'Menunggu aktivasi pemilik bisnis' }}</p>
                </div>
                <a class="secondary" href="{{ route('qrs.edit', $qr) }}"><i data-lucide="pencil"></i>{{ $qr->isActivated() ? 'Edit' : 'Atur bisnis' }}</a>
            </div>
            <dl>
                <dt>Kode kartu</dt>
                <dd>{{ $qr->public_id }}</dd>
                @if($qr->isActivated())
                    <dt>Alamat</dt>
                    <dd>{{ $qr->place_address }}</dd>
                @endif
                <dt>URL QR & NFC</dt>
                <dd class="short-url"><code>{{ route('redirect', $qr->public_id) }}</code><button class="copy" data-copy="{{ route('redirect', $qr->public_id) }}" aria-label="Salin URL kartu"><i data-lucide="copy"></i></button></dd>
                <dt>Total scan</dt>
                <dd>{{ $qr->total_scans }} kali</dd>
            </dl>
            <div class="pin-status {{ $qr->activation_pin_hash ? 'pin-set' : 'pin-unset' }}"><i data-lucide="{{ $qr->activation_pin_hash ? 'shield-check' : 'shield-alert' }}"></i><div><strong>PIN owner: {{ $qr->activation_pin_hash ? 'Sudah dibuat' : 'Belum dibuat / sudah direset' }}</strong><p>PIN dikelola owner dan tidak dapat ditampilkan.</p></div></div>
            <form method="post" action="{{ route('qrs.pin', $qr) }}" class="reset-pin">
                @csrf
                <button class="secondary" type="submit"><i data-lucide="key-round"></i>Reset PIN Owner</button>
            </form>
            <div class="form-actions detail-actions">
                @if($qr->isActivated())
                    <a class="secondary" target="_blank" rel="noopener noreferrer" href="{{ $qr->review_url }}"><i data-lucide="external-link"></i>Test Google Review</a>
                @else
                    <a class="secondary" target="_blank" rel="noopener noreferrer" href="{{ route('redirect', $qr->public_id) }}"><i data-lucide="external-link"></i>Lihat Halaman Aktivasi</a>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
