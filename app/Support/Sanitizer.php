<?php

namespace App\Support;

use App\Services\HtmlSanitizer;

class Sanitizer
{
    public static function clean(?string $html): string
    {
        return (new HtmlSanitizer)->sanitize($html);
    }
}
