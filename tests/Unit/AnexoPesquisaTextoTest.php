<?php

namespace Tests\Unit;

use App\Models\Anexo;
use Tests\TestCase;

/**
 * A suite corre em sqlite, onde a pesquisa de OCR mantém o LIKE. Estes testes
 * inspecionam o SQL gerado para a ligação MySQL, para o caminho FULLTEXT não
 * ficar por verificar até chegar a produção.
 */
class AnexoPesquisaTextoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.mysql_grammar', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'database' => 'nao_usada',
            'username' => 'nao_usado',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ]);
    }

    private function sqlMysql(string $termo): array
    {
        $query = Anexo::on('mysql_grammar')->pesquisarTextoExtraido($termo);

        return [$query->toSql(), $query->getBindings()];
    }

    public function test_mysql_usa_o_indice_fulltext(): void
    {
        [$sql, $bindings] = $this->sqlMysql('licenciamento comercial');

        $this->assertStringContainsString('MATCH(texto_extraido) AGAINST (? IN BOOLEAN MODE)', $sql);
        $this->assertSame(['licenciamento* comercial*'], $bindings);
    }

    /**
     * Termos abaixo do tamanho mínimo de token do índice recaem no LIKE, para
     * não desaparecerem dos resultados sem aviso.
     */
    public function test_termo_curto_recai_no_like(): void
    {
        [$sql, $bindings] = $this->sqlMysql('DN');

        $this->assertStringContainsString('like', strtolower($sql));
        $this->assertStringNotContainsString('MATCH', $sql);
        $this->assertSame(['%DN%'], $bindings);
    }

    /** Os operadores do BOOLEAN MODE não podem passar para a expressão. */
    public function test_operadores_do_boolean_mode_sao_descartados(): void
    {
        [$sql, $bindings] = $this->sqlMysql('+licenca* -("verba")');

        $this->assertStringContainsString('AGAINST', $sql);
        $this->assertSame(['licenca* verba*'], $bindings);
    }

    public function test_termo_vazio_nao_altera_a_query(): void
    {
        $base = Anexo::on('mysql_grammar')->toSql();

        $this->assertSame($base, Anexo::on('mysql_grammar')->pesquisarTextoExtraido('   ')->toSql());
        $this->assertSame($base, Anexo::on('mysql_grammar')->pesquisarTextoExtraido(null)->toSql());
    }

    public function test_sqlite_mantem_o_like(): void
    {
        $query = Anexo::query()->pesquisarTextoExtraido('licenciamento');

        $this->assertStringContainsString('like', strtolower($query->toSql()));
        $this->assertSame(['%licenciamento%'], $query->getBindings());
    }
}
