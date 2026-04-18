<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\Role;
use App\Event\Admin\Create\UserCreatedEvent;
use App\Event\Admin\Update\UserUpdatedEvent;
use App\Event\Application\UserAuthenticatedEvent;
use App\EventListener\SessionListener;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Service\GroupService;
use App\Service\ProjectService;
use App\Service\UserService;
use App\Utils\DateTimeUtil;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\NoReturn;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserSubscriber implements EventSubscriberInterface
{
    /**
     * UserSubscriber constructor.
     */
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserService $userService,
        private readonly ProjectService $projectService,
        private readonly GroupRepository $groupRepository,
        private readonly GroupService $groupService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserCreatedEvent::class => [
                'onUserCreated',
            ],
            UserUpdatedEvent::class => [
                'onUserUpdated',
            ],
            UserAuthenticatedEvent::class => [
                'onUserAuthenticated',
            ],
        ];
    }

    #[NoReturn]
    public function onUserCreated(UserCreatedEvent $event)
    {
        $user = $event->getUser();
        $isAdmin = in_array(Role::ADMINISTRATOR->string(), $user->getRoles(), true);
        if (!$isAdmin) {
            $isAlone = count($this->userRepository->findAllExcept($user)) === 0;
            if ($isAlone) {
                $user->setRoles(User::getAllNonAdminRoles());
                $hasNoGroups = count($this->groupRepository->findAll()) === 0;
                if ($hasNoGroups) {
                    $newGroup = $this->groupService->createGroup("Team", null);
                    $this->userService->addUserToGroups($user, [$newGroup]);
                }
            }
            $userIsMissingUserRole = !in_array(Role::USER->string(), $user->getRoles(), true);
            if ($userIsMissingUserRole) {
                $newRoles = $user->getRoles();
                $newRoles[] = Role::USER->string();
                $user->setRoles($newRoles);
            }

            if ($user->getDateFormat() === null || $user->getDateFormat() === '') {
                $user->setDateFormat(DateTimeUtil::DEFAULT_DATE_FORMAT);
            }
        }
        $mailTemplate = $event->getMailTemplate();
        $this->userService->welcomeUser($user, $mailTemplate);
        $this->entityManager->flush();
    }

    #[NoReturn]
    public function onUserUpdated(UserUpdatedEvent $event)
    {
        $user = $event->getUser();
        $isAdmin = in_array(Role::ADMINISTRATOR->string(), $user->getRoles(), true);
        if (!$isAdmin) {
            $userIsMissingUserRole = in_array(Role::USER->string(), $user->getRoles(), true);
            if ($userIsMissingUserRole) {
                $newRoles = $user->getRoles();
                $newRoles[] = Role::USER->string();
                $user->setRoles($newRoles);
            }
        }
        $this->entityManager->flush();
    }

    public function onUserAuthenticated(UserAuthenticatedEvent $event)
    {
        $user = $event->getUser();
        $user->setLastLogin(new \DateTime('now'));
        $user->setPasswordResetHash(null);
        $user->setPasswordResetHashExpiration(null);
        $this->entityManager->flush();
        
        $request = $event->getRequest();
        $session = $request->getSession();
        $session->set(SessionListener::TIMESTAMP, time());

        $shouldEnableFilter = !$this->entityManager->getFilters()->isEnabled('deleted_entity');
        if ($shouldEnableFilter) {
            $this->entityManager->getFilters()->enable('deleted_entity');
        }

        $availableProjects = $this->projectService->getAvailableProjectsForUser($user);
        if (count($availableProjects) !== 0) {
            $this->projectService->setCurrentProject($availableProjects[array_key_first($availableProjects)]);
        }

        if ($shouldEnableFilter) {
            $this->entityManager->getFilters()->disable('deleted_entity');
        }
    }
}
