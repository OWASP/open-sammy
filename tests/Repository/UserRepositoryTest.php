<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Assignment;
use App\Entity\Improvement;
use App\Entity\User;
use App\Enum\ImprovementStatus;
use App\Enum\Role;
use App\Repository\UserRepository;
use App\Tests\_support\AbstractKernelTestCase;
use App\Tests\builders\UserBuilder;
use Doctrine\ORM\EntityManagerInterface;

class UserRepositoryTest extends AbstractKernelTestCase
{
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = self::getContainer()->get(UserRepository::class);
    }

    /**
     * @dataProvider findAllExceptProvider
     */
    public function testFindAllExcept(User $user, ?User $expectedUser): void
    {
        $this->entityManager->persist($user);
        if ($expectedUser !== null) {
            $this->entityManager->persist($expectedUser);
        }
        $this->entityManager->flush();

        $resultUsers = static::getContainer()->get(UserRepository::class)->findAllExcept($user);


        if ($expectedUser === null) {
            self::assertEmpty($resultUsers);
        } else {
            self::assertEquals($resultUsers[$expectedUser->getId()]->getId(), $expectedUser->getId());
            self::assertEquals($resultUsers[$expectedUser->getId()]->getSecretKey(), $expectedUser->getSecretKey());
            self::assertEquals($resultUsers[$expectedUser->getId()]->getEmail(), $expectedUser->getEmail());
        }
    }

    public function findAllExceptProvider(): array
    {
        $user = (new UserBuilder())->build();
        $user2 = (new UserBuilder())->build();

        return [
            'Positive test 1. Expect a match' => [
                $user, // user
                $user2, // expected user
            ],
        ];
    }

    /**
     * @return void
     */
    public function testFindAllNonAdmins(): void
    {
        $entityManager = $this->entityManager;
        (new UserBuilder($entityManager))->build();

        $allUsers = $entityManager->getRepository(User::class)->findAll();
        $nonAdminUsers = [];
        foreach ($allUsers as $user) {
            if (!in_array("ROLE_ADMIN", $user->getRoles(), true)) {
                $nonAdminUsers[] = $user;
            }
        }
        $resultUsers = static::getContainer()->get(UserRepository::class)->findAllNonAdmins();
        self::assertCount(sizeof($resultUsers), $nonAdminUsers);
    }

    /**
     * @return void
     */
    public function testFindAllAdmins(): void
    {
        $entityManager = $this->entityManager;
        (new UserBuilder($entityManager))->withRoles([Role::ADMINISTRATOR->string()])->build();

        $allUsers = $entityManager->getRepository(User::class)->findAll();
        $adminUsers = [];
        foreach ($allUsers as $user) {
            if (in_array("ROLE_ADMIN", $user->getRoles(), true)) {
                $adminUsers[] = $user;
            }
        }

        $resultUsers = static::getContainer()->get(UserRepository::class)->findAllAdmins();
        self::assertCount(sizeof($resultUsers), $adminUsers);
    }

    /**
     * @dataProvider loadUserByPasswordResetHashProvider
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function testLoadUserByPasswordResetHash(?User $user, string $plaintextToken, bool $isExpectingException): void
    {
        if ($user !== null) {
            $this->entityManager->persist($user);
        }
        $this->entityManager->flush();

        if ($isExpectingException) {
            $this->expectException(\Exception::class);
        }

        $resultUser = static::getContainer()->get(UserRepository::class)->loadUserByPasswordResetHash($plaintextToken);

        if (!$isExpectingException) {
            self::assertNotNull($resultUser);
        }
    }

    /**
     * @return array[]
     */
    public function loadUserByPasswordResetHashProvider(): array
    {
        $plaintext = bin2hex(random_bytes(5))."Gj2323jk2jjkanu3hakwj3hajk3";
        $user = (new UserBuilder())
            ->withPasswordResetHash(\App\Service\ResetPasswordService::hashToken($plaintext))
            ->withPasswordResetHashExpiration(new \DateTime('tomorrow'))
            ->build();

        return [
            'Positive test 1. Expect user' => [
                $user,
                $plaintext,
                false,
            ],
            'Negative test 1. Wrong hash supplied. No user expected, but exception' => [
                null,
                'wrongHash',
                true,
            ],
        ];
    }

    /**
     * @dataProvider findAllIndexedByIdProvider
     */
    public function testFindAllIndexedById(?User $expectedUser): void
    {
        if ($expectedUser !== null) {
            $this->entityManager->persist($expectedUser);
        }
        $this->entityManager->flush();

        $resultUsers = static::getContainer()->get(UserRepository::class)->findAllIndexedByName();

        if ($expectedUser === null) {
            self::assertEmpty($resultUsers);
        } else {
            $expectedUser = static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->find($expectedUser);
            self::assertSame($resultUsers[$expectedUser->getId()], $expectedUser);
        }

    }

    /**
     * @return array[]
     */
    public function findAllIndexedByIdProvider(): array
    {
        $user = (new UserBuilder())
            ->withPasswordResetHash(bin2hex(random_bytes(5))."Gj2323jk2jjkanu3hakwj3hajk3")
            ->withPasswordResetHashExpiration(new \DateTime('tomorrow'))
            ->build();

        return [
            'Positive test 1. Expect user' => [
                $user, // expected user
            ],
        ];
    }

    /**
     * @dataProvider testTrashProvider
     * @testdox $_dataName
     */
    public function testTrash(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        self::assertNull($user->getDeletedAt());

        $this->userRepository->trash($user);

        self::assertNotNull($user->getDeletedAt());
    }

    private function testTrashProvider(): array
    {
        return [
            "Test that the user has a deleteAt set after the deletion" => [
                new User(),
            ],
        ];
    }


    public function testUserDeletionDoesNotDeleteStagesAndAssignments(): void
    {
        $user = (new UserBuilder($this->entityManager))->build();
        $assignment = (new Assignment())->setUser($user)->setStage((new Improvement())->setStatus(ImprovementStatus::DRAFT));

        $this->entityManager->persist($assignment);

        self::assertNull($user->getDeletedAt());

        $this->userRepository->trash($user);

        self::assertNotNull($user->getDeletedAt());

        $assignmentAfter = $this->entityManager->getRepository(Assignment::class)->find($assignment->getId());

        self::assertNotNull($assignmentAfter);
        self::assertNull($assignmentAfter->getDeletedAt());
        self::assertNull($assignmentAfter->getStage()->getDeletedAt());
        self::assertEquals($assignment->getStage(), $assignmentAfter->getStage());
        self::assertEquals($assignment->getStage()->getStatus(), $assignmentAfter->getStage()->getStatus());
    }
}