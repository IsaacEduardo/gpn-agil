{{--
    Sincroniza os campos Assunto/destinatário com os marcadores do corpo no editor TinyMCE
    (ver App\Support\CamposVinculados). Altera só os marcadores; o resto do texto mantém-se.
    Uso: ligarCamposVinculados(callbackDepoisDeAlterar) depois de o DOM estar pronto.
--}}
@php
    $cidadeCamposVinculados = optional($dadosInstituicao ?? view()->shared('dadosInstituicao'))->cidade ?: 'Lubango';
@endphp
<style>
    #live-preview-content .campo-vazio { color: #b45309; background: #fef3c7; }
</style>
<script>
    const camposVinculados = @json(\App\Support\CamposVinculados::paraJs($cidadeCamposVinculados));

    function sincronizarCampoVinculado(campo) {
        const editor = window.tinymce ? tinymce.get('conteudo_final') : null;
        const def = camposVinculados.campos[campo];
        const input = document.getElementById(campo);
        if (!editor || !def || !input) return;

        const marcadores = editor.dom.select('span.' + def.classe);
        if (!marcadores.length) return;

        let valor = input.value.trim();
        if (!valor && def.padrao) valor = def.padrao;
        const vazio = valor === '';

        editor.undoManager.transact(() => {
            marcadores.forEach(no => {
                no.textContent = vazio ? def.guia : valor;
                editor.dom.toggleClass(no, camposVinculados.classeVazio, vazio);
            });
        });
        editor.setDirty(true);
    }

    function ligarCamposVinculados(depoisDeAlterar) {
        Object.keys(camposVinculados.campos).forEach(campo => {
            const input = document.getElementById(campo);
            if (!input) return;
            input.addEventListener('input', () => {
                sincronizarCampoVinculado(campo);
                if (typeof depoisDeAlterar === 'function') depoisDeAlterar();
            });
        });
    }
</script>
