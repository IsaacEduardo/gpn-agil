<?php

namespace App\Support;

class SafeFileHeaders
{
    /**
     * Tipos MIME seguros para renderização inline no navegador. Qualquer outro
     * tipo (ex.: text/html disfarçado de PDF) é forçado a download como
     * application/octet-stream para eliminar o vetor de XSS via ficheiros.
     */
    private const INLINE_SAFE = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'text/plain',
    ];

    /**
     * Monta cabeçalhos seguros para servir um ficheiro armazenado.
     *
     * @param  string|null  $mime  MIME registado/detetado (não confiável por si só)
     * @param  string  $filename  Nome apresentado ao utilizador
     * @param  string  $disposition  'inline' ou 'attachment'
     * @return array<string, string>
     */
    public static function for(?string $mime, string $filename, string $disposition = 'inline'): array
    {
        $mime = strtolower(trim((string) $mime));
        $safeName = str_replace(['"', "\r", "\n"], '', $filename);

        if (! in_array($mime, self::INLINE_SAFE, true)) {
            $mime = 'application/octet-stream';
            $disposition = 'attachment';
        }

        return [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition.'; filename="'.$safeName.'"',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
