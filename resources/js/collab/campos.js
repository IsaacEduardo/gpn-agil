/**
 * Campos Assunto/destinatário no editor colaborativo.
 *
 * Ao escrever num campo, o texto das marcas correspondentes (MarcaComClasse com a classe do
 * campo) é substituído numa transacção do editor — os outros participantes recebem-no pelo
 * Yjs. O destinatário é também gravado no documento (autosave, como o título).
 * Classes e textos-guia vêm de App\Support\CamposVinculados::paraJs() (cfg.campos).
 */
export function ligarCamposColaborativos(editor, cfg, http, obterRevisao) {
    if (!cfg.podeEditar || !cfg.campos) return;

    const { classeVazio, campos } = cfg.campos;
    const idDoInput = (campo) => (campo === 'titulo' ? 'collab-titulo' : `collab-${campo}`);
    let valoresPendentes = {};
    let timer = null;

    Object.keys(campos).forEach((campo) => {
        const input = document.getElementById(idDoInput(campo));
        if (!input) return;

        input.addEventListener('input', () => {
            actualizarMarcas(editor, campos[campo], classeVazio, input.value);

            // O título tem autosave próprio (collab.js → wireTitulo).
            if (campo === 'titulo' || !cfg.urls.campos) return;
            valoresPendentes[campo] = input.value;
            clearTimeout(timer);
            timer = setTimeout(gravar, 800);
        });
    });

    function gravar() {
        const dados = valoresPendentes;
        valoresPendentes = {};
        http.post(cfg.urls.campos, { ...dados, revisao: obterRevisao() })
            .catch(() => { valoresPendentes = { ...dados, ...valoresPendentes }; });
    }
}

function temClasse(classes, classe) {
    return ` ${classes || ''} `.includes(` ${classe} `);
}

function comClasseVazio(classes, classeVazio, vazio) {
    const lista = (classes || '').split(/\s+/).filter((c) => c && c !== classeVazio);
    if (vazio) lista.push(classeVazio);
    return lista.join(' ');
}

/**
 * Substitui o texto de todas as ocorrências do campo. Um campo vazio nunca fica sem texto
 * (o ProseMirror não admite texto vazio): leva o texto-guia e a classe de vazio.
 */
export function actualizarMarcas(editor, def, classeVazio, valorBruto) {
    const { state } = editor;
    const tipo = state.schema.marks.marcaComClasse;
    if (!tipo) return;

    let valor = (valorBruto || '').trim();
    if (!valor && def.padrao) valor = def.padrao;
    const vazio = valor === '';
    const texto = vazio ? def.guia : valor;

    // Intervalos contíguos com a marca deste campo (o texto pode estar partido por outras marcas).
    const intervalos = [];
    state.doc.descendants((node, pos) => {
        if (!node.isText) return;
        const marca = node.marks.find((m) => m.type === tipo && temClasse(m.attrs.class, def.classe));
        if (!marca) return;
        const ultimo = intervalos[intervalos.length - 1];
        if (ultimo && ultimo.to === pos) {
            ultimo.to = pos + node.nodeSize;
        } else {
            intervalos.push({ from: pos, to: pos + node.nodeSize, marca, marcas: node.marks });
        }
    });
    if (!intervalos.length) return;

    const tr = state.tr;
    // Do fim para o início, para as posições anteriores continuarem válidas.
    intervalos.reverse().forEach(({ from, to, marca, marcas }) => {
        const nova = tipo.create({ class: comClasseVazio(marca.attrs.class, classeVazio, vazio) });
        const outras = marcas.filter((m) => m.type !== tipo);
        tr.replaceWith(from, to, state.schema.text(texto, [...outras, nova]));
    });
    editor.view.dispatch(tr);
}
