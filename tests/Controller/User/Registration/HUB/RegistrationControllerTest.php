<?php

declare(strict_types=1);

namespace App\Tests\Controller\User\Registration\HUB;

use App\Controller\User\Registration\HUB\RegistrationController;
use App\Attribute\PublicRoute;
use App\DTO\MenuAvailabilityDTO;
use App\DTO\QrCodeResponseDTO;
use App\DTO\RegistrationViewDataDTO;
use App\Service\User\Registration\HUB\RegistrationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RegistrationControllerTest extends TestCase
{
    public function testUserRegistrationRouteIsExplicitlyMarkedAsPublic(): void
    {
        $reflectionMethod = new \ReflectionMethod(RegistrationController::class, 'userRegistration');

        self::assertNotEmpty($reflectionMethod->getAttributes(PublicRoute::class));
    }

    public function testUserRegistrationRendersTypedViewData(): void
    {
        $request = Request::create('/user-registration');

        $registrationService = $this->createMock(RegistrationService::class);
        $registrationService
            ->method('buildRegistrationViewData')
            ->with($request)
            ->willReturn(new RegistrationViewDataDTO(
                QrCodeResponseDTO::fromArray(['registrationProcessId' => 'registration-123', 'qrCode' => 'qr-code']),
                true,
                'registration',
                new MenuAvailabilityDTO(true, true, true)
            ));

        $controller = new TestHubRegistrationController();
        $response = $controller->userRegistration($request, $registrationService);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('views/users/user-registration.html.twig', $controller->renderedTemplate);
        self::assertSame('registration-123', $controller->renderedParameters['processId']);
    }
}

final class TestHubRegistrationController extends RegistrationController
{
    public string $renderedTemplate = '';
    public array $renderedParameters = [];

    protected function render(string $view, array $parameters = [], ?Response $response = null): Response
    {
        $this->renderedTemplate = $view;
        $this->renderedParameters = $parameters;

        return $response ?? new Response('rendered');
    }
}