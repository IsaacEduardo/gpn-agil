<?php

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * A geometria do lote ia crua para dentro de um <script> e só era validada
 * como string: quem cria lotes injectava JavaScript a correr no browser de
 * quem abrisse o lote (auditoria de 2026-09-30, A5).
 */
class LoteGeojsonSegurancaTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = '1;window.__xss=1;</script><script>alert(1)</script>';

    public function test_rejeita_geometria_que_nao_e_geojson(): void
    {
        $user = $this->utilizador(['lotes.create', 'lotes.view']);

        foreach ([self::PAYLOAD, '{"type":"Script","coordinates":[]}', '[1,2]'] as $invalida) {
            $this->actingAs($user)
                ->post(route('lotes.store'), $this->dadosLote('LOTE-XSS-'.md5($invalida), $invalida))
                ->assertSessionHasErrors('geojson_geometria');
        }

        $this->assertDatabaseCount('lotes', 0);
    }

    public function test_aceita_geometria_geojson_valida(): void
    {
        $user = $this->utilizador(['lotes.create', 'lotes.view']);
        $poligono = '{"type":"Polygon","coordinates":[[[12.15,-15.19],[12.16,-15.19],[12.16,-15.20],[12.15,-15.19]]]}';

        $this->actingAs($user)
            ->post(route('lotes.store'), $this->dadosLote('LOTE-OK-1', $poligono))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('lotes', ['codigo_lote' => 'LOTE-OK-1']);
    }

    public function test_geometria_gravada_nao_e_executada_na_pagina(): void
    {
        $user = $this->utilizador(['lotes.view']);
        // Registo antigo, gravado antes da validação existir.
        $lote = Lote::create(array_merge(
            $this->dadosLote('LOTE-ANTIGO', self::PAYLOAD),
            ['latitude_centro' => -15.19, 'longitude_centro' => 12.15]
        ));

        $html = $this->actingAs($user)->get(route('lotes.show', $lote))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('window.__xss=1', $html);
    }

    private function utilizador(array $permissoes): User
    {
        $user = User::factory()->create();
        foreach ($permissoes as $nome) {
            $user->givePermissionTo(Permission::findOrCreate($nome, 'web'));
        }

        return $user->fresh();
    }

    private function dadosLote(string $codigo, string $geojson): array
    {
        return [
            'codigo_lote' => $codigo,
            'municipio' => 'Namibe',
            'area_m2' => 500,
            'zoneamento' => 'HABITACIONAL',
            'status' => 'DISPONIVEL',
            'geojson_geometria' => $geojson,
        ];
    }
}
