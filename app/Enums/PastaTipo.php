<?php

namespace App\Enums;

enum PastaTipo: string
{
    case ENTRADA = 'entrada';
    case ENTRADA_ANO = 'entrada_ano';
    case ENTRADA_MES = 'entrada_mes';
    case SAIDA = 'saida';
    case SAIDA_ANO = 'saida_ano';
    case SAIDA_MES = 'saida_mes';
    case INTERNO = 'interno';
    case INTERNO_DESPACHOS = 'interno_despachos';
    case INTERNO_PARECERES = 'interno_pareceres';
    case OUTRO = 'outro';

    public function label(): string
    {
        return match ($this) {
            self::ENTRADA => 'Correspondência Recebida',
            self::ENTRADA_ANO => 'Ano (Entrada)',
            self::ENTRADA_MES => 'Mês (Entrada)',
            self::SAIDA => 'Correspondência Expedida',
            self::SAIDA_ANO => 'Ano (Saída)',
            self::SAIDA_MES => 'Mês (Saída)',
            self::INTERNO => 'Documentos Internos',
            self::INTERNO_DESPACHOS => 'Despachos',
            self::INTERNO_PARECERES => 'Pareceres',
            self::OUTRO => 'Outro',
        };
    }
}
