<?php
/*
 * HUB instance
 * Task: Home page for the HUB instance
 */
namespace App\Controller\Instance\HUB;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class InstanceController extends AbstractController
{
    public function __construct(
        private InstanceService $instanceService,
    ) {}

    /*
    * Home page for the HUB instance, shows the Instance Registration process if the .env variable 
    * ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION is set to true.
    * If JWT token is present in cookies, decodes it to check if it's valid and passes this info to the template.
    */
    #[Route('/', name: 'home')]
    public function home(        
        Request $request
    ): Response
    {
        return $this->render(
            'views/containers/container-home.html.twig',
            $this->instanceService->buildHomeViewData(
                $request,
                (bool) $this->getParameter('ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION')
            )
        );
    } 
}