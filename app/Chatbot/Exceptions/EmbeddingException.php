<?php

namespace App\Chatbot\Exceptions;

/**
 * Erro recuperável ao gerar embeddings (provedor não configurado, rede, API).
 */
class EmbeddingException extends \RuntimeException {}
