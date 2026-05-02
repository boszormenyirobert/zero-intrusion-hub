<?php

declare(strict_types=1);

namespace App\Tests\Service\Instance;

use App\Entity\OwnClient;
use App\Repository\OwnClientRepository;
use App\Service\Instance\InstanceSettingsService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class InstanceSettingsServiceTest extends TestCase
{
    public function testConstructorDoesNotQueryRepositoryBeforeAccessorIsCalled(): void
    {
        $repository = $this->createMock(OwnClientRepository::class);
        $repository
            ->expects(self::never())
            ->method('findOneBy');

        $service = new InstanceSettingsService($this->createParameterBag(), $repository);

        self::assertSame('https://hub.example.test', $service->getInstanceDomain());
    }

    public function testOwnClientConfigurationIsLoadedLazilyAndCached(): void
    {
        $ownClient = (new OwnClient())
            ->setCorporateId('corporate-id')
            ->setCorporateIdSecret('corporate-secret')
            ->setCorporateIdKey('corporate-key');

        $repository = $this->createMock(OwnClientRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with([], ['id' => 'ASC'])
            ->willReturn($ownClient);

        $service = new InstanceSettingsService($this->createParameterBag(), $repository);

        self::assertSame('corporate-id', $service->getInstancePublicId());
        self::assertSame('corporate-secret', $service->getSecret());
        self::assertSame('corporate-key', $service->getCorporateKey());
    }

    private function createParameterBag(): ContainerBagInterface
    {
        $parameterBag = $this->createMock(ContainerBagInterface::class);
        $parameterBag
            ->method('get')
            ->willReturnMap([
                ['ZERO_INTRUSION_HUB_DOMAIN', 'https://hub.example.test'],
                ['SERVICE_API_KEY', 'service-api-key'],
                ['SERVICE_API_SECRET', 'service-api-secret'],
            ]);

        return $parameterBag;
    }
}