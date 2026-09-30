<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * "Esqueci a password" dizia se o email existia e não tinha limite por IP
 * (auditoria de 2026-09-30, M4).
 */
class RecuperacaoPasswordSegurancaTest extends TestCase
{
    use RefreshDatabase;

    public function test_resposta_igual_exista_ou_nao_o_email(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'existe@example.com']);

        $existe = $this->from(route('password.request'))->post(route('password.email'), ['email' => 'existe@example.com']);
        $naoExiste = $this->from(route('password.request'))->post(route('password.email'), ['email' => 'fantasma@example.com']);
        // Segundo pedido para a conta real cai no limite do broker: também não pode denunciar a conta.
        $repetido = $this->from(route('password.request'))->post(route('password.email'), ['email' => 'existe@example.com']);

        foreach ([$existe, $naoExiste, $repetido] as $resposta) {
            $resposta->assertRedirect(route('password.request'))
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', \App\Http\Controllers\Auth\ForgotPasswordController::MENSAGEM_GENERICA);
        }
    }

    public function test_pedidos_de_recuperacao_sao_limitados_por_ip(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => "x{$i}@example.com"])->assertStatus(302);
        }

        $this->post(route('password.email'), ['email' => 'y@example.com'])->assertStatus(429);
    }
}
