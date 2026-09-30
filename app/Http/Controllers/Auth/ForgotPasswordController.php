<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    /**
     * A mesma resposta para envio, email desconhecido e pedido repetido: as
     * mensagens do Laravel distinguiam-nos e permitiam enumerar contas.
     */
    public const MENSAGEM_GENERICA = 'Se o email indicado estiver registado, vai receber um link para redefinir a password.';

    public function __construct()
    {
        $this->middleware('throttle:5,1')->only('sendResetLinkEmail');
    }

    protected function sendResetLinkResponse(Request $request, $response)
    {
        return $this->respostaGenerica($request);
    }

    protected function sendResetLinkFailedResponse(Request $request, $response)
    {
        return $this->respostaGenerica($request);
    }

    private function respostaGenerica(Request $request)
    {
        return $request->wantsJson()
            ? new JsonResponse(['message' => self::MENSAGEM_GENERICA], 200)
            : back()->with('status', self::MENSAGEM_GENERICA);
    }
}
