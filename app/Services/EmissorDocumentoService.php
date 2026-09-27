<?php

namespace App\Services;

use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\Gabinete;
use App\Models\User;
use App\Support\SeriesNumeracao;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Quem emite um documento interno: um departamento ou o próprio gabinete.
 *
 * Valores do formulário: "dep:{id}" ou "gab:{id}" (campo `emissor`; `departamento_id`
 * continua aceite por compatibilidade).
 *  - Admin: qualquer departamento ou gabinete (sem pré-selecção).
 *  - Chefe / super-chefe de gabinete: o próprio gabinete (pré-seleccionado) e os
 *    departamentos desse gabinete; também o seu departamento, se tiver.
 *  - Restantes: os seus departamentos.
 * Nunca há recurso silencioso a "um departamento qualquer": sem emissor válido, erro.
 */
class EmissorDocumentoService
{
    public const MENSAGEM_NOTA_SO_DEPARTAMENTO = 'A Nota é emitida por um departamento: escolha o departamento que a elabora.';

    /**
     * Opções agrupadas por gabinete, para o formulário.
     *
     * @return Collection<int, array{grupo: string, opcoes: array<int, array{valor: string, rotulo: string}>}>
     */
    public function opcoesPara(User $user): Collection
    {
        $gabinetes = $user->isAdmin()
            ? Gabinete::with(['departamentos' => fn ($q) => $q->orderBy('nome')])->orderBy('nome')->get()
            : $this->gabinetesChefiados($user)->load(['departamentos' => fn ($q) => $q->orderBy('nome')]);

        $grupos = $gabinetes->map(fn (Gabinete $g) => [
            'grupo' => $g->nome,
            'opcoes' => collect([['valor' => "gab:{$g->id}", 'rotulo' => "{$g->nome} (o próprio gabinete)"]])
                ->concat($g->departamentos->map(fn (Departamento $d) => [
                    'valor' => "dep:{$d->id}",
                    'rotulo' => ($d->sigla ? "{$d->sigla} — " : '').$d->nome,
                ]))->all(),
        ]);

        // Departamentos próprios que ainda não estejam listados.
        $listados = $grupos->pluck('opcoes')->flatten(1)->pluck('valor')->all();
        $proprios = $this->departamentosProprios($user)
            ->reject(fn (Departamento $d) => in_array("dep:{$d->id}", $listados, true));

        if ($proprios->isNotEmpty()) {
            $grupos->push([
                'grupo' => 'O meu departamento',
                'opcoes' => $proprios->map(fn (Departamento $d) => [
                    'valor' => "dep:{$d->id}",
                    'rotulo' => ($d->sigla ? "{$d->sigla} — " : '').$d->nome,
                ])->values()->all(),
            ]);
        }

        return $grupos->values();
    }

    /** Valor pré-seleccionado no formulário (null = obrigatório escolher). */
    public function padraoPara(User $user): ?string
    {
        if ($user->isAdmin()) {
            return null;
        }

        if ($gabinete = $this->gabinetesChefiados($user)->first()) {
            return "gab:{$gabinete->id}";
        }

        $dep = $this->departamentosProprios($user)->first();

        return $dep ? "dep:{$dep->id}" : null;
    }

    /**
     * Resolve e valida o emissor pedido.
     *
     * @return array{departamento: ?Departamento, gabinete: Gabinete}
     *
     * @throws ValidationException
     */
    public function resolver(User $user, ?string $emissor, $departamentoId = null, ?DocumentoEspecie $especie = null): array
    {
        $valor = $emissor ?: ($departamentoId ? "dep:{$departamentoId}" : $this->padraoOuProprioPara($user));

        if (! $valor || ! preg_match('/^(dep|gab):(\d+)$/', $valor, $m)) {
            throw ValidationException::withMessages([
                'emissor' => 'Indique quem emite o documento (departamento ou gabinete).',
            ]);
        }

        [$tipo, $id] = [$m[1], (int) $m[2]];

        if ($tipo === 'gab') {
            $gabinete = Gabinete::find($id);
            if (! $gabinete || ! $this->podeEmitirPeloGabinete($user, $gabinete)) {
                throw ValidationException::withMessages([
                    'emissor' => 'Só o Chefe de Gabinete pode emitir documentos em nome deste gabinete.',
                ]);
            }

            // A Nota é sempre de um departamento (assina o Chefe de Departamento).
            if (SeriesNumeracao::abreviaturaEspecie($especie) === 'NOTA') {
                throw ValidationException::withMessages([
                    'emissor' => self::MENSAGEM_NOTA_SO_DEPARTAMENTO,
                ]);
            }

            return ['departamento' => null, 'gabinete' => $gabinete];
        }

        $departamento = Departamento::with('gabinete')->find($id);
        if (! $departamento || ! $this->podeEmitirPeloDepartamento($user, $departamento)) {
            throw ValidationException::withMessages([
                'emissor' => 'Não pode emitir documentos em nome deste departamento.',
            ]);
        }

        return ['departamento' => $departamento, 'gabinete' => $departamento->gabinete];
    }

    public function podeEmitirPeloGabinete(User $user, Gabinete $gabinete): bool
    {
        return $user->isAdmin() || $this->gabinetesChefiados($user)->contains('id', $gabinete->id);
    }

    public function podeEmitirPeloDepartamento(User $user, Departamento $departamento): bool
    {
        return $user->isAdmin()
            || $this->departamentosProprios($user)->contains('id', $departamento->id)
            || $this->gabinetesChefiados($user)->contains('id', $departamento->gabinete_id);
    }

    /** Gabinetes que o utilizador chefia (responsável ou super-chefe). */
    public function gabinetesChefiados(User $user): Collection
    {
        return Gabinete::where('responsavel_id', $user->id)
            ->orWhere('super_chefe_id', $user->id)
            ->orderBy('nome')
            ->get();
    }

    /** Departamento principal e restantes departamentos do utilizador. */
    public function departamentosProprios(User $user): Collection
    {
        $ids = collect([$user->departamento_id, $user->departamentoPrincipal()?->id])
            ->merge(method_exists($user, 'departamentos') ? $user->departamentos()->pluck('departamentos.id') : [])
            ->filter()->unique()->values();

        return $ids->isEmpty() ? collect() : Departamento::whereIn('id', $ids)->orderBy('nome')->get();
    }

    /** Sem escolha no pedido: o padrão; o Admin usa o seu departamento, se tiver. */
    private function padraoOuProprioPara(User $user): ?string
    {
        if ($padrao = $this->padraoPara($user)) {
            return $padrao;
        }

        $dep = $this->departamentosProprios($user)->first();

        return $dep ? "dep:{$dep->id}" : null;
    }
}
