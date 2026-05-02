<?php

namespace App\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
class InitializationOrJwtRoute
{
    public function __construct(
        public readonly ?string $reason = null,
    ) {
    }
}
