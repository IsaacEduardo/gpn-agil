{{--
    Cabeçalho institucional padronizado — fonte única (Fase 3/4).

    Ordem obrigatória da regra de negócio:
        [logo] → REPÚBLICA DE ANGOLA → Nome oficial da Instituição → Gabinete do criador

    Compatível com ecrã e com PDF (Dompdf): usa apenas estilos inline simples
    (sem flexbox), pelo que pode ser incluído em qualquer template.

    Parâmetros (@include('partials.document-header', [...])):
      $logoSrc         string   URL (ecrã) ou caminho absoluto/base64 (PDF).
                                Por omissão usa $dadosInstituicao->logo_url.
      $gabineteNome    ?string  Linha do gabinete JÁ resolvida (ver CabecalhoDocumento).
                                Se vazia, a linha é omitida.
      $dadosInstituicao model   Por omissão usa o partilhado globalmente (View::share).
      $nomeInstituicao ?string  Sobrepõe o nome oficial (opcional).
      $larguraLogo     string   Ex.: '22mm' (PDF) ou '90px' (ecrã). Por omissão '22mm'.
--}}
@php
    $inst = $dadosInstituicao ?? view()->shared('dadosInstituicao');
    $logo = ($logoSrc ?? null) ?: optional($inst)->logo_url;
    $republica = optional($inst)->cabecalho_linha1 ?: 'REPÚBLICA DE ANGOLA';
    $nomeOficial = ($nomeInstituicao ?? null) ?: (optional($inst)->nome_oficial ?: optional($inst)->cabecalho_linha2);
    $largura = $larguraLogo ?? '22mm';
    $gabinete = $gabineteNome ?? null;
@endphp
<div class="doc-header" style="text-align: center; line-height: 1.4;">
    @if ($logo)
        <img class="doc-header__logo" src="{{ $logo }}" alt="Insígnia da {{ $republica }}"
             style="display: block; margin: 0 auto 3mm; width: {{ $largura }}; height: auto;">
    @endif

    <div class="doc-header__republica" style="font-weight: bold; text-transform: uppercase; font-size: 12pt;">
        {{ $republica }}
    </div>

    @if (filled($nomeOficial))
        <div class="doc-header__instituicao" style="font-weight: bold; text-transform: uppercase; font-size: 14pt;">
            {{ $nomeOficial }}
        </div>
    @endif

    @if (filled($gabinete))
        <div class="doc-header__gabinete" style="font-weight: bold; text-transform: uppercase; font-size: 12pt;">
            {{ $gabinete }}
        </div>
    @endif
</div>
