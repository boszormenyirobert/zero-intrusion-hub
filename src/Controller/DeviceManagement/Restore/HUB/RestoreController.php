<?php

/**
 * HUB View
 * Handles device replacement and recovery processes when a user changes their device
 * or updates their email/phone number.
 *
 * Responsibilities:
 * - First step: collects user email and phone number, sends verification via email/SMS,
 *   and moves the data to the recovery table.
 * - Second step: confirms the user's PIN, generates a handy identifier, and produces
 *   a QR code for frontend use.
 * - Uses ReplaceDeviceService and GenerateQrService to manage recovery workflows.
 * - Forwards requests to the backend via UserRegistrationService.
 */

namespace App\Controller\DeviceManagement\Restore\HUB;

use App\Form\ReplaceDevicePinType;
use App\Form\ReplaceDeviceType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\User\UserRegistrationService;
use Psr\Log\LoggerInterface;
use App\Service\Device\ReplaceDeviceService;
use App\Service\Qr\GenerateQrService;

class RestoreController extends AbstractController
{

    public function __construct(
        private ReplaceDeviceService $replaceDeviceService,
        private GenerateQrService $generateQrService,
        private LoggerInterface $logger
    ) {}

    /*
    * HUB endpoint for the first step in the device replacement process.
    * Retrieves email and phone number from the request payload.
    * Sends verification via email and SMS.
    * Moves the user data to the recovery table.
    */
    #[Route('/replace-device', name: 'replaceDeviceForm')]
    public function replaceDevice(
        Request $request,
        UserRegistrationService $userRegistrationService
    ) {
        $form = $this->createForm(ReplaceDeviceType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $process = "replaceDevice";

            /** @var Response $response */
            $response = $userRegistrationService->forwardRegistration(
                [$process => $form->getData()]
            );
        }

        return $this->render('views/device/replace.html.twig', [
            'replace_device' => $form->createView()
        ]);
    }


    /*
    * API endpoint for the second step in the device replacement process.
    * Confirms the PIN provided by the user.
    * Returns a handy identifier used by the frontend to generate a QR code.
    */
    #[Route('/replace-device/{replaceHash}', name: 'replace_device_pin', methods: ['GET', 'POST'])]
    public function replaceDevicePin(
        String $replaceHash,
        Request $request,
        UserRegistrationService $userRegistrationService
    ) {
        $form = $this->createForm(ReplaceDevicePinType::class);
        $form->handleRequest($request);

        $qrData = [];
        if ($form->isSubmitted() && $form->isValid()) {
            $process = "restorePin";

            $content['data'] = $form->getData();
            $content['replaceHash'] = $replaceHash;

            /** @var Response $response */
            $response = $userRegistrationService->forwardRegistration(
                [$process => $content]
            );

            $valid = $this->replaceDeviceService->controllResponse($response);
           
            if ($valid) {
                $qrData = (array)$response;
                $qrData['type'] = 'recovery';
                $qrData['source'] = 'easyPublic';

                $qrData = $this->generateQrService->getQrCode($qrData);
            }
        }

        return $this->render('views/device/replacePin.html.twig', [
            'replace_device_pin' => $form->createView(),
            'replaceHash' => $replaceHash,
            'qrCodeData' => $qrData
        ]);
    }
}
