<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Surel atur ulang kata sandi mandiri. Hanya memuat tautan bertanda tangan
 * berumur 60 menit; tidak pernah memuat kata sandi.
 */
class ResetKataSandi extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tautan = URL::temporarySignedRoute('password.reset', now()->addMinutes(60), [
            'token' => $this->token,
            'email' => $notifiable->email,
        ]);

        return (new MailMessage)
            ->subject('Atur ulang kata sandi SPMB Al-Insan')
            ->greeting('Assalamu\'alaikum, '.$notifiable->nama)
            ->line('Kami menerima permintaan untuk mengatur ulang kata sandi akun SPMB Anda.')
            ->action('Atur kata sandi sekarang', $tautan)
            ->line('Tautan ini berlaku 60 menit dan hanya dapat dipakai sekali.')
            ->line('Bila Anda tidak meminta atur ulang kata sandi, abaikan surel ini.');
    }
}
