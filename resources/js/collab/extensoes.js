import { Extension, Mark, Node } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import TextAlign from '@tiptap/extension-text-align';
import Table from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableHeader from '@tiptap/extension-table-header';
import TableCell from '@tiptap/extension-table-cell';

/**
 * Esquema do editor colaborativo, alinhado com o HTML dos modelos (ofício, etc.).
 *
 * Sem estas extensões o Tiptap descartava marcadores (campo-vinculado, ref-nossa-referencia),
 * <div> com recuo e text-transform — e o autosave gravava esse HTML degradado.
 * Tudo o que aqui se preserva continua a passar pelo Sanitizer no servidor.
 */

const CLASSES_PRESERVADAS = /(^|\s)(campo-vinculado|ref-nossa-referencia)(\s|$)/;
const BLOCOS = 'p,div,table,ul,ol,blockquote,h1,h2,h3,h4,h5,h6,hr,pre';

// style sem text-align: esse é do TextAlign; mantê-lo aqui duplicava-o a cada ida e volta.
const semTextAlign = (style) => (style || '')
    .split(';')
    .map((s) => s.trim())
    .filter((s) => s && !/^text-align\s*:/i.test(s))
    .join('; ') || null;

const atributoStyle = (limpar = (s) => s || null) => ({
    style: {
        default: null,
        parseHTML: (el) => limpar(el.getAttribute('style')),
        renderHTML: (attrs) => (attrs.style ? { style: attrs.style } : {}),
    },
});

/** <span style="..."> (ex.: sublinhado + maiúsculas do Local). */
export const EstiloInline = Mark.create({
    name: 'estiloInline',
    priority: 105, // por fora da marca de campo
    addAttributes: () => atributoStyle(),
    parseHTML: () => [{
        tag: 'span[style]',
        getAttrs: (el) => (CLASSES_PRESERVADAS.test(el.getAttribute('class') || '') ? false : null),
    }],
    renderHTML: ({ HTMLAttributes }) => ['span', HTMLAttributes, 0],
});

/**
 * <span class="campo-vinculado campo-nome"> e <span class="ref-nossa-referencia">.
 * Prioridade mínima: fica sempre a marca mais interior, pelo que o seu <span> contém só
 * texto — condição de que depende a sincronização do servidor (CamposVinculados).
 */
export const MarcaComClasse = Mark.create({
    name: 'marcaComClasse',
    priority: 1,
    inclusive: false, // escrever junto ao campo não o estende
    addAttributes: () => ({
        class: {
            default: null,
            parseHTML: (el) => el.getAttribute('class'),
            renderHTML: (attrs) => (attrs.class ? { class: attrs.class } : {}),
        },
    }),
    parseHTML: () => [{
        tag: 'span[class]',
        priority: 60,
        getAttrs: (el) => (CLASSES_PRESERVADAS.test(el.getAttribute('class') || '') ? null : false),
    }],
    renderHTML: ({ HTMLAttributes }) => ['span', HTMLAttributes, 0],
});

/** <div style> com texto directo (ex.: bloco "Ao / nome / …", linha do Local). */
export const BlocoTexto = Node.create({
    name: 'blocoTexto',
    group: 'block',
    content: 'inline*',
    defining: true,
    addAttributes: () => atributoStyle(),
    parseHTML: () => [{
        tag: 'div',
        priority: 60,
        getAttrs: (el) => (el.querySelector(BLOCOS) ? false : null),
    }],
    renderHTML: ({ HTMLAttributes }) => ['div', HTMLAttributes, 0],
});

/** <div style> que agrupa blocos (ex.: contentor do modelo, bloco de assinatura). */
export const BlocoContentor = Node.create({
    name: 'blocoContentor',
    group: 'block',
    content: 'block+',
    defining: true,
    addAttributes: () => atributoStyle(),
    parseHTML: () => [{ tag: 'div', priority: 50 }],
    renderHTML: ({ HTMLAttributes }) => ['div', HTMLAttributes, 0],
});

/** style em parágrafos, títulos, tabelas e marcas de ênfase (recuos, larguras, maiúsculas). */
export const EstilosGlobais = Extension.create({
    name: 'estilosGlobais',
    addGlobalAttributes: () => [
        { types: ['paragraph', 'heading'], attributes: atributoStyle(semTextAlign) },
        { types: ['table', 'tableCell', 'tableHeader', 'bold', 'underline', 'italic'], attributes: atributoStyle() },
    ],
});

/** Extensões de conteúdo (sem Collaboration/Cursor, que dependem da sessão). */
export function extensoesDoDocumento() {
    return [
        // History é fornecido pelo Yjs (UndoManager) — desligar o do StarterKit.
        StarterKit.configure({ history: false }),
        // Só <u>: o sublinhado vindo de style="text-decoration" já é preservado por
        // EstiloInline; lê-lo também aqui acrescentava um <u> redundante.
        Underline.extend({ parseHTML: () => [{ tag: 'u' }] }),
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        // Sem redimensionamento: acrescentava <colgroup>/min-width ao HTML dos modelos.
        Table.configure({ resizable: false }),
        TableRow,
        TableHeader,
        TableCell,
        EstiloInline,
        MarcaComClasse,
        BlocoTexto,
        BlocoContentor,
        EstilosGlobais,
    ];
}
