<?php

namespace App\DTO;

class BusinessRequestDTO
{
    private const ALLOWED_BUSINESS_MODELS = [
        'pswManager',
        'biometric',
        'businessBasic',
        'businessPlus',
        'businessPro',
    ];

    public string $businessModel = '';

    public function __construct(string $businessModel = '')
    {
        $this->businessModel = $businessModel;
    }

    public function toArray(): array
    {
        return [
            'businessModel' => $this->businessModel,
        ];
    }

    public static function allowedBusinessModels(): array
    {
        return self::ALLOWED_BUSINESS_MODELS;
    }

    public static function isAllowedBusinessModel(string $businessModel): bool
    {
        return in_array($businessModel, self::ALLOWED_BUSINESS_MODELS, true);
    }
}
