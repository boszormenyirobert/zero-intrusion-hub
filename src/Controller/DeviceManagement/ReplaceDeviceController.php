<?php

/**
 * Device replacement and recovery process => The user have the device. E-mail or Phone-number changed
 */

namespace App\Controller\DeviceManagement;

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

class ReplaceDeviceController extends AbstractController
{

    public function __construct(
        private ReplaceDeviceService $replaceDeviceService,
        private GenerateQrService $generateQrService,
        private LoggerInterface $logger
    ) {}

    /** 
     * 
     * First step in the device replacement process.
     * Retrive email and phone number from the request payload,
     * Send email and SMS
     * Move the data to the recovery table
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


    /** 
     * 
     * Second step in the device replacement process.
     * Pin confirmation 
     * Return with a handy identifier // FE generates a QR code
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
