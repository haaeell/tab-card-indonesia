import './bootstrap';
import $ from 'jquery';
import Swal from 'sweetalert2';
import { createIcons, icons } from 'lucide';
import 'datatables.net-dt';

window.$ = $;
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const draw = () => createIcons({ icons });

document.addEventListener('DOMContentLoaded', () => {
    draw();
    if (window.flash) Swal.fire({ icon: 'success', title: window.flash, toast: true, position: 'top-end', showConfirmButton: false, timer: 2600 });
    document.querySelector('#menu')?.addEventListener('click', () => document.querySelector('aside').classList.toggle('open'));

    document.querySelectorAll('.delete').forEach((form) => form.addEventListener('submit', (event) => {
        event.preventDefault();
        Swal.fire({ title: 'Hapus QR?', text: 'Data scan juga akan dihapus.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Hapus', cancelButtonText: 'Batal' }).then((result) => result.isConfirmed && form.submit());
    }));

    document.querySelectorAll('.toggle').forEach((button) => button.addEventListener('click', async () => {
        const response = await fetch(button.dataset.url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
        const result = await response.json();
        Swal.fire({ icon: 'success', title: result.message, toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
        $('#qrs-table').DataTable().ajax.reload(null, false);
    }));

    document.querySelectorAll('.copy').forEach((button) => button.addEventListener('click', () => navigator.clipboard.writeText(button.dataset.copy).then(() => Swal.fire({ icon: 'success', title: 'Link disalin', toast: true, position: 'top-end', showConfirmButton: false, timer: 1800 }))));

    if (document.querySelector('#qrs-table')) {
        $('#qrs-table').DataTable({ processing: true, serverSide: true, ajax: '/qrs', columns: [{ data: 'name' }, { data: 'place_name' }, { data: 'total_scans' }, { data: 'is_active' }, { data: 'action', orderable: false, searchable: false }], language: { search: 'Cari:', lengthMenu: 'Tampil _MENU_', info: '_START_–_END_ dari _TOTAL_', zeroRecords: 'Tidak ada QR' } });
    }

    const input = document.querySelector('#place-search');
    let timer;
    input?.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            if (input.value.length < 2) return;
            const response = await fetch(`/places/autocomplete?query=${encodeURIComponent(input.value)}`);
            const predictions = await response.json();
            const box = document.querySelector('#places');
            box.innerHTML = predictions.map((place) => `<button type="button" class="place-option" data-id="${place.place_id}"><strong>${place.structured_formatting.main_text}</strong><small>${place.description}</small></button>`).join('');
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
