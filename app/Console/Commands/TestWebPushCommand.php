<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use Illuminate\Console\Command;
use Minishlink\WebPush\Subscription as WebPushSubscription;
use Minishlink\WebPush\WebPush;

class TestWebPushCommand extends Command
{
    protected $signature = 'push:test {--user=} {--title=Notificação de teste} {--body=Este é um push de teste.} {--url=}';

    protected $description = 'Envia uma notificação Web Push de teste para assinaturas salvas (todas ou de um usuário)';

    public function handle()
    {
        $publicKey = env('VAPID_PUBLIC_KEY');
        $privateKey = env('VAPID_PRIVATE_KEY');
        $subject = env('VAPID_SUBJECT', 'mailto:admin@example.com');

        if (! $publicKey || ! $privateKey) {
            $this->error('VAPID_PUBLIC_KEY/VAPID_PRIVATE_KEY não configurados no .env');

            return 1;
        }

        $userId = $this->option('user');
        $query = PushSubscription::query();
        if ($userId) {
            $query->where('user_id', $userId);
        }
        $subs = $query->get();

        if ($subs->isEmpty()) {
            $this->warn('Nenhuma assinatura encontrada. Acesse o app, aceite permissões e recarregue.');

            return 0;
        }

        $payload = [
            'title' => $this->option('title') ?? 'Notificação de teste',
            'body' => $this->option('body') ?? 'Este é um push de teste.',
            'url' => $this->option('url') ?? url('/'),
        ];

        $auth = [
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];

        $webPush = new WebPush($auth, [
            'TTL' => 300,
        ]);

        $this->info('Enviando push para '.$subs->count().' assinatura(s)...');

        foreach ($subs as $sub) {
            $subscription = WebPushSubscription::create([
                'endpoint' => $sub->endpoint,
                'publicKey' => $sub->p256dh,
                'authToken' => $sub->auth,
                'contentEncoding' => 'aes128gcm',
            ]);

            $report = $webPush->sendOneNotification($subscription, json_encode($payload));

            if ($report->isSuccess()) {
                $this->line("✔ Enviado para {$sub->endpoint}");
            } else {
                $this->error("✖ Falha para {$sub->endpoint}: ".$report->getReason());
                // Remove assinatura inválida (410 Gone, 404 Not Found, etc.)
                $statusCode = $report->getResponse()?->getStatusCode();
                if (in_array($statusCode, [404, 410])) {
                    $sub->delete();
                    $this->warn('Assinatura removida (endpoint inválido).');
                }
            }
        }

        $webPush->flush();

        $this->info('Concluído.');

        return 0;
    }
}
