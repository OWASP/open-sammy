<?php

declare(strict_types=1);

namespace App\Controller\Application;

use App\DTO\NewUserDTO;
use App\Entity\Group;
use App\Entity\User;
use App\Enum\MailTemplateType;
use App\Enum\Role;
use App\Event\Admin\Create\UserCreatedEvent;
use App\Exception\BadGroupForUserSuppliedException;
use App\Exception\InvalidUserDataException;
use App\Exception\ZeroSheetsProvidedForImportException;
use App\Form\Application\GroupFilterType;
use App\Form\Application\UserAddType;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Service\AssignmentService;
use App\Service\Processing\UserImporterService;
use App\Service\ProjectService;
use App\Service\UserService;
use App\Traits\ApplicationCrudTrait;
use App\Traits\SafeAjaxModifyTrait;
use App\Traits\UtilsBundle\CrudPopulateEnumerationSelectTrait;
use PhpOffice\PhpSpreadsheet\Exception;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user', name: 'user_')]
#[IsGranted('ROLE_MANAGER')]
class UserController extends AbstractController
{
    use ApplicationCrudTrait;
    use SafeAjaxModifyTrait;
    use CrudPopulateEnumerationSelectTrait;

    #[Route('/index/{group}', name: 'index', requirements: ['group' => "\d+"], methods: ['GET'])]
    #[IsGranted(new Expression("is_granted('GROUP_ACCESS', subject) || subject == null"), 'group')]
    public function index(
        Request $request,
        UserRepository $userRepository,
        GroupRepository $groupRepository,
        AssignmentService $assignmentService,
        ?Group $group = null,
    ): Response {

        $page = $request->query->getInt('page', 1);
        $searchTerm = $request->query->get('searchTerm', '');
        if ($group !== null) {
            $users = $userRepository->findAllByGroup($group, searchTerm: $searchTerm, page: $page, returnPaginated: true);
        } else {
            $users = $userRepository->findAllIndexedByName(searchTerm: $searchTerm, page: $page, returnPaginated: true);
        }

        return $this->render(
            'application/user/index.html.twig',
            [
                'group' => $group,
                'users' => $users,
                'allGroups' => $groupRepository->findAll(),
                'allUserAssignments' => $assignmentService->getAssessmentsGroupedByUsers($users->getResults()->getArrayCopy()),
                'addUserForm' => $this->createForm(UserAddType::class, new NewUserDTO(), [
                    'groups' => $groupRepository->findAll(),
                    'selectedGroups' => $group !== null ? [$group] : [],
                ])->createView(),
                'groupFilterForm' => $this->createForm(GroupFilterType::class, ['selected' => $group], [
                    'groups' => $groupRepository->findAll(),
                    'action' => $this->generateUrl('app_user_index'),
                ])->createView(),
                'queryParams' => $request->query->all(),
            ]
        );
    }

    #[Route('/filter/{group}', name: 'filter', requirements: ['group' => "\d+"], methods: ['GET'])]
    #[IsGranted(new Expression("is_granted('GROUP_ACCESS', subject) || subject == null"), 'group')]
    public function filter(
        Request $request,
        UserRepository $userRepository,
        GroupRepository $groupRepository,
        AssignmentService $assignmentService,
        ?Group $group = null,
    ): Response {

        $page = $request->query->getInt('page', 1);
        $searchTerm = $request->query->get('searchTerm', '');
        if ($group !== null) {
            $users = $userRepository->findAllByGroup($group, searchTerm: $searchTerm, page: $page, returnPaginated: true);
        } else {
            $users = $userRepository->findAllIndexedByName(searchTerm: $searchTerm, page: $page, returnPaginated: true);
        }

        return $this->render(
            'application/user/table-paginator-container.html.twig',
            [
                'group' => $group,
                'users' => $users,
                'allGroups' => $groupRepository->findAll(),
                'allUserAssignments' => $assignmentService->getAssessmentsGroupedByUsers($users->getResults()->getArrayCopy()),
                'addUserForm' => $this->createForm(UserAddType::class, new NewUserDTO(), [
                    'groups' => $groupRepository->findAll(),
                    'selectedGroups' => $group !== null ? [$group] : [],
                ])->createView(),
                'groupFilterForm' => $this->createForm(GroupFilterType::class, ['selected' => $group], [
                    'groups' => $groupRepository->findAll(),
                    'action' => $this->generateUrl('app_user_index'),
                ])->createView(),
                'queryParams' => $request->query->all(),
            ]
        );
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(
        Request $request,
        UserService $userService,
        GroupRepository $groupRepository,
    ): Response {
        $userDTO = new NewUserDTO();
        $form = $this->createForm(UserAddType::class, $userDTO, ['groups' => $groupRepository->findAll()]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $user = $userService->registerUser($userDTO, $form->get('groups')->getData());
                $this->eventDispatcher->dispatch(new UserCreatedEvent($request, $user));
                $this->addFlash('success', $this->translator->trans('application.user.save_success', ['user' => trim("$user")], 'application'), true);
            } catch (BadGroupForUserSuppliedException) {
                $this->addFlash('error', $this->translator->trans('application.user.bad_groups', [], 'application'));
            }
        } elseif ($form->isSubmitted() && !$form->isValid()) {
            $formError = $form->getErrors(true)->current();
            $msg = $formError->getMessage();
            $failedValue = $formError->getOrigin()->getData();
            $failedValue = $failedValue === [] ? '' : $failedValue;
            $this->addFlash('error', "({$failedValue}) {$msg}", true);
        }

        return $this->safeRedirect($request, 'app_user_index');
    }

    #[Route('/ajaxModify/{id}', name: 'ajaxmodify', requirements: ['id' => "\d+"], methods: ['POST'])]
    #[IsGranted('USER_EDIT', 'chosenUser')]
    public function ajaxModify(Request $request, User $chosenUser, UserRepository $userRepository): JsonResponse
    {
        $value = $request->request->get('value');
        $name = $request->request->get('name');
        if ($name === 'email') {
            $existingUser = $userRepository->findBy([
                'email' => $value,
                'externalId' => $chosenUser->getExternalId(),
                'deletedAt' => null,
            ]);
            if (count($existingUser) !== 0) {
                return new JsonResponse(['status' => 'error', 'msg' => 'Email is already used'], Response::HTTP_BAD_REQUEST);
            }
        }

        return $this->safeAjaxModify($request, $chosenUser);
    }

    #[Route('/delete/{id}', name: 'delete', requirements: ['id' => "\d+"], methods: ['DELETE'])]
    #[IsGranted('USER_EDIT', 'chosenUser')]
    public function delete(Request $request, User $chosenUser, UserService $userService): Response
    {
        try {
            $userService->delete($chosenUser);
            $this->addFlash('success', $this->translator->trans('application.user.delete_success', ['user' => trim("$chosenUser")], 'application'), true);
        } catch (\Throwable) {
            $this->addFlash('error', $this->translator->trans('application.user.delete_error', [], 'application'));
        }

        return $this->safeRedirect($request, 'app_user_index');
    }

    #[Route('/resend-welcome/{id}', name: 'resend_welcome', requirements: ['id' => "\d+"], methods: ['POST'])]
    #[IsGranted('USER_EDIT', 'chosenUser')]
    public function resendWelcome(Request $request, User $chosenUser, UserService $userService): Response
    {
        if (!$this->isCsrfTokenValid((string) $this->getUser()?->getId(), $request->request->get('_token'))) {
            return $this->safeRedirect($request, 'app_user_index');
        }

        $sent = $userService->welcomeUser($chosenUser, MailTemplateType::USER_WELCOME, flush: true);
        if ($sent) {
            $this->addFlash('success', $this->translator->trans('application.user.resend_welcome_success', ['user' => trim("$chosenUser")], 'application'));
        } else {
            $this->addFlash('error', $this->translator->trans('application.user.resend_welcome_error', [], 'application'));
        }

        return $this->safeRedirect($request, 'app_user_index');
    }

    #[Route('/editUser/{id}', name: 'edituser', requirements: ['id' => "\d+"], methods: ['POST'])]
    #[IsGranted('USER_EDIT', 'chosenUser')]
    public function editUser(Request $request, User $chosenUser, UserService $userService): RedirectResponse
    {
        $newRoles = $request->request->all('userRoles');
        $newGroups = $request->request->all('userGroups');

        if ($this->getUser() === $chosenUser && !in_array(Role::MANAGER->string(), $newRoles, true)) {
            $newRoles[] = Role::MANAGER->string();
        }

        try {
            $userService->editUser($chosenUser, $newRoles, $newGroups);
            $this->addFlash('success', $this->translator->trans('application.user.user_edit_success', [], 'application'));
        } catch (BadGroupForUserSuppliedException) {
            $this->addFlash('error', $this->translator->trans('application.user.bad_groups', [], 'application'));
        }

        return $this->safeRedirect($request, 'app_user_index');
    }
}
