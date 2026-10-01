import './bootstrap';
import $ from 'jquery';
import Swal from 'sweetalert2';
import { createIcons, icons } from 'lucide';
import DataTable from 'datatables.net-dt';
import 'datatables.net-dt/css/dataTables.dataTables.css';

window.$ = $;
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const draw = () => createIcons({ icons });
let qrTable;

function renderQrMobileCards() {
    const table = document.querySelector('#qrs-table');
    const tableRow = table?.closest('.dt-layout-table');
    if (!tableRow) return;

    let list = document.querySelector('#qrs-mobile-list');
    if (!list) {
        list = document.createElement('div');
        list.id = 'qrs-mobile-list';
        list.className = 'qr-mobile-list';
        tableRow.insertAdjacentElement('afterend', list);
    }

    const rows = [...table.tBodies[0].rows].filter((row) => row.cells.length === 6);
    if (!rows.length) {
        const empty = document.createElement('p');
        empty.className = 'qr-mobile-empty';
        empty.textContent = 'QR belum ditemukan';
        list.replaceChildren(empty);
        return;
    }

    list.replaceChildren(...rows.map((row) => {
        const card = document.createElement('article');
        card.className = 'qr-mobile-card';
        card.innerHTML = '<div class="qr-mobile-heading"><div><h3></h3><p></p></div><span class="qr-mobile-number"></span></div><p class="qr-mobile-address"></p><div class="qr-mobile-meta"><div><span>Total scan</span><strong></strong></div><div><span>Status</span></div></div><div class="qr-mobile-actions"><span>Aksi</span></div>';
        card.querySelector('h3').textContent = row.cells[1].textContent.trim();
        card.querySelector('.qr-mobile-heading p').textContent = row.cells[2].querySelector('strong')?.textContent ?? '';
        card.querySelector('.qr-mobile-number').textContent = `#${row.cells[0].textContent.trim()}`;
        card.querySelector('.qr-mobile-address').textContent = row.cells[2].querySelector('small')?.textContent ?? '';
        card.querySelector('.qr-mobile-meta strong').textContent = row.cells[3].textContent.trim();
        const status = row.cells[4].querySelector('.badge');
        if (status) card.querySelector('.qr-mobile-meta div:last-child').append(status.cloneNode(true));
        const actions = row.cells[5].querySelector('.actions');
        if (actions) card.querySelector('.qr-mobile-actions').append(actions.cloneNode(true));
        return card;
    }));
}

document.addEventListener('DOMContentLoaded', () => {
    draw();
    if (window.flash) Swal.fire({ icon: 'success', title: window.flash, toast: true, position: 'top-end', showConfirmButton: false, timer: 2600 });
    document.querySelector('#menu')?.addEventListener('click', () => document.querySelector('aside').classList.toggle('open'));

    document.querySelector('.password-toggle')?.addEventListener('click', (event) => {
        const password = document.querySelector('#password');
        const visible = password.type === 'text';
        password.type = visible ? 'password' : 'text';
        event.currentTarget.setAttribute('aria-label', visible ? 'Tampilkan password' : 'Sembunyikan password');
        event.currentTarget.innerHTML = `<i data-lucide="${visible ? 'eye' : 'eye-off'}"></i>`;
        draw();
    });

    document.querySelector('#login-form')?.addEventListener('submit', (event) => {
        const button = event.currentTarget.querySelector('.login-submit');
        if (!event.currentTarget.checkValidity()) return;
        button.disabled = true;
        button.classList.add('is-loading');
    });

    document.querySelectorAll('.pin-input').forEach((group) => {
        const fields = [...group.querySelectorAll('input')];
        const target = document.querySelector(`#${group.dataset.pinTarget}`);
        const sync = () => { target.value = fields.map((field) => field.value).join(''); };
        fields.forEach((field, index) => {
            field.addEventListener('input', () => {
                field.value = field.value.replace(/\D/g, '').slice(-1);
                sync();
                if (field.value && fields[index + 1]) fields[index + 1].focus();
            });
            field.addEventListener('keydown', (event) => {
                if (event.key === 'Backspace' && !field.value && fields[index - 1]) fields[index - 1].focus();
            });
            field.addEventListener('paste', (event) => {
                event.preventDefault();
                const digits = event.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
                digits.split('').forEach((digit, digitIndex) => { if (fields[digitIndex]) fields[digitIndex].value = digit; });
                sync();
                fields[Math.min(digits.length, 6) - 1]?.focus();
            });
        });
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('.delete');
        if (!form) return;
        event.preventDefault();
        Swal.fire({ title: 'Hapus QR?', text: 'Data scan juga akan dihapus.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Hapus', cancelButtonText: 'Batal' }).then((result) => result.isConfirmed && form.submit());
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('.delete-pending');
        if (!form) return;
        event.preventDefault();
        Swal.fire({ title: 'Hapus kartu belum aktif?', text: 'Kartu yang sudah owner aktivasi tidak akan dihapus.', icon: 'warning', input: 'text', inputPlaceholder: 'Ketik HAPUS', showCancelButton: true, confirmButtonText: 'Hapus kartu', cancelButtonText: 'Batal', preConfirm: (value) => value === 'HAPUS' || Swal.showValidationMessage('Ketik HAPUS untuk melanjutkan.') }).then((result) => result.isConfirmed && form.submit());
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('.reset-pin');
        if (!form) return;
        event.preventDefault();
        Swal.fire({ title: 'Reset PIN?', text: 'PIN lama tidak akan berlaku lagi.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Reset PIN', cancelButtonText: 'Batal' }).then((result) => result.isConfirmed && form.submit());
    });

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('.toggle');
        if (!button) return;
        const response = await fetch(button.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
        const result = await response.json();
        Swal.fire({ icon: 'success', title: result.message, toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
        qrTable?.ajax.reload(null, false);
    });

    document.querySelectorAll('.copy').forEach((button) => button.addEventListener('click', () => navigator.clipboard.writeText(button.dataset.copy).then(() => Swal.fire({ icon: 'success', title: 'Link disalin', toast: true, position: 'top-end', showConfirmButton: false, timer: 1800 }))));

    if (document.querySelector('#qrs-table')) {
        qrTable = new DataTable('#qrs-table', { processing: true, serverSide: true, ajax: '/qrs', pageLength: 10, lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Semua']], layout: { topStart: 'pageLength', topEnd: 'search', bottomStart: 'info', bottomEnd: 'paging' }, columns: [{ data: 'DT_RowIndex', orderable: false, searchable: false }, { data: 'name' }, { data: 'place_name' }, { data: 'total_scans' }, { data: 'is_active' }, { data: 'action', orderable: false, searchable: false }], language: { search: '', searchPlaceholder: 'Cari QR atau bisnis...', lengthMenu: 'Tampilkan _MENU_', info: 'Menampilkan _START_–_END_ dari _TOTAL_ QR', infoEmpty: 'Menampilkan 0 dari 0 QR', infoFiltered: '(disaring dari _MAX_ QR)', zeroRecords: 'QR belum ditemukan', processing: 'Memuat data...', paginate: { previous: 'Sebelumnya', next: 'Berikutnya' } }, drawCallback: () => { renderQrMobileCards(); draw(); } });
    }

    const input = document.querySelector('#place-search');
    const activationForm = document.querySelector('#activation-form');
    let timer;
    let placeRequest = 0;
    activationForm?.addEventListener('submit', (event) => {
        const pin = activationForm.querySelector('[name="pin"]');
        if (pin && pin.value.length !== 6) {
            event.preventDefault();
            Swal.fire('PIN belum lengkap', 'Masukkan 6 angka PIN.', 'warning');
        }
    });
    input?.addEventListener('input', () => {
        const box = document.querySelector('#places');
        const query = input.value.trim();
        const requestId = ++placeRequest;
        if (activationForm) {
            document.querySelector('#place-id').value = '';
            document.querySelector('#selected-name').value = '';
            document.querySelector('#selected-place').classList.add('hidden');
        }
        clearTimeout(timer);
        timer = setTimeout(async () => {
            if (query.length < 2) {
                box.innerHTML = '';
                return;
            }

            box.innerHTML = '<div class="places-loading"><span class="inline-loader"></span><span>Mencari bisnis...</span></div>';

            try {
                const response = await fetch(`${input.dataset.searchUrl || '/places/autocomplete'}?query=${encodeURIComponent(query)}`);
                const predictions = await response.json();
                if (requestId !== placeRequest) return;
                if (!response.ok) {
                    box.innerHTML = '';
                    return Swal.fire('Pencarian gagal', predictions.message, 'error');
                }

                if (!predictions.length) {
                    box.innerHTML = '<div class="places-empty">Bisnis tidak ditemukan.</div>';
                    return;
                }

                box.replaceChildren(...predictions.map((place) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'place-option';
                    const name = document.createElement('strong');
                    name.textContent = place.name;
                    const address = document.createElement('small');
                    address.textContent = place.address;
                    button.append(name, address);
                    button.addEventListener('click', async () => {
                        ++placeRequest;
                        if (activationForm) {
                            input.value = place.name;
                            document.querySelector('#place-id').value = place.place_id;
                            document.querySelector('#selected-name').value = place.name;
                            document.querySelector('#selected-place span').textContent = place.name;
                            document.querySelector('#selected-place').classList.remove('hidden');
                            box.innerHTML = '';
                            return;
                        }

                        box.innerHTML = '<div class="places-loading"><span class="inline-loader"></span><span>Mengambil detail...</span></div>';
                        try {
                            const detailResponse = await fetch(`/places/detail?place_id=${encodeURIComponent(place.place_id)}`);
                            const detail = await detailResponse.json();
                            if (!detailResponse.ok) {
                                box.innerHTML = '';
                                return Swal.fire('Gagal', detail.message, 'error');
                            }
                            document.querySelector('#place-id').value = detail.place_id;
                            document.querySelector('#place-name').value = detail.name;
                            document.querySelector('#place-address').value = detail.address;
                            document.querySelector('#maps-url').value = detail.maps_url;
                            document.querySelector('#review-url').value = detail.review_url;
                            document.querySelector('#preview-name').textContent = detail.name;
                            document.querySelector('#preview-address').textContent = detail.address;
                            document.querySelector('#place-preview').classList.remove('hidden');
                            box.innerHTML = '';
                            draw();
                        } catch {
                            box.innerHTML = '';
                            Swal.fire('Gagal', 'Detail bisnis tidak dapat dimuat.', 'error');
                        }
                    });
                    return button;
                }));
            } catch {
                if (requestId !== placeRequest) return;
                box.innerHTML = '';
                Swal.fire('Pencarian gagal', 'Coba beberapa saat lagi.', 'error');
            }
        }, 350);
    });

    activationForm?.addEventListener('submit', (event) => {
        if (!document.querySelector('#place-id').value) {
            event.preventDefault();
            Swal.fire('Pilih bisnis', 'Pilih bisnis dari hasil pencarian.', 'warning');
            return;
        }
        if (!activationForm.checkValidity()) return;
        const button = activationForm.querySelector('.activation-submit');
        button.disabled = true;
        button.classList.add('is-loading');
    });
});
