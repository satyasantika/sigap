<?php

namespace App\Notifications;

use App\Models\Tagihan;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Hanya lewat basis data, tidak lewat surel.
 *
 * Aplikasi ini sengaja tidak bergantung pada SMTP yang harus selalu hidup —
 * lihat vibecoding/docs/08-auth-dan-izin.md. Orang melihat pemberitahuannya
 * saat membuka panel.
 */
class TagihanDitugaskan extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Tagihan $tagihan,
        public readonly User $oleh,
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
            'judul' => 'Tagihan baru untuk Anda',
            'isi' => "{$this->oleh->nama_lengkap} menugaskan \"{$this->tagihan->judul}\" kepada Anda.",
            'tagihan_id' => $this->tagihan->getKey(),
            'tenggat' => $this->tagihan->tenggat?->toDateString(),
        ];
    }
}
