<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DocumentoInterno;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Histórico de "quem fez o quê" na edição colaborativa, na tabela de auditoria do
 * documento (separador Auditoria da ficha; texto em App\Support\DescricaoAuditoria).
 *
 * Antes a colaboração não deixava rasto: o autosave é silencioso (saveQuietly), e
 * convites, comentários e mudanças de título não eram registados. O log Yjs guarda o
 * autor de cada alteração, mas é apagado na compactação.
 *
 * Edições ao texto e aos campos são agrupadas por pessoa: uma linha por sessão de
 * trabalho ("editou o texto — 14 alterações entre 19:20 e 19:23"), não uma por tecla.
 */
class ActividadeColaborativaService
{
    /** Sem actividade da mesma pessoa durante isto, a próxima edição abre linha nova. */
    public const JANELA_MINUTOS = 10;

    /** Uma linha agrupada é actualizada na base no máximo a cada N segundos. */
    public const GRAVAR_A_CADA_SEGUNDOS = 5;

    public const EDICAO = 'colaboracao.edicao';

    public const TITULO = 'colaboracao.titulo';

    public const DESTINATARIO = 'colaboracao.destinatario';

    public const CONVITE = 'colaboracao.convite';

    public const NIVEL = 'colaboracao.nivel';

    public const REMOCAO = 'colaboracao.remocao';

    public const COMENTARIO = 'colaboracao.comentario';

    public const COMENTARIO_RESOLVIDO = 'colaboracao.comentario_resolvido';

    public const COMENTARIO_REABERTO = 'colaboracao.comentario_reaberto';

    /** Marca no motivo da auditoria de uma versão guardada na colaboração. */
    public const MARCA_VERSAO = '[colaboração]';

    /** Um acontecimento pontual (convite, comentário, …). Nunca impede a operação. */
    public function registar(DocumentoInterno $doc, ?User $user, string $acao, array $dados = []): void
    {
        try {
            AuditLog::create($this->linha($doc, $user, $acao, $dados));
        } catch (\Throwable $e) {
            Log::warning('Edição colaborativa: actividade não registada.', ['acao' => $acao, 'erro' => $e->getMessage()]);
        }
    }

    /** Mais uma alteração ao texto, na linha da sessão de trabalho desta pessoa. */
    public function registarEdicao(DocumentoInterno $doc, User $user): void
    {
        $this->agrupar($doc, $user, self::EDICAO, fn (array $d) => [
            'alteracoes' => ($d['alteracoes'] ?? 0) + 1,
            'de' => $d['de'] ?? now()->toIso8601String(),
            'ate' => now()->toIso8601String(),
        ]);
    }

    /**
     * Título ou destinatário: o autosave corre a cada pausa na escrita; guarda-se uma
     * linha por sessão com o último valor.
     */
    public function registarCampos(DocumentoInterno $doc, User $user, string $acao, array $valores): void
    {
        $this->agrupar($doc, $user, $acao, fn (array $d) => array_merge($d, $valores, [
            'de' => $d['de'] ?? now()->toIso8601String(),
            'ate' => now()->toIso8601String(),
        ]));
    }

    /**
     * @param  callable(array): array  $proximo  dados da linha a partir dos actuais
     */
    private function agrupar(DocumentoInterno $doc, User $user, string $acao, callable $proximo): void
    {
        try {
            $chave = "collab_actividade:{$doc->id}:{$user->id}:{$acao}";
            $sessao = Cache::get($chave);
            $agora = now();

            if (! $sessao || $agora->diffInSeconds($sessao['ultimo'], true) > self::JANELA_MINUTOS * 60
                || ! AuditLog::whereKey($sessao['id'])->exists()) {
                $dados = $proximo([]);
                $sessao = ['id' => AuditLog::create($this->linha($doc, $user, $acao, $dados))->id, 'dados' => $dados, 'gravado' => $agora];
            } else {
                $sessao['dados'] = $proximo($sessao['dados']);
                if ($agora->diffInSeconds($sessao['gravado'], true) >= self::GRAVAR_A_CADA_SEGUNDOS) {
                    AuditLog::whereKey($sessao['id'])->first()?->update(['new_values' => $sessao['dados']]);
                    $sessao['gravado'] = $agora;
                }
            }

            $sessao['ultimo'] = $agora;
            Cache::put($chave, $sessao, now()->addMinutes(self::JANELA_MINUTOS + 5));
        } catch (\Throwable $e) {
            Log::warning('Edição colaborativa: actividade não registada.', ['acao' => $acao, 'erro' => $e->getMessage()]);
        }
    }

    /**
     * O que cada pessoa fez no documento desde $inicio, numa frase por pessoa — para o
     * resumo enviado ao autor. As linhas agrupadas lêem-se da cache quando lá estão (a
     * base só é actualizada a cada GRAVAR_A_CADA_SEGUNDOS).
     *
     * @param  array<int, string>  $pessoas  user_id => nome
     * @return array<int, string>
     */
    public function resumoPorPessoa(DocumentoInterno $doc, Carbon $inicio, array $pessoas): array
    {
        $linhas = AuditLog::where('auditable_type', DocumentoInterno::class)
            ->where('auditable_id', $doc->id)
            ->whereIn('user_id', array_keys($pessoas))
            // Linhas agrupadas podem ter começado antes da janela e continuado dentro dela.
            ->where('created_at', '>=', $inicio->copy()->subMinutes(self::JANELA_MINUTOS))
            ->orderBy('id')
            ->get();

        $frases = [];
        foreach ($pessoas as $userId => $nome) {
            $minhas = $linhas->where('user_id', $userId);
            $dados = function (AuditLog $l) use ($doc, $userId) {
                $sessao = Cache::get("collab_actividade:{$doc->id}:{$userId}:{$l->action}");

                return ($sessao && ($sessao['id'] ?? null) === $l->id) ? $sessao['dados'] : ($l->new_values ?? []);
            };
            $naJanela = fn (AuditLog $l) => Carbon::parse($dados($l)['ate'] ?? $l->created_at)->gte($inicio);

            $alteracoes = $minhas->where('action', self::EDICAO)->filter($naJanela)->sum(fn ($l) => (int) ($dados($l)['alteracoes'] ?? 0));
            $recentes = $minhas->filter(fn ($l) => $l->created_at->gte($inicio));
            $comentarios = $recentes->where('action', self::COMENTARIO)->filter(fn ($l) => empty($l->new_values['resposta']))->count();
            $respostas = $recentes->where('action', self::COMENTARIO)->filter(fn ($l) => ! empty($l->new_values['resposta']))->count();
            $versoes = $recentes->where('action', 'update')
                ->filter(fn ($l) => str_starts_with((string) $l->motivo, self::MARCA_VERSAO))
                ->map(fn ($l) => 'v'.($l->new_values['versao_major'] ?? 0).'.'.($l->new_values['versao_minor'] ?? 0).'.'.($l->new_values['versao_patch'] ?? 0))
                ->values();

            $partes = array_values(array_filter([
                $alteracoes ? $alteracoes.' '.($alteracoes === 1 ? 'alteração' : 'alterações').' ao texto' : null,
                $comentarios ? $comentarios.' '.($comentarios === 1 ? 'comentário' : 'comentários') : null,
                $respostas ? $respostas.' '.($respostas === 1 ? 'resposta' : 'respostas').' a comentários' : null,
                $minhas->where('action', self::TITULO)->filter($naJanela)->isNotEmpty() ? 'mudou o título' : null,
                $minhas->where('action', self::DESTINATARIO)->filter($naJanela)->isNotEmpty() ? 'alterou o destinatário' : null,
                $versoes->isNotEmpty() ? 'guardou '.($versoes->count() === 1 ? 'a ' : 'as ').self::juntar($versoes->all()) : null,
            ]));

            $frases[] = $nome.': '.($partes ? self::juntar($partes) : 'editou o documento').'.';
        }

        return $frases;
    }

    /** "a", "a e b", "a, b e c". */
    private static function juntar(array $itens): string
    {
        $ultimo = array_pop($itens);

        return $itens ? implode(', ', $itens).' e '.$ultimo : (string) $ultimo;
    }

    private function linha(DocumentoInterno $doc, ?User $user, string $acao, array $dados): array
    {
        return [
            'user_id' => $user?->id,
            'action' => $acao,
            'auditable_type' => DocumentoInterno::class,
            'auditable_id' => $doc->id,
            'old_values' => null,
            'new_values' => $dados,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ];
    }
}
