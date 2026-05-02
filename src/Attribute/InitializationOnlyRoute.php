<?php

namespace App\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
class InitializationOnlyRoute
{
    public function __construct(
        public readonly ?string $reason = null,
    ) {
    }
}
