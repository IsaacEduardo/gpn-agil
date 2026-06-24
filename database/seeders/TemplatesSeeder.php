<?php

namespace Database\Seeders;

use App\Models\DocumentoEspecie;
use App\Models\ModeloDocumento;
use App\Models\User;
use Illuminate\Database\Seeder;

class TemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first() ?? User::factory()->create();

        $templates = [
            'Ofício' => [
                'nome' => 'Modelo Institucional de Ofício',
                'campos_dinamicos' => null,
                'conteudo' => '
<div style="font-family: \'Times New Roman\', serif; font-size: 12pt; line-height: 1.5; color: #000;">
    <br>

    <div style="float: right; width: 50%; text-align: left;">
        <strong>OFÍCIO Nº _____ /{{DEPARTAMENTO_SIGLA}}/GPN/{{ANO}}</strong><br>
        <strong>Assunto:</strong> {{ASSUNTO}}
    </div>
    <div style="clear: both;"></div>

    <br><br>

    <div style="margin-left: 50px;">
        <strong>Ao Sr(a).</strong><br>
        {{DESTINATARIO_NOME}}<br>
        {{DESTINATARIO_CARGO}}<br>
        {{DESTINATARIO_ORGAO}}<br>
        <u>{{DESTINATARIO_LOCAL}}</u>
    </div>

    <br><br>

    <p><strong>Excelência,</strong></p>

    <p>Com os nossos melhores cumprimentos.</p>

    <p>Serve o presente para informar/solicitar...</p>

    <p>[Escreva aqui o corpo do ofício...]</p>

    <br>

    <p>Sem outro assunto de momento, reiteramos os nossos protestos de elevada estima e consideração.</p>

    <br><br><br>

    <div style="text-align: center;">
        <p><strong>O(A) CHEFE DE DEPARTAMENTO</strong></p>
        <br><br>
        _____________________________________________<br>
        <strong>{{RESPONSAVEL_NOME}}</strong>
    </div>
</div>',
            ],
            'Memorando' => [
                'nome' => 'Modelo Institucional de Memorando',
                'campos_dinamicos' => null,
                'conteudo' => '
<div style="font-family: \'Times New Roman\', serif; font-size: 12pt; line-height: 1.5; color: #000;">
    <p style="text-align: center; font-weight: bold; text-transform: uppercase;">
        MEMORANDO
    </p>

    <hr style="border: 1px solid #000;">

    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="width: 15%; font-weight: bold;">PARA:</td>
            <td>{{DESTINATARIO_NOME}} - {{DESTINATARIO_CARGO}}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">DE:</td>
            <td>{{USUARIO_NOME}} - {{DEPARTAMENTO_NOME}}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">DATA:</td>
            <td>{{DATA_EXTENSO}}</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">ASSUNTO:</td>
            <td>{{ASSUNTO}}</td>
        </tr>
    </table>

    <hr style="border: 1px solid #000;">

    <br>

    <p>Prezado(a) Senhor(a),</p>

    <p>[Escreva aqui o conteúdo do memorando...]</p>

    <br><br>

    <p>Atenciosamente,</p>

    <br><br>

    <div style="text-align: left;">
        _____________________________________________<br>
        <strong>{{RESPONSAVEL_NOME}}</strong>
    </div>
</div>',
            ],
            'Circular' => [
                'nome' => 'Modelo Institucional de Circular',
                'campos_dinamicos' => null,
                'conteudo' => '
<div style="font-family: \'Times New Roman\', serif; font-size: 12pt; line-height: 1.5; color: #000;">
    <p style="text-align: center; font-weight: bold; text-transform: uppercase;">
        CIRCULAR Nº _____ /{{ANO}}
    </p>

    <br>

    <p><strong>DATA:</strong> {{DATA_EXTENSO}}</p>
    <p><strong>ASSUNTO:</strong> {{ASSUNTO}}</p>
    <p><strong>PARA:</strong> Todos os Funcionários / Departamentos</p>

    <hr style="border: 1px solid #000;">

    <br>

    <p>Levamos ao conhecimento de todos os colaboradores que...</p>

    <p>[Escreva aqui o conteúdo da circular...]</p>

    <br>

    <p>Contamos com a colaboração de todos.</p>

    <br><br>

    <div style="text-align: center;">
        <p><strong>A DIREÇÃO</strong></p>
        <br><br>
        _____________________________________________<br>
        <strong>{{RESPONSAVEL_NOME}}</strong><br>
        {{DEPARTAMENTO_NOME}}
    </div>
</div>',
            ],
            'Despacho' => [
                'nome' => 'Modelo Institucional de Despacho',
                'campos_dinamicos' => ['decisao' => 'select:Aprovado,Rejeitado,Arquivar', 'observacoes' => 'textarea'],
                'conteudo' => '
<div style="font-family: \'Times New Roman\', serif; font-size: 12pt; line-height: 1.5; color: #000;">
    <div style="border: 2px solid #000; padding: 20px;">
        <p style="text-align: center; font-weight: bold; text-transform: uppercase; text-decoration: underline;">
            DESPACHO
        </p>

        <br>

        <p><strong>REFERÊNCIA:</strong> Documento de Entrada nº {{DOCUMENTO_ORIGEM_NUMERO}}</p>
        <p><strong>ASSUNTO:</strong> {{DOCUMENTO_ORIGEM_ASSUNTO}}</p>
        <p><strong>DATA:</strong> {{DATA_ATUAL}}</p>

        <br>
        <hr>
        <br>

        <p>Considerando o exposto no documento supracitado, determino:</p>

        <p><strong>DECISÃO:</strong> {{DECISAO}}</p>

        <br>

        <p><strong>Observações/Instruções:</strong></p>
        <p>{{OBSERVACOES}}</p>

        <br><br><br>

        <div style="text-align: center;">
            _____________________________________________<br>
            <strong>{{RESPONSAVEL_NOME}}</strong>
        </div>
    </div>
</div>',
            ],
            'Relatório' => [
                'nome' => 'Modelo Institucional de Relatório',
                'campos_dinamicos' => null,
                'conteudo' => '
<div style="font-family: \'Times New Roman\', serif; font-size: 12pt; line-height: 1.5; color: #000;">
    <p style="text-align: center; font-weight: bold; text-transform: uppercase;">
        RELATÓRIO TÉCNICO
    </p>

    <br>

    <p><strong>TÍTULO:</strong> {{ASSUNTO}}</p>
    <p><strong>DATA:</strong> {{DATA_EXTENSO}}</p>
    <p><strong>ELABORADO POR:</strong> {{USUARIO_NOME}}</p>

    <br><hr><br>

    <h3 style="text-transform: uppercase;">1. Introdução</h3>
    <p>[Descreva aqui o objetivo do relatório e o contexto...]</p>

    <h3 style="text-transform: uppercase;">2. Desenvolvimento</h3>
    <p>[Apresente aqui os fatos, dados analisados e atividades realizadas...]</p>

    <h3 style="text-transform: uppercase;">3. Conclusão</h3>
    <p>[Apresente as conclusões e recomendações...]</p>

    <br><br>

    <div style="text-align: right;">
        Moçâmedes, {{DATA_EXTENSO}}
    </div>

    <br><br>

    <div style="text-align: center;">
        _____________________________________________<br>
        <strong>{{RESPONSAVEL_NOME}}</strong><br>
        {{DEPARTAMENTO_NOME}}
    </div>
</div>',
            ],
            'Acta' => [
                'nome' => 'Modelo Institucional de Acta',
                'campos_dinamicos' => ['hora_inicio' => 'time', 'ordem_trabalhos' => 'textarea'],
                'conteudo' => '
<div style="font-family: \'Times New Roman\', serif; font-size: 12pt; line-height: 1.5; color: #000; text-align: justify;">
    <p style="text-align: center; font-weight: bold; text-transform: uppercase;">
        ACTA Nº _____ /{{ANO}}
    </p>

    <br>

    <p>Aos {{DATA_EXTENSO}}, pelas {{HORA_INICIO}} horas, reuniu-se na sala de reuniões do(a) {{DEPARTAMENTO_NOME}}, sob a presidência do Sr(a). {{USUARIO_NOME}}, com a seguinte ordem de trabalhos:</p>

    <p>{{ORDEM_TRABALHOS}}</p>

    <br>

    <h4 style="text-transform: uppercase;">Desenvolvimento dos Trabalhos</h4>
    <p>Iniciada a sessão, o Presidente cumprimentou os presentes e passou à leitura da ordem do dia...</p>
    <p>[Descrever o desenrolar da reunião...]</p>

    <br>

    <h4 style="text-transform: uppercase;">Deliberações</h4>
    <p>Após discussão, foram tomadas as seguintes deliberações:</p>
    <ul>
        <li>[Deliberação 1]</li>
        <li>[Deliberação 2]</li>
    </ul>

    <br>

    <p>Nada mais havendo a tratar, deu-se por encerrada a reunião, da qual se lavrou a presente acta que vai ser assinada por todos os presentes.</p>

    <br><br>

    <div style="text-align: center;">
        <p>O Presidente</p>
        <br>
        ___________________________<br>
        ({{USUARIO_NOME}})
    </div>
</div>',
            ],
            'Requerimento' => [
                'nome' => 'Modelo Institucional de Requerimento',
                'campos_dinamicos' => null,
                'conteudo' => '
<div style="font-family: \'Times New Roman\', serif; font-size: 12pt; line-height: 1.5; color: #000;">
    <p style="text-align: right;">
        <strong>Exmo. Sr.<br>
        {{DESTINATARIO_CARGO}}<br>
        {{DESTINATARIO_ORGAO}}</strong>
    </p>

    <br><br>

    <p style="text-align: justify;">
        <strong>{{USUARIO_NOME}}</strong>, funcionário afecto ao {{DEPARTAMENTO_NOME}}, vem mui respeitosamente requerer a V. Excia se digne autorizar...
    </p>

    <p style="text-align: justify;">
        [Descreva aqui o objeto do requerimento, fundamentando o pedido...]
    </p>

    <br>

    <p>Pede Deferimento,</p>

    <br>

    <p>{{DESTINATARIO_LOCAL}}, {{DATA_EXTENSO}}</p>

    <br><br><br>

    <div style="text-align: center;">
        _____________________________________________<br>
        <strong>{{USUARIO_NOME}}</strong>
    </div>
</div>',
            ],
            'Nota' => [
                'nome' => 'Parecer Secretaria Geral DLP',
                'campos_dinamicos' => null,
                'conteudo' => '
<div style="font-family: \'Times New Roman\', serif; font-size: 12pt; line-height: 1.5; color: #000;">
    <br>

    <div style="margin-left: 50%; text-align: left;">
        <strong>AO<br>
        EXMO. SR.<br>
        {{DESTINATARIO_NOME}}<br>
        {{DESTINATARIO_CARGO}}<br>
        <span style="text-decoration: underline;">{{DESTINATARIO_LOCAL}}</span></strong>
    </div>

    <br><br>

    <p><strong>NOTA N.º _____ /08.03.05/00-33/GPN/SG/DLPT/{{ANO}}</strong></p>
    <p><strong>ASSUNTO: {{ASSUNTO}}</strong></p>

    <br>

    <p><strong>Exmo. Senhor,</strong></p>

    <p style="text-align: justify;">
        Acusamos a recepção da V/Nota.º _____/_____, de {{DATA_EXTENSO}}, em que é solicitado o nosso pronunciamento em torno do pedido de...
    </p>

    <p style="text-align: justify;">
        [Escreva aqui o conteúdo da nota...]
    </p>

    <p style="text-align: justify;">
        Sobre o assunto, somos de parecer que...
    </p>

    <br>

    <p style="text-align: justify;">
        Sem outro assunto de momento, digne-se aceitar a nossa reiterada expressão de respeito e consideração.
    </p>

    <br><br>

    <div style="text-align: center;">
        <strong>SECRETARIA GERAL DO GOVERNO PROVINCIAL DO NAMIBE</strong>, em Moçâmedes, {{DATA_EXTENSO}}.
        <br><br>
        O Secretário Geral
        <br><br><br>
        <strong>{{USUARIO_NOME}}</strong>
    </div>
</div>',
            ],
            'Parecer' => [
                'nome' => 'Modelo de Parecer Jurídico',
                'campos_dinamicos' => ['processo_numero' => 'text', 'interessado' => 'text'],
                'conteudo' => '
<div style="font-family: \'Times New Roman\', serif; font-size: 12pt; line-height: 1.5; color: #000;">
    <p style="text-align: center; font-weight: bold; text-transform: uppercase;">
        PARECER JURÍDICO Nº _____ /{{ANO}}
    </p>

    <br>

    <p><strong>ASSUNTO:</strong> {{ASSUNTO}}</p>
    <p><strong>PROCESSO Nº:</strong> {{PROCESSO_NUMERO}}</p>
    <p><strong>INTERESSADO:</strong> {{INTERESSADO}}</p>

    <hr>

    <h4 style="text-transform: uppercase;">I - RELATÓRIO</h4>
    <p>[Descrever os fatos...]</p>

    <h4 style="text-transform: uppercase;">II - FUNDAMENTAÇÃO LEGAL</h4>
    <p>[Análise jurídica...]</p>

    <h4 style="text-transform: uppercase;">III - CONCLUSÃO</h4>
    <p>[Parecer final...]</p>

    <br><br>

    <div style="text-align: center;">
        _____________________________________________<br>
        <strong>{{USUARIO_NOME}}</strong><br>
        Jurista
    </div>
</div>',
            ],
            'Guia de Marcha' => [
                'nome' => 'Guia de Marcha',
                'campos_dinamicos' => ['destino' => 'text', 'motivo' => 'text', 'data_partida' => 'date', 'data_regresso' => 'date'],
                'conteudo' => '
<div style="font-family: \'Times New Roman\', serif; font-size: 12pt; line-height: 1.5; color: #000;">
    <p style="text-align: center; font-weight: bold; text-transform: uppercase;">
        GUIA DE MARCHA
    </p>

    <br><br>

    <p>O(A) funcionário(a) <strong>{{USUARIO_NOME}}</strong>, afecto ao {{DEPARTAMENTO_NOME}}, segue viagem para:</p>

    <p><strong>DESTINO:</strong> {{DESTINO}}</p>
    <p><strong>MOTIVO:</strong> {{MOTIVO}}</p>

    <br>

    <p><strong>DATA DE PARTIDA:</strong> {{DATA_PARTIDA}}</p>
    <p><strong>DATA DE REGRESSO:</strong> {{DATA_REGRESSO}}</p>

    <br><br>

    <div style="text-align: center;">
        O Responsável
        <br><br>
        __________________________________<br>
        <strong>{{RESPONSAVEL_NOME}}</strong>
    </div>
</div>',
            ],
            'Guia de Férias' => [
                'nome' => 'Guia de Férias',
                'campos_dinamicos' => ['ano_referente' => 'number', 'dias_gozo' => 'number', 'data_inicio' => 'date', 'data_fim' => 'date'],
                'conteudo' => '
<div style="font-family: \'Times New Roman\', serif; font-size: 12pt; line-height: 1.5; color: #000;">
    <p style="text-align: center; font-weight: bold; text-transform: uppercase;">
        GUIA DE FÉRIAS
    </p>

    <br>

    <p>Autoriza-se o(a) funcionário(a) <strong>{{USUARIO_NOME}}</strong> a gozar as suas férias referentes ao ano de {{ANO_REFERENTE}}.</p>

    <p><strong>DIAS DE GOZO:</strong> {{DIAS_GOZO}} dias</p>
    <p><strong>PERÍODO:</strong> De {{DATA_INICIO}} a {{DATA_FIM}}</p>

    <br>

    <p>Deve apresentar-se ao serviço no dia útil seguinte ao término das férias.</p>

    <br><br>

    <div style="text-align: center;">
        O Diretor de Recursos Humanos
        <br><br>
        __________________________________<br>
        <strong>{{RESPONSAVEL_NOME}}</strong>
    </div>
</div>',
            ],
            'Guia de Apresentação' => [
                'nome' => 'Guia de Apresentação',
                'campos_dinamicos' => ['apresentado_nome' => 'text', 'motivo_apresentacao' => 'text'],
                'conteudo' => '
<div style="font-family: \'Times New Roman\', serif; font-size: 12pt; line-height: 1.5; color: #000;">
    <p style="text-align: center; font-weight: bold; text-transform: uppercase;">
        GUIA DE APRESENTAÇÃO
    </p>

    <br>

    <p>Ao<br>{{DESTINATARIO_ORGAO}}</p>

    <br>

    <p>Apresentamos o(a) Sr(a). <strong>{{APRESENTADO_NOME}}</strong>, a fim de tratar do seguinte assunto:</p>

    <p><strong>{{MOTIVO_APRESENTACAO}}</strong></p>

    <br><br>

    <p>Agradecemos a atenção dispensada.</p>

    <br><br>

    <div style="text-align: center;">
        O Chefe de Departamento
        <br><br>
        __________________________________<br>
        {{USUARIO_NOME}}
    </div>
</div>',
            ],
            'Declaração de Vaga' => [
                'nome' => 'Declaração de Existência de Vaga',
                'campos_dinamicos' => ['categoria_funcional' => 'text'],
                'conteudo' => '
<div style="font-family: \'Times New Roman\', serif; font-size: 12pt; line-height: 1.5; color: #000;">
    <p style="text-align: center; font-weight: bold; text-transform: uppercase;">
        DECLARAÇÃO
    </p>

    <br><br>

    <p>Para os devidos efeitos, declara-se que existe vaga no Quadro de Pessoal desta Instituição, na categoria de <strong>{{CATEGORIA_FUNCIONAL}}</strong>, que pode ser preenchida pelo(a) Sr(a). <strong>{{DESTINATARIO_NOME}}</strong>.</p>

    <br>

    <p>Por ser verdade e me ter sido solicitado, mandei passar a presente declaração que vai por mim assinada e autenticada com o carimbo a óleo em uso nesta instituição.</p>

    <br><br>

    <div style="text-align: center;">
        O Responsável
        <br><br>
        __________________________________<br>
        <strong>{{RESPONSAVEL_NOME}}</strong>
    </div>
</div>',
            ],
            'Declaração de Salário' => [
                'nome' => 'Declaração de Rendimentos',
                'campos_dinamicos' => ['salario_base' => 'number', 'subsidios' => 'number'],
                'conteudo' => '
<div style="font-family: \'Times New Roman\', serif; font-size: 12pt; line-height: 1.5; color: #000;">
    <p style="text-align: center; font-weight: bold; text-transform: uppercase;">
        DECLARAÇÃO DE RENDIMENTOS
    </p>

    <br><br>

    <p>Declara-se que <strong>{{USUARIO_NOME}}</strong>, funcionário desta instituição, aufere mensalmente os seguintes rendimentos:</p>

    <table border="1" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 5px;">Salário Base</td>
            <td style="padding: 5px; text-align: right;">{{SALARIO_BASE}} Kz</td>
        </tr>
        <tr>
            <td style="padding: 5px;">Subsídios</td>
            <td style="padding: 5px; text-align: right;">{{SUBSIDIOS}} Kz</td>
        </tr>
        <tr>
            <td style="padding: 5px; font-weight: bold;">TOTAL</td>
            <td style="padding: 5px; text-align: right; font-weight: bold;">... Kz</td>
        </tr>
    </table>

    <br>

    <p>A presente declaração destina-se a fins bancários/administrativos.</p>

    <br><br>

    <div style="text-align: center;">
        O Responsável Financeiro
        <br><br>
        __________________________________
    </div>
</div>',
            ],
            'Mapa' => [
                'nome' => 'Mapa Estatístico Genérico',
                'campos_dinamicos' => ['mes_referencia' => 'text'],
                'conteudo' => '
<div style="font-family: \'Times New Roman\', serif; font-size: 12pt; line-height: 1.5; color: #000;">
    <p style="text-align: center; font-weight: bold; text-transform: uppercase;">
        MAPA ESTATÍSTICO - {{MES_REFERENCIA}}
    </p>

    <br>

    <table border="1" style="width: 100%; border-collapse: collapse; text-align: center;">
        <tr style="background-color: #f0f0f0;">
            <th>Designação</th>
            <th>Quantidade</th>
            <th>Observação</th>
        </tr>
        <tr>
            <td>...</td>
            <td>...</td>
            <td>...</td>
        </tr>
        <tr>
            <td>...</td>
            <td>...</td>
            <td>...</td>
        </tr>
    </table>

    <br><br>

    <div style="text-align: center;">
        O Técnico Responsável
        <br><br>
        __________________________________<br>
        {{USUARIO_NOME}}
    </div>
</div>',
            ],
        ];

        foreach ($templates as $especieNome => $data) {
            $especie = DocumentoEspecie::firstOrCreate(['nome' => $especieNome]);

            ModeloDocumento::updateOrCreate(
                [
                    'nome' => $data['nome'],
                    'documento_especie_id' => $especie->id,
                    'gabinete_id' => null, // Templates padrão (globais)
                ],
                [
                    'conteudo' => $data['conteudo'],
                    'campos_dinamicos' => $data['campos_dinamicos'],
                    'ativo' => true,
                    'user_id' => $admin->id,
                ]
            );
        }
    }
}
