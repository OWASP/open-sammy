<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\ResetPasswordService;
use App\Tests\_support\AbstractKernelTestCase;
use App\Tests\builders\UserBuilder;
use App\Tests\helpers\TestHelper;


class ResetPasswordServiceTest extends AbstractKernelTestCase
{

    private ResetPasswordService $resetPasswordService;

    public function setUp(): void
    {
        parent::setUp();
        $this->resetPasswordService = self::getContainer()->get(ResetPasswordService::class);
    }

    /**
     * @dataProvider resetProvider
     */
    public function testReset(User $user, bool $isWelcomeMail, bool $expectedResult)
    {
        //EXECUTE
        $result = $this->resetPasswordService->reset($user, $isWelcomeMail);
        //TESTS
        self::assertEquals($result, $expectedResult);
        self::assertNotEmpty($user->getPasswordResetHash());
        self::assertTrue($user->getPasswordResetHashExpiration() > new \DateTime());
        //make sure password reset is not larger than 2 days
        $future = new \DateTime();
        $future->modify('+3 day');
        self::assertTrue($user->getPasswordResetHashExpiration() < $future);
    }

    public function resetProvider()
    {
        return [
            'User 1' => [(new User())->setName("user1")->setId(1), false, true],
        ];
    }

    /**
     * @group asvs
     * @dataProvider passwordResetLinkValidityProvider
     * @testdox ASVS 2.3.1 - $_dataName
     */
    public function testPasswordResetLinkValidity(array $entitiesToPersist, User $user, bool $isWelcomeEmail, bool $expectedResult): void
    {
        $this->persistEntities(...$entitiesToPersist);
        $this->entityManager->flush();

        $passwordResetHashBeforeReset = $user->getPasswordResetHash();

        $result = $this->resetPasswordService->reset($user, $isWelcomeEmail);

        $passwordResetHashAfterReset = $user->getPasswordResetHash();

        $hashEntropy = TestHelper::calculateEntropy($passwordResetHashAfterReset);
        $hashExpireTime = $user->getPasswordResetHashExpiration();

        self::assertEquals($expectedResult, $result);
        self::assertNotEquals($passwordResetHashBeforeReset, $passwordResetHashAfterReset);
        self::assertTrue(strlen($passwordResetHashAfterReset) >= 6);
        self::assertTrue($hashEntropy >= 3);
        self::assertTrue($hashExpireTime > new \DateTime('+30 minutes') && $hashExpireTime < new \DateTime('+2 hours'));

        $user->setPasswordResetHash('');
        $this->entityManager->flush();

        $this->resetPasswordService->reset($user, $isWelcomeEmail);

        self::assertNotNull($user->getPasswordResetHash());
        self::assertNotEquals($passwordResetHashBeforeReset, $user->getPasswordResetHash());
        self::assertNotEquals($passwordResetHashAfterReset, $user->getPasswordResetHash());
    }

    public function passwordResetLinkValidityProvider(): \Generator
    {
        yield "Test 1 - That the password reset link is more than 6 characters long, has entropy over 3, is expired after 12 hours, is not the same if reset again" => [
            [
                $user = (new UserBuilder())->build(),
            ],
            $user,
            false, // is welcome email
            true, // expected result
        ];
    }
}