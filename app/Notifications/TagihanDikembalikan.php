<?php

namespace App\Notifications;

use App\Models\Tagihan;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TagihanDikembalikan extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Tagihan $tagihan,
        public readonly User $oleh,
        public readonly ?string $catatan,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'judul' => 'Tagihan dikembalikan untuk diperbaiki',
            'isi' => "{$this->oleh->nama_lengkap} mengembalikan \"{$this->tagihan->judul}\".",
            // Catatan ikut dibawa, bukan sekadar ditautkan: alasan pengembalian
            // adalah hal pertama yang ingin dibaca orang, dan memaksanya membuka
            // halaman lebih dulu membuat sebagian tidak membacanya sama sekali.
            'catatan' => $this->catatan,
            'tagihan_id' => $this->tagihan->getKey(),
        ];
    }
}
