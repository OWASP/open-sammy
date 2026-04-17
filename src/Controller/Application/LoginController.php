<?php

declare(strict_types=1);

namespace App\Controller\Application;

use App\DTO\NewUserDTO;
use App\Enum\Role;
use App\Event\Admin\Create\UserCreatedEvent;
use App\Form\Application\ResetPasswordRequestType;
use App\Form\Application\UserLoginType;
use App\Repository\UserRepository;
use App\Service\MailingService;
use App\Service\ResetPasswordService;
use App\Service\UserService;
use App\Service\ConfigurationService;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class LoginController.
 *
 * @Route(name="login_")
 */
#[Route(name: 'login_')]
class LoginController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(
        Request $request,
        Security $security,
        AuthenticationUtils $authenticationUtils,
        ConfigurationService $configurationService,
    ): Response {
        // if the user is already logged in, don't display the login page again
        if ($security->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('app_dashboard_index');
        }

        $form = $this->createForm(UserLoginType::class, null);

        return $this->render(
            'application/login/login.html.twig',
            [
                'error' => $authenticationUtils->getLastAuthenticationError(),
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * @Route("/logout", name="logout")
     */
    #[Route('/logout', name: 'logout')]
    public function logout()
    {
    }

    /**
     * @Route("/password-reset-hash/{hash}", name="password-reset-hash")
     *
     * @return RedirectResponse|Response
     *
     * @throws NonUniqueResultException
     */
    #[Route('/password-reset-hash/{hash}', name: 'password-reset-hash')]
    public function passwordResetHash(
        string $hash,
        Security $security,
        Request $request,
        UserRepository $userRepository,
        AuthenticationUtils $authenticationUtils,
        TranslatorInterface $translator
    ) {
        // if user is already logged in, don't display the login page again
        if ($security->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('app_dashboard_index');
        }

        try {
            $userRepository->loadUserByPasswordResetHash($hash);
        } catch (\Throwable) {
            $this->getSession()->set('invalid_sso', true);

            return $this->redirectToRoute('app_login_reset_password_request');
        }

        $form = $this->createForm(\App\Form\Application\ResetPasswordType::class);
        $form->handleRequest($request);

        $error = $authenticationUtils->getLastAuthenticationError();
        if ($error !== null && strlen($error->getMessageKey()) !== 0) {
            $this->addFlash('warning', $translator->trans($error->getMessageKey(), [], 'security'));
        }

        return $this->render(
            'application/login/sso.html.twig',
            [
                'hash' => $hash,
                'form' => $form->createView(),
                'error' => null,
            ]
        );
    }

    #[Route('/reset-password', name: 'reset_password_request')]
    public function resetPasswordRequest(
        Request $request,
        ResetPasswordService $passwordResetService,
        MailingService $mailingService,
        TranslatorInterface $translator,
        UserRepository $userRepository,
        ConfigurationService $configurationService
    ): Response {
        $supportsLoginForm = $configurationService->getBool('login.internal');
        if (!$supportsLoginForm) {
            return $this->redirectToRoute('app_login_login');
        }

        $session = $this->getSession();
        $invalidSSO = $session->get('invalid_sso');
        $session->remove('invalid_sso');

        $form = $this->createForm(ResetPasswordRequestType::class, null);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $user = $userRepository->findOneBy(['email' => $email, 'deletedAt' => null]);
            $sendFailed = false;
            if ($user !== null && !in_array(Role::ADMINISTRATOR->string(), $user->getRoles(), true)) {
                $status = $passwordResetService->reset($user);
                if ($status) {
                    $sendFailed = !$mailingService->sendImmediate(\App\Enum\MailTemplateType::USER_PASSWORD_RESET, $user);
                }
            }

            if ($sendFailed) {
                $this->addFlash('error', $translator->trans('application.general.reset_password_send_failed', [], 'application'));
            } else {
                $this->addFlash('success', $translator->trans('application.general.reset_password_success', [], 'application'));
            }

            return $this->redirectToRoute('app_login_login');
        }

        return $this->render(
            'application/login/reset_password_request.html.twig',
            [
                'form' => $form->createView(),
                'invalidSSO' => $invalidSSO,
            ]
        );
    }
}
