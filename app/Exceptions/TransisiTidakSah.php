<?php

namespace App\Exceptions;

use App\Enums\StatusTagihan;
use DomainException;

/**
 * Perpindahan status yang tidak ada di tabel alur, atau dilakukan orang yang
 * tidak berwenang.
 *
 * Sengaja pengecualian domain, bukan diam-diam diabaikan: tagihan yang gagal
 * berpindah tanpa suara akan tampak macet tanpa alasan, dan orang akan
 * mengulanginya berkali-kali.
 */
class TransisiTidakSah extends DomainException
{
    public static function tidakAdaJalur(StatusTagihan $dari, StatusTagihan $ke): self
    {
        $sah = collect($dari->berikutnya())->map(fn (StatusTagihan $s) => $s->value)->join(', ');

        return new self(
            "Tidak ada jalur dari `{$dari->value}` ke `{$ke->value}`. ".
            ($sah === ''
                ? "Status `{$dari->value}` adalah status akhir."
                : "Dari `{$dari->value}` hanya bisa ke: {$sah}.")
        );
    }

    public static function tidakBerwenang(StatusTagihan $ke): self
    {
        return new self("Anda tidak berwenang memindahkan tagihan ke `{$ke->value}`.");
    }

    public static function catatanWajib(): self
    {
        return new self(
            'Mengembalikan tagihan wajib disertai catatan. '.
            'Tanpa alasan, penanggung jawab hanya bisa menebak apa yang harus diperbaiki.'
        );
    }

    public static function belumPunyaPenanggungJawab(): self
    {
        return new self('Tagihan belum punya penanggung jawab, jadi belum bisa dikerjakan.');
    }

    public static function belumLayakDiajukan(array $alasan): self
    {
        return new self('Tagihan belum bisa diajukan: '.implode(' ', $alasan));
    }

    /**
     * Alasan disebut satu per satu, bukan digabung menjadi "buktinya
     * bermasalah": keterbacaan dan keabsahan adalah dua sumbu terpisah, dan
     * orang yang hanya diberi tahu salah satunya akan memperbaiki yang salah.
     */
    public static function buktiBelumLayak(array $alasan): self
    {
        return new self('Tagihan belum bisa disetujui: '.implode(' ', $alasan));
    }
}
