<?php

namespace App\Services\Ai;

/**
 * Cliente de IA de demonstração/teste. Não faz chamadas externas nem tem custo.
 * Útil para validar a UI/integração localmente (ASSISTENTE_DRIVER=fake) e como base em testes.
 * Devolve uma resposta determinística que ecoa a última pergunta do utilizador.
 */
class FakeLlmClient implements LlmClient
{
    public function isConfigured(): bool
    {
        return true;
    }

    public function chat(string $system, array $messages, array $options = []): string
    {
        if (str_contains($system, 'JSON')) {
            return json_encode([
                'classificacao_sugerida' => 'Ofício Técnico',
                'assunto_resumido' => 'Assunto extraído automaticamente (Modo de Demonstração)',
                'prioridade' => 'alta',
                'encaminhar_para_departamento_id' => null,
                'justificativa_encaminhamento' => 'Recomendado encaminhar para análise técnica complementar no departamento sugerido.',
                'tarefas_sugeridas' => [
                    [
                        'titulo' => 'Análise técnica da solicitação',
                        'descricao' => 'Proceder com a verificação de conformidade do relatório de atividades e fatura.',
                        'assigned_to_user_id' => null,
                        'prazo_dias_sugerido' => 3
                    ],
                    [
                        'titulo' => 'Preparar despacho conclusivo',
                        'descricao' => 'Elaborar minuta de despacho com a resposta oficial do gabinete.',
                        'assigned_to_user_id' => null,
                        'prazo_dias_sugerido' => 5
                    ]
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        $ultima = '';
        foreach (array_reverse($messages) as $m) {
            if (($m['role'] ?? '') === 'user') {
                $ultima = trim((string) ($m['content'] ?? ''));
                break;
            }
        }

        return '[Modo de demonstração] Com base no conteúdo fornecido, esta é uma resposta de exemplo para: "'
            .$ultima.'". (Assistente em modo de teste — sem custos e sem chamadas externas.)';
    }
}
