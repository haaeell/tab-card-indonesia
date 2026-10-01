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

    document.querySelectorAll('.delete').forEach((form) => form.addEventListener('submit', (event) => {
        event.preventDefault();
        Swal.fire({ title: 'Hapus QR?', text: 'Data scan juga akan dihapus.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Hapus', cancelButtonText: 'Batal' }).then((result) => result.isConfirmed && form.submit());
    }));

    document.querySelectorAll('.toggle').forEach((button) => button.addEventListener('click', async () => {
        const response = await fetch(button.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
        const result = await response.json();
        Swal.fire({ icon: 'success', title: result.message, toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
        qrTable?.ajax.reload(null, false);
    }));

    document.querySelectorAll('.copy').forEach((button) => button.addEventListener('click', () => navigator.clipboard.writeText(button.dataset.copy).then(() => Swal.fire({ icon: 'success', title: 'Link disalin', toast: true, position: 'top-end', showConfirmButton: false, timer: 1800 }))));

    if (document.querySelector('#qrs-table')) {
        qrTable = new DataTable('#qrs-table', { processing: true, serverSide: true, ajax: '/qrs', pageLength: 10, lengthMenu: [10, 25, 50], layout: { topStart: 'pageLength', topEnd: 'search', bottomStart: 'info', bottomEnd: 'paging' }, columns: [{ data: 'DT_RowIndex', orderable: false, searchable: false }, { data: 'name' }, { data: 'place_name' }, { data: 'total_scans' }, { data: 'is_active' }, { data: 'action', orderable: false, searchable: false }], language: { search: '', searchPlaceholder: 'Cari QR atau bisnis...', lengthMenu: '_MENU_ per halaman', info: 'Menampilkan _START_–_END_ dari _TOTAL_ QR', zeroRecords: 'QR belum ditemukan', processing: 'Memuat data...' }, drawCallback: draw });
    }

    const input = document.querySelector('#place-search');
    let timer;
    input?.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            if (input.value.length < 2) return;
            const response = await fetch(`/places/autocomplete?query=${encodeURIComponent(input.value)}`);
            const predictions = await response.json();
            if (!response.ok) return Swal.fire('Pencarian gagal', predictions.message, 'error');
            const box = document.querySelector('#places');
            box.innerHTML = predictions.map((place) => `<button type="button" class="place-option" data-id="${place.place_id}"><strong>${place.name}</strong><small>${place.address}</small></button>`).join('');
            box.querySelectorAll('button').forEach((button) => button.addEventListener('click', async () => {
                const detailResponse = await fetch(`/places/detail?place_id=${encodeURIComponent(button.dataset.id)}`);
                const place = await detailResponse.json();
                if (!detailResponse.ok) return Swal.fire('Gagal', place.message, 'error');
                document.querySelector('#place-id').value = place.place_id;
                document.querySelector('#place-name').value = place.name;
                document.querySelector('#place-address').value = place.address;
                document.querySelector('#maps-url').value = place.maps_url;
                document.querySelector('#review-url').value = place.review_url;
                document.querySelector('#preview-name').textContent = place.name;
                document.querySelector('#preview-address').textContent = place.address;
                document.querySelector('#place-preview').classList.remove('hidden');
                box.innerHTML = '';
                draw();
            }));
        }, 350);
    });
});
