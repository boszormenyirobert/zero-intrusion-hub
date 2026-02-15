<?php

namespace App\Controller\User\Login\HUB;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\User\UserService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Cookie;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class LoginController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private UserService $userService
    ) {}

    /**
     * Called from the HUB FE to get a QR code for login
     * The userPublicId is optional and can be used to notify a specific user via firebase for auto login
     * Firebase notification is handled in the API 
     */
    #[Route('/user-login', name: 'instance_login', methods: ["GET","POST"] )]
    public function login(
        CsrfTokenManagerInterface $csrfTokenManager, 
        Request $request
        ) {  

    $token = $csrfTokenManager->getToken('userLoginCsrf')->getValue();

    $oneTouchUsers = [];
    $form = null;

    // 1️⃣ oneTouchUsers mindig POST-ból vagy hidden mezőből jön
    if ($request->isMethod('POST')) {
        if ($request->request->has('oneTouchUsers')) {
            $oneTouchUsersJson = $request->request->get('oneTouchUsers');
            $oneTouchUsers = json_decode($oneTouchUsersJson, true);
        } elseif ($request->request->has('oneTouchUsersHidden')) {
            $oneTouchUsersJson = $request->request->get('oneTouchUsersHidden');
            $oneTouchUsers = json_decode($oneTouchUsersJson, true);
        } elseif ($request->request->has('form')) {
            $formData = $request->request->all('form');
            if (isset($formData['oneTouchUsersHidden'])) {
                $oneTouchUsers = json_decode($formData['oneTouchUsersHidden'], true) ?? [];
            }
        }
    }

    // Dropdown choices előkészítése
    $choices = [];
    foreach ($oneTouchUsers as $user) {
        if (isset($user['email'], $user['userPublicId'])) {
            $choices[$user['email']] = $user['userPublicId'];
        }
    }

    // 2️⃣ Form létrehozása mindig

    $formBuilder = $this->createFormBuilder(null, [
        'csrf_protection' => true,
    ]);
    $formBuilder
        ->add('selectedUser', ChoiceType::class, [
            'choices' => $choices,
            'placeholder' => 'Select a user',
            'required' => true,
        ]);
    // Hidden mező mindig legyen POST submitnál
    $formBuilder->add('oneTouchUsersHidden', \Symfony\Component\Form\Extension\Core\Type\HiddenType::class, [
        'data' => json_encode($oneTouchUsers),
        'mapped' => false,
    ]);
    $form = $formBuilder->getForm();
    $userPublicId = null;


    // 3️⃣ Handle request előtt: csak akkor dd, ha form submit (selectedUser is van)
    if ($request->isMethod('POST') && $request->request->has('selectedUser')) {       
         $userPublicId = $form->get('selectedUser')->getData();
    }
    $form->handleRequest($request);

    // 4️⃣ Második POST: Twig form submit
    $authentication = $this->userService->getQrCode('user_login', [],  $request->request->get('oneTouchUsers'));
    
    if ($form->isSubmitted() && $form->isValid()) {
        $data = $form->get('selectedUser')->getData();        

        return $this->redirectToRoute('instance_login', ['domainProcessId' =>$authentication['domainProcessId']]);       
    }

        $response = $this->render('views/users/user-login.html.twig', [
            'authentication' => $authentication,
            'userLoginCsrf' => $token,
            'menuItem_instanceRegistration' => (bool)$this->getParameter('ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION'),
            'oneTouchUsers' => $oneTouchUsers,
            'form' => $form->createView(),
            'userPublicId' => $userPublicId
        ]);

        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    // Pollling database to check if user confirmed the login via JS
    #[Route('/user-login/check', name: 'user_login_check', methods: "GET")]
    public function userJSCheck(
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserRepository $userRepository,
        JWTTokenManagerInterface $jwtManager
    )
    {
        $processId = $request->query->get('processId');
        $user = $userRepository->findOneBy([
            'process' => $processId
        ]);
       
        if($user && $user->isAllowed()){            
            $token = $jwtManager->create($user);
            $response = new JsonResponse([
                'message' => 'Authentication is success',
                'jwt_token' => $token
            ]);

            $cookie = new Cookie(
                'jwt_token',
                $token,
                time() + 3600, // expire in 1h
                '/',
                null,
                false,  // secure (set to true on HTTPS)
                true,   // httpOnly
                false,
                'Strict'
            );

            $response = $this->json([
                'message' => 'Authentication success.'
            ]);

            $response->headers->setCookie($cookie);

            return $response;
        }

        return $this->json([
            'message' => 'Waiting for authentication.'
        ], 200);
    }

    // Logout user and clear JWT cookie clicked on logout link
    #[Route('/user-logout', name: 'instance_logout', methods: "GET")]
    public function logout(CsrfTokenManagerInterface $csrfTokenManager) {       
        $csrfTokenManager->removeToken('userLoginCsrf');
    
        $response = new Response();
        $response->headers->clearCookie('jwt_token');

        $html = $this->renderView('views/users/user-logged-out.html.twig', [
            'logout' => true,
            'menuItem_instanceRegistration' => (bool)$this->getParameter('ZERO_INTRUSION_FRONTEND_ALLOW_INSTANCE_REGISTRATION')
        ]);

        $response->setContent($html);

        return $response;
    }    
}
