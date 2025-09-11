<?php

namespace App\Helper;

final class UtilityHelper
{

    /**
     * Simple URL builder
     */
    public static function buildPath(string $domain, string $target, string $endpoint = ""): string
    {
        return rtrim($domain, '/') . '/' . trim($target, '/') . '/' . ltrim($endpoint, '/');
    }
}
