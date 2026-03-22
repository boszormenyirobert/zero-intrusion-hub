<?php

namespace App\Helper;

/**
 * Helper class for common utility functions used across the application.
 * Currently provides static methods for URL/path building
 */
final class UtilityHelper
{

    public static function buildPath(string $domain, string $target, string $endpoint = ""): string
    {
        return rtrim($domain, '/') . '/' . trim($target, '/') . '/' . ltrim($endpoint, '/');
    }
}
