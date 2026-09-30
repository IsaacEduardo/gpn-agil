<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * O login travava só email+IP: uma password contra muitas contas (spraying)
 * ou muitos IPs contra uma conta passavam sem limite (auditoria de
 * 2026-09-30, M1). Contam-se só falhas, porque os serviços saem para a
 * internet por um único IP e os logins válidos da manhã não podem bloquear.
 */
class LoginForcaBrutaTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_spraying_do_mesmo_ip_e_bloqueado(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->tentar("conta{$i}@example.com", 'Errada#123', '10.0.0.1')->assertStatus(302);
        }

        // Bloqueado: nem com a password certa entra enquanto durar a janela.
        $vitima = User::factory()->create(['password' => Hash::make('Certa#2026')]);
        $this->tentar($vitima->email, 'Certa#2026', '10.0.0.1')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_ataque_distribuido_a_uma_conta_e_bloqueado(): void
    {
        User::factory()->create(['email' => 'alvo@example.com', 'password' => Hash::make('Certa#2026')]);

        for ($i = 1; $i <= 10; $i++) {
            $this->tentar('alvo@example.com', 'Errada#123', "10.0.1.{$i}")->assertStatus(302);
        }

        $this->tentar('alvo@example.com', 'Certa#2026', '10.0.1.200')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logins_validos_pelo_mesmo_ip_nao_bloqueiam(): void
    {
        for ($i = 0; $i < 40; $i++) {
            $user = User::factory()->create(['password' => Hash::make('Certa#2026')]);

            $this->tentar($user->email, 'Certa#2026', '10.0.2.1')->assertRedirect('/home');
            $this->post(route('logout'));
        }
    }

    private function tentar(string $email, string $password, string $ip)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('login'), ['email' => $email, 'password' => $password]);
    }
}
