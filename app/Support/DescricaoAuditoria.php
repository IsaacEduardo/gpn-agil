<?php

namespace App\Support;

use App\Enums\DocumentoStatus;
use App\Enums\NivelColaboracao;
use App\Models\AuditLog;
use App\Services\ActividadeColaborativaService as Actividade;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Texto legível de uma linha de auditoria de um Documento Interno: o rótulo da acção
 * e o que foi feito, com o nome de quem foi convidado, a versão guardada, o trecho
 * comentado… O separador Auditoria mostrava só "Update" e o IP.
 */
class DescricaoAuditoria
{
    /** Campos que a gravação toca sem serem uma alteração que interesse a quem lê. */
    private const CAMPOS_TECNICOS = ['updated_at', 'created_at', 'versao_atual', 'revisao_classica', 'versao_major', 'versao_minor', 'versao_patch', 'gabinete_id'];

    private const NOMES_CAMPOS = [
        'titulo' => 'título',
        'conteudo_final' => 'conteúdo',
        'status' => 'estado',
        'destinatario_nome' => 'destinatário',
        'destinatario_cargo' => 'cargo do destinatário',
        'destinatario_orgao' => 'órgão do destinatário',
        'destinatario_local' => 'local do destinatário',
        'bloqueado_edicao' => 'bloqueio de edição',
        'assinado_em' => 'assinatura',
        'numero_referencia' => 'número de referência',
        'documento_especie_id' => 'espécie',
        'departamento_id' => 'departamento',
        'arquivado' => 'arquivo',
    ];

    /** @return array{acao: string, cor: string, detalhe: string} */
    public static function de(AuditLog $a): array
    {
        $n = $a->new_values ?? [];

        return match ($a->action) {
            'create' => ['acao' => 'Criação', 'cor' => 'primary', 'detalhe' => 'Criou o documento.'],
            'update' => self::actualizacao($a),
            'delete' => ['acao' => 'Eliminação', 'cor' => 'danger', 'detalhe' => 'Eliminou o documento.'],
            'print' => ['acao' => 'Impressão', 'cor' => 'secondary', 'detalhe' => 'Imprimiu o documento.'],
            'download' => ['acao' => 'PDF', 'cor' => 'secondary', 'detalhe' => 'Abriu ou descarregou o PDF.'],
            'sign' => ['acao' => 'Assinatura', 'cor' => 'success', 'detalhe' => 'Assinou o documento.'],
            'devolucao' => ['acao' => 'Devolução', 'cor' => 'warning', 'detalhe' => 'Devolveu ao autor'.($a->motivo ? ': '.$a->motivo : '.')],
            'documento.arquivado' => ['acao' => 'Arquivo', 'cor' => 'dark', 'detalhe' => 'Arquivou o documento.'],
            'assistente.resumo' => ['acao' => 'Assistente', 'cor' => 'secondary', 'detalhe' => 'Pediu um resumo ao Assistente de IA.'],

            Actividade::EDICAO => ['acao' => 'Edição', 'cor' => 'info', 'detalhe' => self::edicao($n)],
            Actividade::TITULO => ['acao' => 'Título', 'cor' => 'info', 'detalhe' => 'Mudou o título para «'.($n['titulo'] ?? '').'».'],
            Actividade::DESTINATARIO => ['acao' => 'Destinatário', 'cor' => 'info', 'detalhe' => self::destinatario($n)],
            Actividade::CONVITE => ['acao' => 'Convite', 'cor' => 'primary', 'detalhe' => 'Convidou '.($n['colaborador'] ?? 'um colaborador').' ('.self::nivel($n['nivel'] ?? null).').'],
            Actividade::NIVEL => ['acao' => 'Permissões', 'cor' => 'primary', 'detalhe' => 'Mudou o nível de '.($n['colaborador'] ?? 'um colaborador').' para '.self::nivel($n['nivel'] ?? null).'.'],
            Actividade::REMOCAO => ['acao' => 'Permissões', 'cor' => 'warning', 'detalhe' => 'Removeu '.($n['colaborador'] ?? 'um colaborador').' da colaboração.'],
            Actividade::COMENTARIO => ['acao' => 'Comentário', 'cor' => 'secondary', 'detalhe' => self::comentario($n)],
            Actividade::COMENTARIO_RESOLVIDO => ['acao' => 'Comentário', 'cor' => 'success', 'detalhe' => 'Resolveu o comentário «'.Str::limit($n['texto'] ?? '', 80).'».'],
            Actividade::COMENTARIO_REABERTO => ['acao' => 'Comentário', 'cor' => 'secondary', 'detalhe' => 'Reabriu o comentário «'.Str::limit($n['texto'] ?? '', 80).'».'],

            default => ['acao' => Str::of($a->action)->replace(['.', '_'], ' ')->ucfirst()->toString(), 'cor' => 'secondary', 'detalhe' => ''],
        };
    }

    private static function actualizacao(AuditLog $a): array
    {
        $antes = $a->old_values ?? [];
        $depois = $a->new_values ?? [];
        $mudou = fn (string $k) => array_key_exists($k, $depois) && ($antes[$k] ?? null) != $depois[$k];

        // Versão: a numeração mudou nesta gravação.
        if ($mudou('versao_major') || $mudou('versao_minor') || $mudou('versao_patch')) {
            $versao = ($depois['versao_major'] ?? 0).'.'.($depois['versao_minor'] ?? 0).'.'.($depois['versao_patch'] ?? 0);
            $motivo = trim((string) $a->motivo);
            if (str_starts_with($motivo, Actividade::MARCA_VERSAO)) {
                $descricao = trim(substr($motivo, strlen(Actividade::MARCA_VERSAO)));

                return ['acao' => 'Versão', 'cor' => 'success', 'detalhe' => "Guardou a v{$versao} na edição colaborativa"
                    .($descricao !== '' ? " — «{$descricao}»." : '.')];
            }

            return ['acao' => 'Versão', 'cor' => 'success', 'detalhe' => "Guardou a v{$versao}"
                .($motivo !== '' ? " — motivo: {$motivo}." : '.')];
        }

        if ($mudou('status')) {
            $estado = DocumentoStatus::tryFrom((string) $depois['status'])?->label() ?? $depois['status'];

            return ['acao' => 'Estado', 'cor' => 'warning', 'detalhe' => "Mudou o estado para «{$estado}»."];
        }

        $campos = collect(array_keys($depois))
            ->reject(fn ($k) => in_array($k, self::CAMPOS_TECNICOS, true))
            ->filter($mudou)
            ->map(fn ($k) => self::NOMES_CAMPOS[$k] ?? str_replace('_', ' ', $k))
            ->unique()
            ->values();

        return ['acao' => 'Alteração', 'cor' => 'info', 'detalhe' => $campos->isEmpty()
            ? 'Actualizou o documento.'
            : 'Alterou: '.$campos->implode(', ').'.'.($a->motivo ? ' Motivo: '.$a->motivo : '')];
    }

    private static function edicao(array $n): string
    {
        $total = (int) ($n['alteracoes'] ?? 0);
        $texto = 'Editou o texto — '.$total.' '.($total === 1 ? 'alteração' : 'alterações');
        if (empty($n['de']) || empty($n['ate'])) {
            return $texto.'.';
        }
        $de = Carbon::parse($n['de']);
        $ate = Carbon::parse($n['ate']);

        return $de->format('H:i') === $ate->format('H:i')
            ? $texto.' às '.$de->format('H:i').'.'
            : $texto.' entre '.$de->format('H:i').' e '.$ate->format('H:i').'.';
    }

    private static function destinatario(array $n): string
    {
        $partes = collect(['destinatario_nome' => 'nome', 'destinatario_cargo' => 'cargo', 'destinatario_orgao' => 'órgão', 'destinatario_local' => 'local'])
            ->filter(fn ($rotulo, $campo) => filled($n[$campo] ?? null))
            ->map(fn ($rotulo, $campo) => $rotulo.' «'.$n[$campo].'»');

        return 'Alterou o destinatário'.($partes->isEmpty() ? '.' : ': '.$partes->implode(', ').'.');
    }

    private static function comentario(array $n): string
    {
        $texto = '«'.Str::limit($n['texto'] ?? '', 120).'»';
        if (! empty($n['resposta'])) {
            return "Respondeu a um comentário: {$texto}.";
        }

        return filled($n['trecho'] ?? null)
            ? 'Comentou o trecho «'.Str::limit($n['trecho'], 60)."»: {$texto}."
            : "Comentou: {$texto}.";
    }

    private static function nivel(?string $nivel): string
    {
        return $nivel ? (NivelColaboracao::tryFrom($nivel)?->label() ?? $nivel) : '—';
    }
}
