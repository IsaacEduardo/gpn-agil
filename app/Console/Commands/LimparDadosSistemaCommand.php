<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\DadosInstituicao;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Entrega do sistema limpo: fica só a configuração (instituição, gabinetes, departamentos,
 * espécies, modelos, papéis e permissões) e uma conta de administrador; tudo o resto —
 * documentos, livro de entrada, numeração, utilizadores, notificações, auditoria, ficheiros —
 * é apagado e os contadores (ids) recomeçam.
 *
 * Lista do que FICA (não do que sai): uma tabela nova fica limpa por omissão.
 * Referências dos registos mantidos a utilizadores apagados (chefias, autor do modelo…) ou a
 * tabelas limpas passam a NULL. Sem --executar só mostra o que faria.
 */
class LimparDadosSistemaCommand extends Command
{
    protected $signature = 'sistema:limpar-dados
        {--manter-admin= : Email da conta de administrador que fica (obrigatório)}
        {--executar : Aplica a limpeza (sem esta opção só mostra o que faria)}';

    protected $description = 'Apaga os dados de utilização e mantém só a configuração da instituição e um administrador';

    /** Configuração que fica inteira. */
    private const TABELAS_MANTIDAS = [
        'migrations', 'dados_instituicao', 'gabinetes', 'departamentos', 'documento_especies',
        'modelo_documentos', 'roles', 'permissions', 'role_has_permissions', 'permission_role',
        'retention_schedules',
    ];

    /** Tabelas que ficam só com as linhas do administrador mantido: tabela => coluna do utilizador. */
    private const TABELAS_DO_ADMIN = [
        'users' => 'id',
        'model_has_roles' => 'model_id',
        'model_has_permissions' => 'model_id',
        'departamento_user' => 'user_id',
        'notification_preferences' => 'user_id',
        'notification_user_settings' => 'user_id',
        'modelo_despachos' => 'user_id', // os globais (user_id NULL) também ficam
    ];

    /** Tabelas com relação polimórfica ao utilizador (model_type + model_id). */
    private const POLIMORFICAS = ['model_has_roles', 'model_has_permissions'];

    public function handle(): int
    {
        $email = (string) $this->option('manter-admin');
        $admin = $email !== '' ? User::where('email', $email)->first() : null;

        if (! $admin || ! $admin->isAdmin()) {
            $this->error('Indique em --manter-admin o email de uma conta de administrador existente.');

            return self::FAILURE;
        }

        $tabelas = collect(Schema::getTableListing(schemaQualified: false))
            ->reject(fn ($t) => str_starts_with($t, 'sqlite_'))
            ->values();
        $doAdmin = $tabelas->filter(fn ($t) => array_key_exists($t, self::TABELAS_DO_ADMIN))->values();
        $limpar = $tabelas->reject(fn ($t) => in_array($t, self::TABELAS_MANTIDAS, true) || $doAdmin->contains($t))->values();

        $this->resumo($tabelas, $admin);
        $ficheiros = $this->ficheirosAApagar();
        $this->line(sprintf('Ficheiros a apagar: %d (%.1f MB); fica o logótipo/rodapé da instituição.',
            count($ficheiros), array_sum(array_column($ficheiros, 'tamanho')) / 1048576));

        if (! $this->option('executar')) {
            $this->comment('Simulação: nada foi alterado. Repita com --executar para aplicar.');

            return self::SUCCESS;
        }

        // Fora da transacção: no SQLite o PRAGMA não tem efeito dentro de uma.
        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($limpar, $doAdmin, $admin) {
                foreach ($limpar as $tabela) {
                    DB::table($tabela)->delete();
                }
                foreach ($doAdmin as $tabela) {
                    $this->linhasDeOutros($tabela, $admin)->delete();
                }
                $this->anularReferencias($admin, $limpar);
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->reiniciarContadores($limpar->merge($doAdmin));

        foreach ($ficheiros as $f) {
            Storage::disk($f['disco'])->delete($f['caminho']);
        }
        foreach (['private', 'local'] as $disco) {
            foreach (Storage::disk($disco)->directories() as $pasta) {
                Storage::disk($disco)->deleteDirectory($pasta);
            }
        }

        // Rasto da própria limpeza (a auditoria anterior foi apagada com o resto).
        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'sistema.limpeza',
            'auditable_type' => 'sistema',
            'auditable_id' => 0,
            'new_values' => ['admin_mantido' => $admin->email, 'tabelas_limpas' => $limpar->count()],
            'motivo' => 'Entrega do sistema limpo para início da utilização definitiva',
        ]);

        $this->info('Limpeza concluída. Fica a conta '.$admin->email.'.');
        $this->comment('Falta: limpar a cache e as sessões e reiniciar a fila.');

        return self::SUCCESS;
    }

    private function resumo(Collection $tabelas, User $admin): void
    {
        $linhas = [];
        foreach ($tabelas->sort() as $t) {
            $total = DB::table($t)->count();
            if (in_array($t, self::TABELAS_MANTIDAS, true)) {
                $linhas[] = [$t, $total, $total, 'mantém'];
            } elseif (array_key_exists($t, self::TABELAS_DO_ADMIN)) {
                $linhas[] = [$t, $total, $total - $this->linhasDeOutros($t, $admin)->count(), 'só o admin'];
            } elseif ($total > 0) {
                $linhas[] = [$t, $total, 0, 'apaga'];
            }
        }

        $this->info("Fica a conta {$admin->email} (id {$admin->id}).");
        $this->table(['Tabela', 'Linhas', 'Ficam', 'Acção'], $linhas);
    }

    /** Linhas de uma tabela "do admin" que não pertencem ao administrador mantido. */
    private function linhasDeOutros(string $tabela, User $admin)
    {
        $coluna = self::TABELAS_DO_ADMIN[$tabela];
        $q = DB::table($tabela);

        if ($tabela === 'modelo_despachos') {
            return $q->whereNotNull($coluna)->where($coluna, '!=', $admin->id);
        }
        if (in_array($tabela, self::POLIMORFICAS, true)) {
            return $q->where(fn ($w) => $w->where($coluna, '!=', $admin->id)
                ->orWhere('model_type', '!=', $admin->getMorphClass()));
        }

        return $q->where($coluna, '!=', $admin->id);
    }

    /**
     * Nos registos que ficam, as chaves para utilizadores apagados ou para tabelas limpas
     * passam a NULL (ex.: responsável do gabinete, chefe do departamento, autor do modelo).
     */
    private function anularReferencias(User $admin, Collection $limpar): void
    {
        foreach (array_merge(self::TABELAS_MANTIDAS, array_keys(self::TABELAS_DO_ADMIN)) as $tabela) {
            if (! Schema::hasTable($tabela)) {
                continue;
            }
            foreach (Schema::getForeignKeys($tabela) as $fk) {
                $alvo = $fk['foreign_table'];
                $coluna = $fk['columns'][0];

                if ($alvo === 'users') {
                    DB::table($tabela)->whereNotNull($coluna)->where($coluna, '!=', $admin->id)->update([$coluna => null]);
                } elseif ($limpar->contains($alvo)) {
                    DB::table($tabela)->whereNotNull($coluna)->update([$coluna => null]);
                }
            }
        }
    }

    /** Os ids recomeçam (tabelas vazias em 1; nas que ficam com linhas, a seguir à maior). */
    private function reiniciarContadores(Collection $tabelas): void
    {
        $driver = DB::getDriverName();

        foreach ($tabelas->unique() as $tabela) {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement("ALTER TABLE `{$tabela}` AUTO_INCREMENT = 1");
            } elseif ($driver === 'sqlite' && DB::table('sqlite_master')->where('name', 'sqlite_sequence')->exists()) {
                DB::table('sqlite_sequence')->where('name', $tabela)->delete();
            }
        }
    }

    /**
     * Ficheiros carregados pelos utilizadores (anexos, digitalizações, fotos…). No disco
     * público fica só o que a instituição usa (logótipo e rodapé).
     *
     * @return array<int, array{disco: string, caminho: string, tamanho: int}>
     */
    private function ficheirosAApagar(): array
    {
        $instituicao = DadosInstituicao::first();
        $manter = array_filter([$instituicao?->logo_path, $instituicao?->rodape_img_path]);
        $lista = [];

        foreach (['private', 'local', 'public'] as $disco) {
            $fs = Storage::disk($disco);
            foreach ($fs->allFiles() as $caminho) {
                if (str_ends_with($caminho, '.gitignore') || ($disco === 'public' && in_array($caminho, $manter, true))) {
                    continue;
                }
                $lista[] = ['disco' => $disco, 'caminho' => $caminho, 'tamanho' => (int) $fs->size($caminho)];
            }
        }

        return $lista;
    }
}
