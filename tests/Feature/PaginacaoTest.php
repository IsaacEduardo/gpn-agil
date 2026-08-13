<?php

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

/**
 * O portal é servido só por Bootstrap 5 — resources/sass/app.scss não importa
 * Tailwind e o plugin não está no vite.config.js. Como o Laravel gera paginação
 * com marcação Tailwind por omissão, sem Paginator::useBootstrapFive() os
 * controlos saem com classes sem CSS: existem no DOM mas não se veem, e as
 * listagens parecem não ter paginação.
 */
class PaginacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginacao_usa_marcacao_bootstrap_e_nao_tailwind()
    {
        $paginator = new LengthAwarePaginator([1, 2, 3], 45, 15, 1, ['path' => '/lotes']);

        $html = $paginator->links()->toHtml();

        // Classes de componente do Bootstrap 5
        $this->assertStringContainsString('pagination', $html);
        $this->assertStringContainsString('page-item', $html);
        $this->assertStringContainsString('page-link', $html);

        // Utilitários exclusivos do Tailwind que não existem neste projeto
        $this->assertStringNotContainsString('sm:hidden', $html);
        $this->assertStringNotContainsString('inline-flex', $html);
        $this->assertStringNotContainsString('dark:bg-gray-', $html);
    }

    public function test_listagem_com_varias_paginas_mostra_os_controlos()
    {
        $user = User::factory()->create();
        $user->givePermissionTo(
            \Spatie\Permission\Models\Permission::findOrCreate('lotes.view', 'web')
        );

        // A listagem de lotes pagina a 15; 20 registos garantem 2 páginas.
        foreach (range(1, 20) as $i) {
            Lote::create([
                'codigo_lote' => sprintf('LOTE-PAG-%03d', $i),
                'municipio' => 'Namibe',
                'area_m2' => 100 + $i,
                'zoneamento' => 'HABITACIONAL',
                'status' => 'DISPONIVEL',
            ]);
        }

        $response = $this->actingAs($user->fresh())->get(route('lotes.index'));

        $response->assertOk()
            ->assertSee('page-link', false)
            ->assertSee('page=2', false);
    }
}
