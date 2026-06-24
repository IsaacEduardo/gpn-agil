# Guia de Estilo e Padrões de Interface (GPN-AGIL)

Este documento define os padrões visuais e de usabilidade para o desenvolvimento de novas interfaces no sistema GPN-AGIL, garantindo consistência, acessibilidade e uma experiência de usuário profissional.

## 1. Princípios de Design
- **Simplicidade**: Interfaces limpas, focadas na tarefa principal.
- **Feedback Imediato**: O sistema deve reagir instantaneamente a ações do usuário (cliques, salvamentos, erros).
- **Acessibilidade**: Conformidade com WCAG 2.1 AA (contraste, navegação por teclado, leitores de tela).
- **Responsividade**: Funcionalidade total em Desktop, Tablet e Mobile.

## 2. Cores e Paleta
Utilizar as classes do Bootstrap 5 estendidas.

| Contexto | Cor (Hex) | Classe Bootstrap | Uso |
| :--- | :--- | :--- | :--- |
| **Primária** | `#0d6efd` (Azul) | `text-primary`, `bg-primary` | Ações principais, links, títulos de destaque. |
| **Secundária** | `#6c757d` (Cinza) | `text-secondary`, `bg-secondary` | Ações secundárias, bordas, textos de apoio. |
| **Sucesso** | `#198754` (Verde) | `text-success`, `bg-success` | Conclusão, aprovação, status positivo. |
| **Alerta** | `#ffc107` (Amarelo) | `text-warning`, `bg-warning` | Avisos, pendências, atenção requerida. |
| **Perigo** | `#dc3545` (Vermelho) | `text-danger`, `bg-danger` | Erros, exclusão, ações destrutivas. |
| **Fundo** | `#f8f9fa` (Off-white)| `bg-light` | Fundo de páginas, áreas de agrupamento. |

## 3. Tipografia
- **Fonte Principal**: Padrão do sistema (San Francisco, Segoe UI, Roboto, Helvetica Neue).
- **Títulos**: `fw-bold` para ênfase.
- **Corpo**: Tamanho base `1rem` (16px).
- **Texto Auxiliar**: `text-muted` e `small` para metadados (datas, autores).

## 4. Componentes de Interface

### 4.1. Cartões (Cards)
Utilizados para agrupar informações relacionadas (ex: Tarefas no Grid/Kanban).
```html
<div class="card border-0 shadow-sm hover-lift">
    <div class="card-body p-3">
        <h6 class="fw-bold">Título do Cartão</h6>
        <p class="text-muted small">Conteúdo descritivo...</p>
    </div>
</div>
```
*Atributos*: `border-0`, `shadow-sm`, efeito `hover-lift` para interatividade.

### 4.2. Tabelas (List Views)
Para listagens densas de dados.
```html
<table class="table table-hover align-middle">
    <thead class="bg-light">...</thead>
    <tbody>...</tbody>
</table>
```
*Atributos*: `table-hover` para destacar a linha sob o cursor, `align-middle` para centralização vertical.

### 4.3. Botões e Ações
- **Principal**: `btn btn-primary shadow-sm`.
- **Secundário**: `btn btn-outline-secondary`.
- **Ícones**: FontAwesome 5 (`<i class="fas fa-check"></i>`). Sempre adicionar margem (`me-1` ou `me-2`) se houver texto.

### 4.4. Kanban & Drag-and-Drop
- Colunas com fundo leve (`bg-light`) e bordas arredondadas (`rounded-3`).
- Itens arrastáveis devem ter cursor indicativo (`cursor-grab`).
- Feedback visual ao soltar (animação ou mudança de opacidade).

## 5. JavaScript e Interatividade
- **Framework**: Alpine.js para interações leves (toggles, modais, abas).
- **Requisições**: Fetch API ou Axios.
- **Padrão de AJAX**:
    - Sempre enviar `X-CSRF-TOKEN`.
    - Enviar `Accept: application/json` para tratamento correto de erros.
    - Usar `async/await` para clareza.
    - Fornecer feedback de "Loading" ou desabilitar botões durante o processamento.

## 6. CSS Utilitários (Custom)
Adicionados em `app.css` ou bloco `<style>` específico:
- `.hover-lift`: Eleva o elemento e intensifica a sombra ao passar o mouse.
- `.fade-in`: Animação suave de entrada.
- `.cursor-grab`: Indica elemento arrastável.

## 7. Estrutura de Diretórios (Views)
- `resources/views/{modulo}/index.blade.php`: Listagem principal.
- `resources/views/{modulo}/create.blade.php`: Formulário de criação.
- `resources/views/{modulo}/show.blade.php`: Detalhes.
- `partials/`: Fragmentos reutilizáveis (modais, toolbars).

---
*Atualizado em: 01/02/2026*
