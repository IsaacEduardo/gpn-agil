<?php

namespace App\Http\Controllers;

use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Recebe eventos de bounce/reclamação do provedor de e-mail e desativa o canal
 * de e-mail do utilizador afetado, registando o evento na auditoria de entrega.
 *
 * Contrato mínimo e agnóstico de provedor: { "email": "...", "type": "bounce" }.
 * Para SES/Mailgun/Postmark, mapeie o payload do provedor para este formato
 * (e valide a assinatura do provedor antes deste passo, se aplicável).
 */
class MailBounceWebhookController extends Controller
{
    public function __construct(private NotificationPreferenceService $preferences)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        $expected = config('services.mail_webhook.token');

        // Endpoint desativado se não houver token configurado.
        if (empty($expected)) {
            return response()->json(['message' => 'Webhook desativado.'], 403);
        }

        $provided = $request->header('X-Webhook-Token') ?: $request->query('token');
        if (! is_string($provided) || ! hash_equals((string) $expected, $provided)) {
            return response()->json(['message' => 'Não autorizado.'], 401);
        }

        $email = $request->input('email');
        $type = $request->input('type', 'bounce');
        if (! is_string($email) || $email === '') {
            return response()->json(['message' => 'Campo "email" em falta.'], 422);
        }

        $users = User::where('email', $email)->get();
        foreach ($users as $user) {
            $this->preferences->disableEmailChannel($user);

            NotificationDelivery::create([
                'notifiable_type' => $user->getMorphClass(),
                'notifiable_id' => $user->getKey(),
                'notification_type' => 'mail-bounce-webhook',
                'event_type' => is_string($type) ? $type : 'bounce',
                'channel' => 'mail',
                'status' => 'bounced',
                'response' => mb_substr(json_encode($request->all()) ?: '', 0, 2000),
            ]);
        }

        Log::info('Webhook de bounce processado', ['email' => $email, 'type' => $type, 'users' => $users->count()]);

        return response()->json(['ok' => true, 'affected' => $users->count()]);
    }
}
