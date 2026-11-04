{{-- Satu baris jumlah/rata-rata/subtotal di bawah lembar berbaris tetap. --}}
<tr class="sigap-baris-ringkasan">
    <td class="sigap-sel-label">{{ $sub['label'] }}</td>
    @foreach ($kolom as $k)
        <td class="sigap-sel-hitung">{{ array_key_exists($k['kunci'], $sub['nilai']) && $sub['nilai'][$k['kunci']] !== null ? \App\Livewire\LembarDkps::angka($sub['nilai'][$k['kunci']]) : '' }}</td>
    @endforeach
    <td colspan="4" class="sigap-kolom-tambahan sigap-kolom-tambahan-awal"></td>
</tr>
