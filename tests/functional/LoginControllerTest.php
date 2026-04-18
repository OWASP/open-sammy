<?php

declare(strict_types=1);

namespace App\Tests\functional;

use App\Entity\User;
use App\Enum\Role;
use App\Repository\UserRepository;
use App\Service\ResetPasswordService;
use App\Tests\_support\PantherLogIn;
use App\Tests\builders\UserBuilder;
use App\Tests\helpers\TestHelper;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class LoginControllerTest extends PantherTestCase
{
    use PantherLogIn;

    private KernelBrowser|Client|null $client = null;
    private ?EntityManager $entityManager = null;
    private ?ResetPasswordService $resetPasswordService = null;
    private ?UrlGeneratorInterface $urlGenerator = null;
    private const SECURITY_LOG_PATH = "var/log/test.security.log";
    private const AUDIT_LOG_PATH = "var/log/test.audit.log";

    public function setUp(): void
    {
        if ($this->client === null) {
            $this->client = self::createClient();
            $this->client->catchExceptions(false);
        }

        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetPasswordService = static::getContainer()->get(ResetPasswordService::class);
        $this->urlGenerator = static::getContainer()->get(UrlGeneratorInterface::class);
    }

    /**
     * @dataProvider loginFormDataProvider
     * @param string $userEmail
     * @param string $userPassword
     * @param string $expectedRedirect
     */
    public function testLoginForm(?User $user, string $userEmail, string $userPassword, string $expectedRedirect): void
    {
        if ($user !== null) {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        $this->client->request('GET', '/login');
        $this->client->submitForm('Login', [
            'user_login[email]' => $userEmail,
            'user_login[password]' => $userPassword,
        ]);

        self::assertResponseRedirects($expectedRedirect);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function loginFormDataProvider(): array
    {
        $legitUser = (new User())
            ->setRoles([Role::USER->string()])
            ->setEmail("test".bin2hex(random_bytes(5))."@test.test")
            ->setPassword('$2a$12$NW0Y1QZg5BK4SQ26O07h8OeshH/Mo5S6OzcxYj8t0bL3IEzGn2dLG')
            ->setAgreedToTerms(true)
            ->setSecretKey('MFZWIZDEMRSQ====');

        $admin = (new User())
            ->setRoles([Role::ADMINISTRATOR->string()])
            ->setEmail("admin".bin2hex(random_bytes(5))."@admin.admin")
            ->setPassword('$2a$12$NW0Y1QZg5BK4SQ26O07h8OeshH/Mo5S6OzcxYj8t0bL3IEzGn2dLG')
            ->setAgreedToTerms(true)
            ->setSecretKey('MFZWIZDEMRSQ====');

        return [
            "Legit user attempts to login, expect success and redirect to frontend" => [
                $legitUser, // user
                $legitUser->getEmail(),
                'admin',
                '/',
            ],
            "Admin attempts to login in frontend, expect failure and redirect to login" => [
                $admin, // user
                $admin->getEmail(),
                'admin',
                '/login',
            ],
            "Fuzzing #1 - Wrong characters entered, expect failure" => [
                null, // user
                '#',
                '(',
                '/login',
            ],
            "Fuzzing #2 - Wrong characters entered, expect failure" => [
                null, // user
                "1' OR 1=1 #--",
                "1' OR 1=1 #--",
                '/login',
            ],
            "Fuzzing #3 - Too many characters entered, expect failure" => [
                null,
                // user
                "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aenean dignissim risus cursus risus elementum, et pellentesque lacus sodales. Sed pharetra turpis a libero laoreet commodo nec non diam. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. In hac habitasse platea dictumst. Aliquam feugiat id quam id cursus. Praesent fermentum enim ac felis imperdiet dictum. Sed in euismod augue, ut aliquet nisi. Suspendisse imperdiet purus velit. Quisque non laoreet libero. Cras orci nibh, tristique et massa fermentum, lobortis pulvinar lectus. Cras viverra ipsum felis, eu fermentum est bibendum sed. Donec nunc nulla, aliquam eget tincidunt quis, lacinia non risus. In porta sapien eu urna pulvinar lobortis. In hac habitasse platea dictumst. Duis dictum libero odio, et imperdiet ipsum luctus at.",
                "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aenean dignissim risus cursus risus elementum, et pellentesque lacus sodales. Sed pharetra turpis a libero laoreet commodo nec non diam. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. In hac habitasse platea dictumst. Aliquam feugiat id quam id cursus. Praesent fermentum enim ac felis imperdiet dictum. Sed in euismod augue, ut aliquet nisi. Suspendisse imperdiet purus velit. Quisque non laoreet libero. Cras orci nibh, tristique et massa fermentum, lobortis pulvinar lectus. Cras viverra ipsum felis, eu fermentum est bibendum sed. Donec nunc nulla, aliquam eget tincidunt quis, lacinia non risus. In porta sapien eu urna pulvinar lobortis. In hac habitasse platea dictumst. Duis dictum libero odio, et imperdiet ipsum luctus at.",
                '/login',
            ],
            "Fuzzing #4 - Wrong characters entered, expect failure" => [
                null, // user
                "",
                "",
                '/login',
            ],
            "Fuzzing #5 - Wrong characters entered, expect failure" => [
                null, // user
                "'",
                "''",
                '/login',
            ],
        ];
    }

    /**
     * Creates a user and performs the reset password flow
     * @param string $password - the new password for the user
     * @return User
     */
    private function performResetPasswordFlow(string $password): User
    {
        // Arrange
        $email = bin2hex(random_bytes(5));
        $user = (new User())
            ->setEmail($email."@test.test");
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Act
        // The HTTP form-submit path triggers an SMTP send that the test env can't satisfy.
        // Drive the reset directly so we can grab the plaintext token before it's flushed away.
        $resetPasswordService = static::getContainer()->get(\App\Service\ResetPasswordService::class);
        $resetPasswordService->reset($user);
        $plaintextToken = $user->getPlaintextPasswordResetHash();

        $this->client->request('GET', '/password-reset-hash/'.$plaintextToken);
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Save', [
            'reset_password[newPassword][first]' => $password,
            'reset_password[newPassword][second]' => $password,
        ]);

        // NOTE:
        // Refreshing the user instance
        $user = static::getContainer()->get(UserRepository::class)->findOneBy([
            "id" => $user->getId(),
        ]);

        return $user;
    }

    /**
     * @dataProvider invalidPasswordProvider
     * @param string $password
     * @param string $expectedValidationError
     * @group asvs
     * @group security
     * @testdox $_dataName
     */
    public function testResetPasswordNotAllowed(string $password, string $expectedValidationError): void
    {
        $this->performResetPasswordFlow($password);
        self::assertSelectorTextContains("span.form-error-message", $expectedValidationError);
    }

    private function invalidPasswordProvider(): array
    {

        return [
            "Authentication(v4.0.3-2.1.1) doesn't allow passwords with less than 12 symbols" => [
                bin2hex(random_bytes(5)),
                "This value is too short. It should have 12 characters or more.",
            ],
            "Authentication(v4.0.3-2.1.2) doesn't allow passwords with more than 128 symbols" => [
                bin2hex(random_bytes(65)),
                "This value is too long. It should have 128 characters or less.",
            ],
            "Authentication (v4.0.3-2.1.7) when the new password is from a set of breached passwords" => [
                "asdfASDF1234!",
                "This password has been leaked in a data breach, it must not be used. Please use another password.",
            ],
        ];
    }

    /**
     * @dataProvider validPasswordProvider
     * @param string $password
     * @group asvs
     * @group security
     * @testdox $_dataName
     */
    public function testResetPasswordAllowed(string $password): void
    {
        $user = $this->performresetpasswordflow($password);
        // assert
        $newpasswordhash = $user->getpassword();
        self::assertnotequals("", $newpasswordhash);
    }

    public function validPasswordProvider(): array
    {
        $firstEmojiHexValue = 0x1f601;
        $emojiPassword = $this->generateUTF8String(124, $firstEmojiHexValue);

        //NOTE:
        // Appending this here so the validations for the
        // lower case, uppercase, numbers and symbols also pass
        $emojiPassword .= "aA!1";

        return [
            "Authentication(v4.0.3-2.1.2) allows passwords with more than 64 symbols" => [
                // NOTE:
                // Appending an uppercase 'A!' here because
                // bin2hex the randomly generated string
                // does not contain any upper case letters
                // or symbols
                bin2hex(random_bytes(32))."A!",
            ],
            "Authentication(v4.0.3-2.1.4) using emojis in the password is allowed" => [
                $emojiPassword,
            ],
        ];
    }

    /**
     * Generates a UTF-8 string
     * @param int $range
     * @param int $from - from which unicode character to start (hex value of the unicode character)
     */
    private function generateUTF8String(int $range, int $from = 0x0): string
    {
        $result = "";
        $counter = 0;

        for ($unicodeHexValue = $from; $unicodeHexValue <= 0xFFFFD; $unicodeHexValue++) {
            if ($range === $counter++) {
                break;
            }

            $result .= \mb_chr($unicodeHexValue, "UTF-8");
        }

        return $result;
    }

    /**
     * @group asvs
     * @group security
     * @testdox Error Handling(v4.0.3-7.1.1) Verify that the application does not log credentials or payment details.
     */
    public function testStoredCredentialsSessionTokenInLogs(): void
    {
        $email = bin2hex(random_bytes(5));
        $logPaths = [self::SECURITY_LOG_PATH, self::AUDIT_LOG_PATH];
        // NOTE:
        // We need to empty the files here, because with time the logs might get very long
        file_put_contents("var/log/test.security.log", " ");
        file_put_contents("var/log/test.audit.log", " ");
        $testUser = (new UserBuilder($this->entityManager))->withEmail($email."@test.com")->build();

        $this->client->loginUser($testUser, "boardworks");

        $this->client->request("GET", "/dashboard");

        $cookies = $this->client->getCookieJar()->all();

        $mockSessionID = null;
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === "MOCKSESSID") {
                $mockSessionID = $cookie->getValue();
            }
        }
        foreach ($logPaths as $logPath) {
            $this->testStoredCredentialsOpenLogFileAndAssert($logPath, $mockSessionID, $testUser);
        }
    }

    private function testStoredCredentialsOpenLogFileAndAssert($logPath, $mockSessionID, $testUser)
    {
        $handle = fopen($logPath, "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                self::assertStringNotContainsString($mockSessionID, $line);
                self::assertStringNotContainsString($testUser->getPassword(), $line);
                self::assertStringNotContainsString("admin123", $line);
            }
            fclose($handle);
        }
    }

    /**
     * @group asvs
     * @group security
     * @testdox Error Handling(v4.0.3-7.1.3) Test that the application logs security relevant events.
     */
    public function testApplicationLogsEvents(): void
    {
        $email = bin2hex(random_bytes(5));
        $testUser = (new UserBuilder($this->entityManager))->withEmail($email."@test.com")->build();

        $this->client->request('GET', '/login');
        $this->client->submitForm('Login', [
            'user_login[email]' => $testUser->getEmail(),
            'user_login[password]' => "wrongpassword",
        ]);

        $line = TestHelper::openFileAndReturnLastXLines(self::SECURITY_LOG_PATH, 1);
        self::assertStringContainsString("The presented password is invalid", $line);

        $this->client->request('GET', '/login');
        $this->client->submitForm('Login', [
            'user_login[email]' => $testUser->getEmail(),
            'user_login[password]' => "admin123",
        ]);

        $line = TestHelper::openFileAndReturnLastXLines(self::SECURITY_LOG_PATH, 1);
        self::assertStringContainsString("Authenticator successful!", $line);
    }

    /**
     * @group asvs
     * @testdox Error Handling(v4.0.3-7.1.4) Verify that each log event includes necessary information.
     */
    public function testLogsContainsNecessaryInformation(): void
    {
        $email = bin2hex(random_bytes(5));
        $testUser = (new UserBuilder($this->entityManager))->withEmail($email."@test.com")->build();

        $this->client->request('GET', '/login');
        $this->client->submitForm('Login', [
            'user_login[email]' => $testUser->getEmail(),
            'user_login[password]' => "wrongpassword",
        ]);

        $necessaryInfo = ["Authenticator failed.", "url", "ip", "http_method", "server", "referrer"];
        $line = TestHelper::openFileAndReturnLastXLines(self::SECURITY_LOG_PATH, 1);
        foreach ($necessaryInfo as $info) {
            self::assertStringContainsString($info, $line);
        }

        $this->client->request('GET', '/login');
        $this->client->submitForm('Login', [
            'user_login[email]' => $testUser->getEmail(),
            'user_login[password]' => "admin123",
        ]);

        $necessaryInfo = [
            "Authenticator successful!",
            $testUser->getEmail(),
            "url",
            "ip",
            "http_method",
            "server",
            "referrer",
        ];
        $line = TestHelper::openFileAndReturnLastXLines(self::SECURITY_LOG_PATH, 1);
        foreach ($necessaryInfo as $info) {
            self::assertStringContainsString($info, $line);
        }
    }

    /**
     * @group asvs
     * @group security
     * @dataProvider genericMessageIsShownAndNotLeakedDataProvider
     * @testdox Error Handling(v4.0.3-7.4.1) Test that a generic message is shown when an unexpected
     * or security sensitive error occurs.
     */
    public function testMessageIsShownOnUnexpectedError(User $user, $email): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->followRedirects(true);
        $this->client->request('GET', '/login');
        $this->client->submitForm('Login', [
            'user_login[email]' => $email,
            'user_login[password]' => "randompassword",
        ]);

        self::assertSelectorTextContains("div.alert-danger", "Invalid credentials.");
    }

    private function genericMessageIsShownAndNotLeakedDataProvider(): array
    {
        $randomness = bin2hex(random_bytes(5));
        $user = (new UserBuilder())->withEmail("existingEmail".$randomness."@test.test")->build();

        return [
            "Test that the system does not leak info with a non existing email" =>
                [
                    $user,
                    "NonExistingEmail_324823@test.test",
                ],
            "Test that the system does not leak info with an existing email" =>
                [
                    $user,
                    "existingEmail".$randomness."@test.test",
                ],
        ];
    }

    /**
     * @group asvs
     * @group security
     * @testdox Session Management(v4.0.3-3.2.1) Verify the application generates a new session token on user
     * authentication.
     */
    public function testLoggingInGeneratesANewSessionToken(): void
    {
        $email = "existingEmail".bin2hex(random_bytes(5))."@test.test";

        $user = (new User())->setRoles([Role::USER->string()])
            ->setEmail($email)
            ->setPassword('$2a$12$hq.FgFXh4seK2P6MyALR1uTpKGBhWNdKbFXGqpxP3fV/KQEZNyURm'); // admin123;
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->request('GET', '/login');
        $oldSessionToken = $this->client->getCookieJar()->get("MOCKSESSID");

        $this->client->followRedirects(true);
        $this->client->submitForm('Login', [
            'user_login[email]' => $email,
            'user_login[password]' => "admin123",
        ]);

        $newSessionToken = $this->client->getCookieJar()->get("MOCKSESSID");
        self::assertNotEquals($oldSessionToken, $newSessionToken);
    }

    /**
     * @group asvs
     * @dataProvider authenticationLogsArePresentProvider
     * @testdox ASVS 1.2.3 - $_dataName
     */
    public function testAuthenticationLogsArePresent(array $entitiesToPersist, User $user, string $password, bool $expectedAuthenticationSuccess): void
    {
        foreach ($entitiesToPersist as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        $this->client->request('GET', '/login');
        $this->client->submitForm('Login', [
            'user_login[email]' => $user->getEmail(),
            'user_login[password]' => $password,
        ]);

        $line = TestHelper::openFileAndReturnLastXLines(self::SECURITY_LOG_PATH, 1);

        if ($expectedAuthenticationSuccess) {
            self::assertStringContainsString("Authenticator successful!", $line);
        } else {
            self::assertStringContainsString("Authenticator failed.", $line);
        }
    }

    public function authenticationLogsArePresentProvider(): \Generator
    {
        yield "Test 1 - a log is present for a user who has successfully authenticated" => [
            [
                $user = (new UserBuilder())->build(),
            ],
            $user,
            "admin123",
            true,
        ];
        yield "Test 2 - a log is present for a user who has unsuccessfully authenticated" => [
            [
                $user = (new UserBuilder())->build(),
            ],
            $user,
            "wrong password",
            false,
        ];
    }

    /**
     * @group asvs
     * @dataProvider ssoLinkExpirationProvider
     * @testdox ASVS 2.2.6 - $_dataName
     */
    public function testSsoLinkExpiration(array $entitiesToPersist, User $user, bool $isWelcomeEmail, string $expectedErrorMessage): void
    {
        foreach ($entitiesToPersist as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        $this->resetPasswordService->reset($user, $isWelcomeEmail);
        $plaintextToken = $user->getPlaintextPasswordResetHash();

        $this->client->request(Request::METHOD_GET, '/password-reset-hash/'.$plaintextToken);

        $this->client->submitForm('Save', [
            'reset_password[newPassword][first]' => "Adm!nJkAenMjeasJ1321",
            'reset_password[newPassword][second]' => "Adm!nJkAenMjeasJ1321",
        ]);
        self::assertResponseRedirects('/');

        $this->client->followRedirects();
        $this->client->request(Request::METHOD_GET, '/password-reset-hash/'.$plaintextToken);
        self::assertSelectorTextContains('.sso-invalid-link', $expectedErrorMessage);
    }

    public function ssoLinkExpirationProvider(): \Generator
    {
        yield "Test 1 - Test that trying to use the same sso link is not allowed after it has been used once already" => [
            [
                $user = (new UserBuilder())->build(),
            ],
            $user,
            false, // is welcome email
            'Your link is incorrect or it has expired. You can request a new password reset from this page.', // expected error message
        ];
    }

    /**
     * @group asvs
     * @dataProvider phpSessionIdProvider
     * @testdox ASVS 3.3.1 - $_dataNAme
     */
    public function testPhpSessionId(array $entitiesToPersist, User $user): void
    {
        foreach ($entitiesToPersist as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        $this->client->request(Request::METHOD_GET, $this->urlGenerator->generate('app_login_login'));
        $this->client->submitForm('Login', [
            'user_login[email]' => $user->getEmail(),
            'user_login[password]' => 'admin123',
        ]);
        $this->client->request(Request::METHOD_GET, '/2fa');


        $this->client->submitForm('Login');

        $sessionCookieBefore = $this->client->getCookieJar()->get("MOCKSESSID");

        $this->client->request(Request::METHOD_GET, $this->urlGenerator->generate('app_dashboard_index'));
        // it means that we are at the dashboard
        self::assertSelectorTextContains('.lead', 'You have no selected scope. Either select/create one or contact your manager.');

        $this->client->request(Request::METHOD_GET, $this->urlGenerator->generate('app_login_logout'));

        $this->client->followRedirects();
        $this->client->request(Request::METHOD_GET, "/");
        // means we are logged out
        self::assertSelectorTextContains('.login-title', 'Welcome to SAMMY');

        $this->client->getCookieJar()->set($sessionCookieBefore);

        $this->expectException(AccessDeniedException::class);

        $this->client->request(Request::METHOD_GET, $this->urlGenerator->generate('app_dashboard_index'));
    }

    public function phpSessionIdProvider(): \Generator
    {
        yield "Test 1 - Test The user tries to log in with session cookie which was used last time the user logged in" => [
            [
                $user = (new UserBuilder())->build(),
            ],
            $user,
        ];
    }


}
