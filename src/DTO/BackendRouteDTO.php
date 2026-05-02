<?php

namespace App\DTO;

use App\Helper\UtilityHelper;

class BackendRouteDTO
{
    public function __construct(
        public string $basePath,
        public string $endpointPath = ''
    ) {
    }

    public function toUrl(string $domain): string
    {
        return UtilityHelper::buildPath($domain, $this->basePath, $this->endpointPath);
    }
}
