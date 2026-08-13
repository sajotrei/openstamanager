<?php

namespace Plugins\ExportFE\Providers;

class Base64Document
{
    public static function decode(string $content): string
    {
        $decoded = base64_decode(trim($content), true);

        if ($decoded === false || $decoded === '') {
            throw new \InvalidArgumentException(tr('Documento Base64 non valido'));
        }

        return $decoded;
    }
}
