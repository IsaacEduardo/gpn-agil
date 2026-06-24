<?php

namespace App\Services\Ai;

/**
 * Erro recuperável do serviço de IA (não configurado, falha de rede, erro da API).
 * O controller traduz em resposta amigável (HTTP 503).
 */
class LlmException extends \RuntimeException {}
