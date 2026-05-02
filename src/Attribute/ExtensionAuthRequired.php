<?php

namespace App\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
class ExtensionAuthRequired
{
    public function __construct(
        public readonly ?string $reason = null,
    ) {
    }
}
