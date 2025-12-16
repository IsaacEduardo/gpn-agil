<?php

namespace App\Enums;

enum TipoRequisicao: string
{
    case PRODUTO = 'produto';
    case OFICINA = 'oficina';
    case SERVICO = 'servico';
    case PASSAGEM = 'passagem';

    public function label(): string
    {
        return match ($this) {
            self::PRODUTO => 'Produto',
            self::OFICINA => 'Oficina',
            self::SERVICO => 'Serviço',
            self::PASSAGEM => 'Passagem',
        };
    }

    public function prefixo(): string
    {
        return match ($this) {
            self::PRODUTO => 'PRO',
            self::OFICINA => 'OFI',
            self::SERVICO => 'SER',
            self::PASSAGEM => 'PAS',
        };
    }
}
