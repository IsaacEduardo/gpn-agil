<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartAutoFillTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_company_details_for_smart_auto_fill()
    {
        $user = User::factory()->create();

        $empresa = Empresa::create([
            'nome' => 'Empresa Teste Lda',
            'contacto' => 'João Silva',
            'nif' => '5401234567',
            'telefone_principal' => '923000111',
            'email_institucional' => 'geral@empresateste.co.ao',
            'endereco' => 'Rua Comercial, Namibe',
        ]);

        $response = $this->actingAs($user)->getJson(route('empresas.json', $empresa));

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $empresa->id,
            'nome' => 'Empresa Teste Lda',
            'nif' => '5401234567',
            'contacto' => 'João Silva',
            'telefone_principal' => '923000111',
            'endereco' => 'Rua Comercial, Namibe',
        ]);
    }
}
