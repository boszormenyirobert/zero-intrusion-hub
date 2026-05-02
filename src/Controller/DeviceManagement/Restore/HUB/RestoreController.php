<?php

/**
 * HUB view.
 * Handles device replacement and recovery processes when a user changes their device
 * or updates their email/phone number.
 *
 * Responsibilities:
 * - First step: collects user email and phone number, sends verification via email/SMS,
 *   and moves the data to the recovery table.
 * - Second step: confirms the user's PIN, generates an identifier payload, and produces
 *   a QR code for frontend use.
 * - Uses ReplaceDeviceService and GenerateQrService to manage recovery workflows.
 * - Forwards requests to the backend via dedicated backend forwarding services.
 */

namespace App\Controller\DeviceManagement\Restore\HUB;

use App\Attribute\PublicRoute;
use App\DTO\ReplaceDeviceDTO;
use App\DTO\ReplaceDevicePinDTO;
use App\Form\ReplaceDevicePinType;
use App\Form\ReplaceDeviceType;
use App\Service\Device\Restore\HUB\RestoreService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\User\BackendForwardingService;

class RestoreController extends AbstractController
{
    public function __construct(
        private RestoreService $restoreService
    ) {
    }

    /*
    * HUB endpoint for the first step in the device replacement process.
    * Retrieves email and phone number from the request payload.
    * Sends verification via email and SMS.
    * Moves the user data to the recovery table.
    */
    #[PublicRoute('Public device-recovery entry route; access is governed by the recovery flow rather than an existing JWT session.')]
    #[Route('/replace-device', name: 'replaceDeviceForm')]
    public function replaceDevice(
        Request $request,
        BackendForwardingService $backendForwardingService
    ): Response {
        $form = $this->createForm(ReplaceDeviceType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var ReplaceDeviceDTO $data */
            $data = $form->getData();

            $this->restoreService->submitReplaceDevice($data, $backendForwardingService);
        }

        return $this->render(
            'views/device/replace.html.twig',
            $this->restoreService->buildReplaceViewData($form->createView())->toArray()
        );
    }


    /*
    * API endpoint for the second step in the device replacement process.
    * Confirms the PIN provided by the user.
    * Returns an identifier payload used by the frontend to generate a QR code.
    */
    #[PublicRoute('Public device-recovery completion route; access is governed by the route-specific recovery hash and PIN flow rather than an existing JWT session.')]
    #[Route('/replace-device/{replaceHash}', name: 'replace_device_pin', methods: ['GET', 'POST'])]
    public function replaceDevicePin(
        string $replaceHash,
        Request $request,
    ): Response {
        $form = $this->createForm(ReplaceDevicePinType::class);
        $form->handleRequest($request);

        $qrData = null;
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var ReplaceDevicePinDTO $pinData */
            $pinData = $form->getData();

            $qrData = $this->restoreService->resolveReplaceDevicePinQrCode($replaceHash, $pinData);
        }

        return $this->render(
            'views/device/replacePin.html.twig',
            $this->restoreService->buildReplacePinViewData($form->createView(), $replaceHash, $qrData)->toArray()
        );
    }
}
