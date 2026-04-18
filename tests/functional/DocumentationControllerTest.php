<?php

declare(strict_types=1);

namespace App\Tests\functional;

use App\Entity\Group;
use App\Entity\GroupProject;
use App\Entity\GroupUser;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\Role;
use App\Repository\AssessmentStreamRepository;
use App\Service\AssessmentService;
use App\Service\MetamodelService;
use App\Tests\_support\AbstractWebTestCase;
use App\Tests\builders\UserBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class DocumentationControllerTest extends AbstractWebTestCase
{
    /**
     * @group asvs
     * @group security
     * @dataProvider documentationPageEndpointDOAProvider
     * @testdox Access Control(v4.0.3-4.2.1) $_dataName
     */
    public function testDocumentationPageEndpointsDOA(User $user, bool $expectedAccess): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $group = (new Group());
        $project = (new Project());
        $assessment = self::getContainer()->get(AssessmentService::class)->createAssessment($project);
        $project->setMetamodel(self::getContainer()->get(MetamodelService::class)->getSAMM());
        $group->addGroupGroupProject((new GroupProject())->setProject($project)->setGroup($group));
        $user->addUserGroupUser((new GroupUser())->setUser($user)->setGroup($group));
        $this->entityManager->flush();

        if ($expectedAccess) {
            $group->addGroupGroupUser((new GroupUser())->setUser($user)->setGroup($group));
        }
        $group->addGroupGroupProject((new GroupProject())->setProject($project)->setGroup($group));

        $assessmentStream = self::getContainer()->get(AssessmentStreamRepository::class)
            ->findOneBy([
                "assessment" => $assessment,
            ]);

        $this->entityManager->flush();

        if (!$expectedAccess) {
            $this->expectException(AccessDeniedException::class);
        }

        $this->client->loginUser($user, "boardworks");

        $this->client->request("GET", $this->urlGenerator->generate("app_documentation_documentation_page", ['id' => $assessmentStream->getId()]));

        $responseCode = $this->client->getResponse()->getStatusCode();
        self::assertEquals(200, $responseCode);
    }

    public function documentationPageEndpointDOAProvider(): array
    {
        $user = (new UserBuilder())->build();

        return [
            "Positive 1 - Test that access to '/documentation-page/{id}' is allowed for a user who is in the organization" => [
                $user, //
                true, // expected access
            ],
        ];
    }

    /**
     * @group asvs
     * @group security
     * @dataProvider saveDocumentationEndpointDOAProvider
     * @testdox Access Control(v4.0.3-4.2.1) $_dataName
     */
    public function testSaveDocumentationEndpointsDOA(User $user, int $expectedStatusCode): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $project = (new Project())->setName("test project");
        $project->setMetamodel(self::getContainer()->get(MetamodelService::class)->getSAMM());
        $group = (new Group());
        $group->addGroupGroupUser((new GroupUser())->setUser($user)->setGroup($group));
        $group->addGroupGroupProject((new GroupProject())->setProject($project)->setGroup($group));
        $user->addUserGroupUser((new GroupUser())->setUser($user)->setGroup($group));
        $this->entityManager->flush();

        $assessment = self::getContainer()->get(AssessmentService::class)->createAssessment($project);
        $assessmentStream = self::getContainer()->get(AssessmentStreamRepository::class)
            ->findOneBy([
                "assessment" => $assessment,
            ]);

        $payload = [
            'documentation' => [
                'text' => 'testdocument',
                'assessmentStream' => $assessmentStream->getId(),
            ],
        ];

        if ($expectedStatusCode === Response::HTTP_FORBIDDEN) {
            $this->expectException(AccessDeniedException::class);
        }

        $this->client->loginUser($user, "boardworks");

        $this->client->request("POST", $this->urlGenerator->generate("app_documentation_save_documentation", ['id' => $assessmentStream->getId()]), $payload);

        $responseCode = $this->client->getResponse()->getStatusCode();
        self::assertEquals($expectedStatusCode, $responseCode);
    }

    public function saveDocumentationEndpointDOAProvider(): array
    {
        $userRoleUser = (new UserBuilder())->build();
        $userRoleUserEvaluatorValidatorImproverManager = (new UserBuilder())->withRoles([
            Role::USER->string(),
            Role::EVALUATOR->string(),
            Role::VALIDATOR->string(),
            Role::IMPROVER->string(),
            Role::MANAGER->string(),
        ])->build();

        $userRoleUserAndEvaluator = (new UserBuilder())->withRoles([Role::USER->string(), Role::EVALUATOR->string()])->build();

        $userRoleUserAndValidator = (new UserBuilder())->withRoles([Role::USER->string(), Role::VALIDATOR->string()])->build();

        $userRoleUserAndImprover = (new UserBuilder())->withRoles([Role::USER->string(), Role::IMPROVER->string()])->build();

        $userRoleUserAndManager = (new UserBuilder())->withRoles([Role::USER->string(), Role::MANAGER->string()])->build();

        $user = (new UserBuilder())->build();


        return [
            "Positive 1 - Test that save to '/save-documentation/{id}' is allowed for user who has role user and is evaluator,validator,improver,manager" => [
                $userRoleUserEvaluatorValidatorImproverManager, // user
                Response::HTTP_OK, // expected status code
            ],
            "Positive 2 - Test that save to '/save-documentation/{id}' is allowed for user who has role user and is evaluator" => [
                $userRoleUserAndEvaluator, // user
                Response::HTTP_OK, // expected status code
            ],
            "Positive 3 - Test that save to '/save-documentation/{id}' is allowed for user who has role user and is validator" => [
                $userRoleUserAndValidator, // user
                Response::HTTP_OK, // expected status code
            ],
            "Positive 4 - Test that save to '/save-documentation/{id}' is allowed for user who has role user and is improver" => [
                $userRoleUserAndImprover, // user
                Response::HTTP_OK, // expected status code
            ],
            "Positive 5 - Test that save to '/save-documentation/{id}' is allowed for user who has role user and is manager" => [
                $userRoleUserAndManager, // user
                Response::HTTP_OK, // expected status code
            ],
            "Negative 1 - Test that save to '/save-documentation/{id}' is not allowed for user who has role user and does not have any other roles" => [
                $userRoleUser, // user
                Response::HTTP_FORBIDDEN, // expected status code
            ],
            "Negative 2 - Test that save to '/save-documentation/{id}' is not allowed for user who does not have role user and does not have any other roles" => [
                $user, // user
                Response::HTTP_FORBIDDEN, // expected status code
            ],
        ];
    }

    /**
     * @group pentestFindings22v1
     * @dataProvider testDocumentationPageAccessOtherGroupProvider
     * @testdox Group voter check - attempt to access group data $_dataName
     */
    public function testDocumentationPageAccessOtherGroup(User $testUser, int $expectedStatusCode): void
    {
        $this->entityManager->persist($testUser);
        $this->entityManager->flush();

        $project = (new Project());
        $assessment = self::getContainer()->get(AssessmentService::class)->createAssessment($project);
        $project->setMetamodel(self::getContainer()->get(MetamodelService::class)->getSAMM());
        $assessmentStream = self::getContainer()->get(AssessmentStreamRepository::class)
            ->findOneBy([
                "assessment" => $assessment,
            ]);

        $this->expectException(AccessDeniedException::class);

        $this->client->loginUser($testUser, "boardworks");
        $this->client->request(
            Request::METHOD_GET,
            $this->urlGenerator->generate(
                'app_documentation_documentation_page',
                [
                    'id' => $assessmentStream->getId(),
                ]
            )
        );
    }

    private function testDocumentationPageAccessOtherGroupProvider(): array
    {
        $user1 = (new UserBuilder())->withRoles([Role::USER->string(), Role::EVALUATOR->string()])->build();
        $user2 = (new UserBuilder())->withRoles([Role::USER->string(), Role::EVALUATOR->string()])->build();


        return [
            "Positive 1 - User1 from Group 1 tries to see documentation of Group 1" => [
                $user1, // user
                Response::HTTP_OK, //expected code
            ],
            "Negative 1 - User2 from Group 2 tries to see documentation of Group 1" => [
                $user2, // user
                Response::HTTP_FORBIDDEN, //expected code
            ],
        ];
    }

    /**
     * @group pentestFindings22v1
     * @dataProvider saveDocumentationToOtherGroupProvider
     * @testdox Group voter check - attempt to save remark to other group $_dataName
     */
    public function testSaveDocumentationToOtherGroup(User $testUser): void
    {
        $this->entityManager->persist($testUser);
        $this->entityManager->flush();

        $project = (new Project());
        $assessment = self::getContainer()->get(AssessmentService::class)->createAssessment($project);
        $project->setMetamodel(self::getContainer()->get(MetamodelService::class)->getSAMM());
        $assessmentStream = self::getContainer()->get(AssessmentStreamRepository::class)
            ->findOneBy([
                "assessment" => $assessment,
            ]);

        $this->expectException(AccessDeniedException::class);

        $this->client->loginUser($testUser, "boardworks");
        $this->client->request(
            Request::METHOD_POST,
            $this->urlGenerator->generate(
                'app_documentation_save_documentation',
                [
                    'id' => $assessmentStream->getId(),
                ]
            ),
            [
                'documentation' => [
                    'ckeditor' => 'fakeText',
                ],
            ]
        );
    }

    public function saveDocumentationToOtherGroupProvider(): array
    {
        $user1 = (new UserBuilder())->withRoles([Role::USER->string(), Role::EVALUATOR->string()])->build();
        $user2 = (new UserBuilder())->withRoles([Role::USER->string(), Role::EVALUATOR->string()])->build();


        return [
            "Positive 1 - User1 from Group 1 tries to save documentation to Group 1" => [
                $user1, // user
                Response::HTTP_OK, //expected code
            ],
            "Negative 1 - User2 from Group 2 tries to save documentation to Group 1" => [
                $user2, // user
                Response::HTTP_FORBIDDEN, //expected code
            ],
        ];
    }

}