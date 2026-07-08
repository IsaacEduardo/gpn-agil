<?php

namespace App\Support;

use App\Models\DadosInstituicao;
use App\Models\Gabinete;
use App\Models\User;

/**
 * Fonte única para o cabeçalho institucional dos documentos.
 *
 * Resolve o gabinete do criador (via o seu departamento) e devolve a linha
 * padronizada exigida pela regra de negócio:
 *
 *     [logo] → REPÚBLICA DE ANGOLA → Nome oficial da Instituição → Gabinete do criador
 *
 * Concentrar esta lógica aqui elimina os textos fixos ("DLP", "SECRETARIA
 * GERAL") espalhados pelos templates e garante consistência entre todos os
 * documentos (requisições, termos, documentos internos, etc.).
 */
class CabecalhoDocumento
{
    /**
     * Resolve o Gabinete a partir de um utilizador, através do seu
     * departamento principal (departamento_id, com fallback para o primeiro
     * departamento da relação múltipla).
     */
    public static function gabineteDeUser(?User $user): ?Gabinete
    {
        return $user?->gabinete();
    }

    /**
     * Linha do gabinete para o cabeçalho (em caixa alta).
     *
     * Quando o gabinete não está definido, recorre ao fallback institucional
     * (cabecalho_linha3). Devolve null se não houver gabinete nem fallback,
     * permitindo ao cabeçalho omitir a linha em vez de imprimir texto fixo.
     */
    public static function linhaGabinete(?Gabinete $gabinete, ?DadosInstituicao $instituicao = null): ?string
    {
        if ($gabinete && filled($gabinete->nome)) {
            return mb_strtoupper($gabinete->nome);
        }

        $instituicao ??= self::instituicao();
        $fallback = $instituicao?->cabecalho_linha3;

        return filled($fallback) ? mb_strtoupper($fallback) : null;
    }

    /**
     * Conveniência: resolve a linha do gabinete diretamente a partir do criador.
     */
    public static function linhaGabineteDeUser(?User $user, ?DadosInstituicao $instituicao = null): ?string
    {
        return self::linhaGabinete(self::gabineteDeUser($user), $instituicao);
    }

    /**
     * Dados da instituição partilhados globalmente (View::share), com fallback
     * a uma consulta direta ou a uma instância vazia.
     */
    public static function instituicao(): DadosInstituicao
    {
        return view()->shared('dadosInstituicao') ?? DadosInstituicao::first() ?? new DadosInstituicao;
    }
}
