<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\NewUserDTO;
use App\Entity\Group;
use App\Entity\GroupUser;
use App\Entity\Improvement;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\MailTemplateType;
use App\Enum\Role;
use App\Exception\BadGroupForUserSuppliedException;
use App\Repository\GroupRepository;
use App\Repository\GroupUserRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;

class UserService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly ResetPasswordService $resetPasswordService,
        private readonly MailingService $mailingService,
        private readonly GroupRepository $groupRepository,
        private readonly GroupUserRepository $groupUserRepository,
        private readonly AssignmentService $assignmentService
    ) {
    }

    /**
     * @throws BadGroupForUserSuppliedException
     */
    public function registerUser(NewUserDTO $userDTO, array $groupIds = []): User
    {
        $user = $userDTO->getEntity();
        $this->entityManager->persist($user);
        $this->addUserToGroupIds($user, $groupIds);
        $this->entityManager->flush();
        return $user;
    }

    public function delete(User $user): void
    {
        $this->userRepository->trash($user);
    }

    public function userHasImproverRights(?Improvement $improvement, User $user): bool
    {
        if ($improvement === null) {
            return false;
        }

        return in_array(Role::IMPROVER->string(), $user->getRoles(), true) &&
            (
                $improvement->getStatus() !== \App\Enum\ImprovementStatus::IMPROVE ||
                $this->assignmentService->getStageAssignment($improvement)?->getUser() === $user ||
                in_array(Role::MANAGER->string(), $user->getRoles(), true)
            );
    }

    public function modifyUserRoles(User $user, array $roles): void
    {
        $user->setRoles($roles);
        $this->entityManager->flush();
    }

    /**
     * @throws BadGroupForUserSuppliedException
     * @throws ORMException
     */
    public function editUser(User $user, array $roles, array $groupIds): void
    {
        $newRoles = array_intersect($roles, User::getAllAssignableRoles());
        $newRoles[] = Role::USER->string();
        $user->setRoles($newRoles);

        $groups = $this->groupRepository->findAllIndexedById();
        $groupsToRemove = $userGroups = $this->groupRepository->findAllByUser($user);
        $groupsToAdd = [];

        foreach ($groupIds as $groupId) {
            if (array_key_exists($groupId, $userGroups)) {
                unset($groupsToRemove[$groupId]);
            } elseif (array_key_exists($groupId, $groups)) {
                $groupsToAdd[] = $groups[$groupId];
            } else {
                throw new BadGroupForUserSuppliedException();
            }
        }

        $this->addUserToGroups($user, $groupsToAdd);
        $this->removeGroupUsers($user, $groupsToRemove);

        $this->entityManager->flush();
    }

    /**
     * @throws BadGroupForUserSuppliedException
     */
    private function addUserToGroupIds(User $user, array $groupIds): void
    {
        $allGropus = (sizeof($groupIds) > 0) ? $this->groupRepository->findAllIndexedById() : [];
        foreach ($groupIds as $groupId) {
            $groupUser = new GroupUser();
            if (!array_key_exists($groupId, $allGropus)) {
                throw new BadGroupForUserSuppliedException();
            }
            $groupUser->setGroup($allGropus[$groupId]);
            $groupUser->setUser($user);
            $this->entityManager->persist($groupUser);
        }
    }

    /**
     * @throws BadGroupForUserSuppliedException
     */
    public function addUserToGroups(User $user, array $groups): void
    {
        $allGroups = $this->groupRepository->findAllIndexedById();
        /** @var Group $groupToAdd */
        foreach ($groups as $groupToAdd) {
            $groupUser = new GroupUser();
            if (!array_key_exists($groupToAdd->getId(), $allGroups)) {
                throw new BadGroupForUserSuppliedException();
            }
            $groupUser->setGroup($groupToAdd);
            $groupUser->setUser($user);
            $user->addUserGroupUser($groupUser);
            $groupToAdd->addGroupGroupUser($groupUser);
            $this->entityManager->persist($groupUser);
        }
    }

    /**
     * @throws ORMException
     */
    private function removeGroupUsers(User $user, array $groupsToRemove): void
    {
        $groupUsersToRemove = $this->groupUserRepository->findByUserAndGroups($user, $groupsToRemove);
        foreach ($groupUsersToRemove as $groupUserToRemove) {
            $this->groupUserRepository->delete($groupUserToRemove);
        }
    }

    public function getUsersWithProjectAccess(Project $project, $neededRole = null): array
    {
        $usersWithAccess = $this->userRepository->findAllWithProjectAccess($project);

        return array_filter($usersWithAccess, function ($user) use ($neededRole) {
            return in_array($neededRole, $user->getRoles(), true);
        });
    }

    public function welcomeUser(User $user, MailTemplateType $mailTemplate, bool $flush = false): bool
    {
        $this->resetPasswordService->reset($user, true);
        $this->mailingService->add($mailTemplate, $user);
        if ($flush) {
            $this->entityManager->flush();
        }

        return true;
    }


    public function setUserTimeZone(User $user, ?string $timezone): void
    {
        if ($timezone !== '' && $timezone !== null && ($user->getTimeZone() === null || $user->getTimeZone() === '')) {
            $user->setTimeZone($timezone);
            $this->entityManager->flush();
        }
    }
}
