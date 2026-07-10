<?php

namespace Tests\Feature;

use App\Mail\NotificationDigestMail;
use App\Models\NotificationUserSetting;
use App\Models\User;
use App\Notifications\SimpleBroadcastNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationDigestTest extends TestCase
{
    use RefreshDatabase;

    private function unread(User $user, int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $user->notify(new SimpleBroadcastNotification("N{$i}", "M{$i}", url('/'), 'normal', 'documento_recebido'));
        }
    }

    public function test_envia_resumo_apenas_a_quem_optou_e_tem_nao_lidas(): void
    {
        Mail::fake();

        // Opta pelo digest e tem 2 não lidas -> recebe.
        $comDigest = User::factory()->create(['email' => 'com@example.com']);
        NotificationUserSetting::create(['user_id' => $comDigest->id, 'digest_enabled' => true]);
        $this->unread($comDigest, 2);

        // Opta pelo digest mas não tem não lidas -> não recebe.
        $semNaoLidas = User::factory()->create(['email' => 'vazio@example.com']);
        NotificationUserSetting::create(['user_id' => $semNaoLidas->id, 'digest_enabled' => true]);

        // Não optou pelo digest (default) mas tem não lidas -> não recebe.
        $semDigest = User::factory()->create(['email' => 'sem@example.com']);
        $this->unread($semDigest, 3);

        Artisan::call('notifications:digest');

        Mail::assertQueued(NotificationDigestMail::class, function ($mail) use ($comDigest) {
            return $mail->hasTo($comDigest->email) && $mail->notifications->count() === 2;
        });
        Mail::assertNotQueued(NotificationDigestMail::class, fn ($mail) => $mail->hasTo($semNaoLidas->email));
        Mail::assertNotQueued(NotificationDigestMail::class, fn ($mail) => $mail->hasTo($semDigest->email));
    }
}
