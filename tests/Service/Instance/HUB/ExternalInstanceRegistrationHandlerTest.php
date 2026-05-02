<?php

declare(strict_types=1);

namespace App\Tests\Service\Instance\HUB;

use App\DTO\BackendPayloadDTO;
use App\Logger\LogTrace;
use App\Service\Corporate\SubscriptionService;
use App\Service\Instance\HUB\ExternalInstanceRegistrationHandler;
use App\Service\JWT\JwtService;
use App\Service\Shared\ProcessKey;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

final class ExternalInstanceRegistrationHandlerTest extends TestCase
{
    public function testHandleReturnsNullWhenFormNotSubmitted(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(false);
        $form->expects(self::never())->method('isValid');

        $subscriptionService = $this->createMock(SubscriptionService::class);
        $subscriptionService->expects(self::never())->method('getSubscriptionData');

        $handler = new ExternalInstanceRegistrationHandler(
            $subscriptionService,
            $this->createMock(JwtService::class),
            $this->createMock(LoggerInterface::class)
        );

        self::assertNull($handler->handle($form, new Request()));
    }

    public function testHandleReturnsNullWhenJwtPayloadMissing(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('extractPayloadFromRequest')->willReturn(null);
        $jwtService->method('extractTokenFromRequest')->willReturn('jwt');

        $subscriptionService = $this->createMock(SubscriptionService::class);
        $subscriptionService->expects(self::never())->method('getSubscriptionData');

        $handler = new ExternalInstanceRegistrationHandler(
            $subscriptionService,
            $jwtService,
            $this->createMock(LoggerInterface::class)
        );

        self::assertNull($handler->handle($form, new Request()));
    }

    public function testHandleReturnsSubscriptionDataForValidSubmittedForm(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);

        $request = Request::create('/instance-registration-external', 'POST');

        $jwtService = $this->createMock(JwtService::class);
        $jwtService->method('extractPayloadFromRequest')->with($request)->willReturn([
            'publicId' => 'public-id',
        ]);
        $jwtService->method('extractTokenFromRequest')->with($request)->willReturn('jwt');

        $subscriptionData = BackendPayloadDTO::fromArray([
            'corporate_id' => 'corp-123',
            'corporate_id_key' => 'key-123',
        ]);

        $subscriptionService = $this->createMock(SubscriptionService::class);
        $subscriptionService
            ->expects(self::once())
            ->method('getSubscriptionData')
            ->with(ProcessKey::GET_IDENTITY, 'businessPro', 'external', 'public-id')
            ->willReturn($subscriptionData);

        $logger = new class extends AbstractLogger {
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => $level,
                    'message' => (string) $message,
                    'context' => $context,
                ];
            }
        };

        $handler = new ExternalInstanceRegistrationHandler(
            $subscriptionService,
            $jwtService,
            $logger
        );

        self::assertSame($subscriptionData, $handler->handle($form, $request));
        self::assertCount(2, $logger->records);
        self::assertSame('External HUB instance registration identity received', $logger->records[1]['message']);
        self::assertSame([
            'process' => ProcessKey::GET_IDENTITY,
            'business_model' => 'businessPro',
            'public_id_hash' => LogTrace::fingerprint('public-id'),
            'response_keys' => $subscriptionData->keys(),
        ], $logger->records[1]['context']);
        self::assertArrayNotHasKey('public_id', $logger->records[1]['context']);
    }
}
