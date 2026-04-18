<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DTO\DocumentationDTO;
use App\Entity\Assessment;
use App\Entity\AssessmentStream;
use App\Entity\Evaluation;
use App\Entity\MaturityLevel;
use App\Entity\MaturityLevelRemark;
use App\Entity\Remark;
use App\Entity\User;
use App\Entity\Validation;
use App\Enum\AssessmentStatus;
use App\Enum\Role;
use App\Exception\InsufficientPermissionsToSaveRemarkException;
use App\Service\MetamodelService;
use App\Service\RemarkService;
use App\Tests\_support\AbstractKernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class RemarkServiceTest extends AbstractKernelTestCase
{
    /**
     * @dataProvider saveDocumentationRemarkProvider
     *
     * @testdox $_dataName
     */
    public function testSaveDocumentationRemark(DocumentationDTO $documentationDTO, AssessmentStream $assessmentStream, User $user)
    {
        /** @var RemarkService $remarkService */
        $remarkService = self::getContainer()->get(RemarkService::class);

        $remarkService->saveDocumentationRemark($documentationDTO, $assessmentStream, $user);

        $result = $this->entityManager->getRepository(Remark::class)->findOneBy(['title' => $documentationDTO->getAttachmentTitle()]);

        self::assertNotNull($result);
    }

    private function saveDocumentationRemarkProvider(): array
    {
        $container = self::getContainer();
        $parameterBag = $container->get('parameter_bag');

        // NOTE:
        // we need to create the file
        $randomNumbers = bin2hex(random_bytes(5));
        $path = $parameterBag->get('kernel.project_dir').'/private';
        file_put_contents($path.'/'.$randomNumbers.'_attachmentFile.txt', "sample content\n");

        $user = new User();
        $documentationDTO = (new DocumentationDTO());
        $documentationDTO->setAttachmentFile(new UploadedFile($path.'/'.$randomNumbers.'_attachmentFile.txt', $randomNumbers.'_attachmentFile.txt', null, null, true));
        $documentationDTO->setAttachmentTitle('random title_'.$randomNumbers);
        $assessmentStream = (new AssessmentStream())->setStatus(AssessmentStatus::IN_EVALUATION);

        return [
            'Test that a remark is being saved by saveDocumentationRemark' => [
                $documentationDTO,
                $assessmentStream,
                $user,
            ]
        ];
    }

    /**
     * @dataProvider editDocumentationRemarkProvider
     *
     * @testdox $_dataName
     */
    public function testEditDocumentationRemark(
        array $entitiesToSave,
        AssessmentStream $assessmentStream,
        User $user,
        Remark $remarkToEdit,
        string $newRemark,
        string $expectedRemark,
        ?string $expectedException
    ) {
        foreach ($entitiesToSave as $entity) {
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
        }

        $documentationDTO = new DocumentationDTO();
        $documentationDTO->setRemarkId($remarkToEdit->getId());
        $documentationDTO->setText($newRemark);

        /** @var RemarkService $remarkService */
        $remarkService = self::getContainer()->get(RemarkService::class);

        if ($expectedException !== null) {
            $this->expectException($expectedException);
        }

        $remarkService->saveDocumentationRemark($documentationDTO, $assessmentStream, $user);

        $this->entityManager->clear();

        $result = $this->entityManager->getRepository(Remark::class)->find($remarkToEdit->getId());

        $this->assertEquals($expectedRemark, $result->getText());
    }

    private function editDocumentationRemarkProvider(): array
    {
        $assessmentStream = new AssessmentStream();
        $evaluation = new Evaluation();
        $evaluation->setAssessmentStream($assessmentStream);
        $remark1 = new Remark();
        $remark1->setStage($evaluation);
        $remark1->setText("text1");
        $user = new User();
        $user->setRoles([Role::USER->string()]);
        $remark1->setUser($user);
        $user2 = new User();
        $user2->setRoles([Role::USER->string(), Role::MANAGER->string()]);
        $user3 = new User();
        $user3->setRoles([Role::USER->string()]);

        return [
            'Positive 1 => Test that a remark can be edited by own user' => [
                [$remark1, $user, $evaluation, $assessmentStream],
                $assessmentStream,
                $user,
                $remark1,
                "newText",
                "newText",
                null
            ],
            'Negative 1 => Test that a remark wont be edited by not own and not manager user' => [
                [$remark1, $user, $evaluation, $assessmentStream, $user2, $user3],
                $assessmentStream,
                $user3,
                $remark1,
                "newText",
                "text1",
                InsufficientPermissionsToSaveRemarkException::class,
            ],
            'Negative 2 => Test that a remark can be edited by manager user' => [
                [$remark1, $user, $evaluation, $assessmentStream, $user2],
                $assessmentStream,
                $user2,
                $remark1,
                "newText",
                "newText",
                InsufficientPermissionsToSaveRemarkException::class,
            ],
        ];
    }

    /**
     * @dataProvider deleteRemarkProvider
     *
     * @testdox $_dataName
     */
    public function testDeleteRemark(Remark $remark)
    {
        $this->entityManager->persist($remark);
        $this->entityManager->flush();
        $remark = $this->entityManager->getRepository(Remark::class)->findOneBy(['id' => $remark->getId()]);
        /** @var RemarkService $remarkService */
        $remarkService = self::getContainer()->get(RemarkService::class);

        $resultBefore = $this->entityManager->getRepository(Remark::class)->findBy(['id' => $remark->getId()]);

        self::assertNull($resultBefore[0]->getDeletedAt());

        $remarkService->deleteRemark($remark);

        $resultAfter = $this->entityManager->getRepository(Remark::class)->findBy(['id' => $remark->getId()]);

        self::assertNotNull($resultAfter[0]->getDeletedAt());
    }

    private function deleteRemarkProvider(): array
    {
        $container = self::getContainer();
        $parameterBag = $container->get('parameter_bag');

        $assessmentStream = (new AssessmentStream());
        $remark = (new Remark())->setFile('testFile.txt')->setStage((new Evaluation())->setAssessmentStream($assessmentStream));

        // NOTE:
        // we need to create the directory and the file for the Remark
        $path = $parameterBag->get('kernel.project_dir').'/private/'.$assessmentStream->getId();
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        $attachmentFile = fopen($path.'/'.$remark->getFile(), 'wb');
        fclose($attachmentFile);

        return [
            'Test that a remark is being deleted by deleteRemark' => [
                $remark,
            ],
        ];
    }

    /**
     * @dataProvider testGetAttachmentFilePathProvider
     *
     * @testdox $_dataName
     */
    public function testGetAttachmentFilePath(Remark $remark, string $expectedAttachmentFilePath)
    {
        /** @var RemarkService $remarkService */
        $remarkService = self::getContainer()->get(RemarkService::class);

        $result = $remarkService->getAttachmentFilePath($remark);

        self::assertEquals($expectedAttachmentFilePath, $result);
    }

    private function testGetAttachmentFilePathProvider(): array
    {
        $container = self::getContainer();
        $parameterBag = $container->get('parameter_bag');

        $assessmentStream = (new AssessmentStream());
        $remark = (new Remark())->setFile('testFile2.txt')->setStage((new Evaluation())->setAssessmentStream($assessmentStream));

        // NOTE:
        // we need to create the directory and the file for the Remark
        $path = $parameterBag->get('kernel.project_dir').'/private/'.$assessmentStream->getId();
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        $attachmentFile = fopen($path.'/'.$remark->getFile(), 'wb');
        fclose($attachmentFile);

        return [
            'Test that the attachment file path is returned correctly by getAttachmentFilePath' => [
                $remark,
                $path.$remark->getFile(),
            ],
        ];
    }

    /**
     * @dataProvider findAllCurrentAndOldRemarkLevelsProvider
     *
     * @testdox $_dataName
     */
    public function testFindAllCurrentAndOldRemarkLevels(AssessmentStream $assessmentStream, MaturityLevelRemark $maturityLevelRemark)
    {
        $this->entityManager->persist($assessmentStream);
        $this->entityManager->persist($maturityLevelRemark);
        $this->entityManager->flush();
        /** @var RemarkService $remarkService */
        $remarkService = self::getContainer()->get(RemarkService::class);

        $result = $remarkService->findAllCurrentAndOldRemarkLevels($assessmentStream);

        self::assertArrayHasKey($maturityLevelRemark->getRemark()->getId(), $result);
    }

    private function findAllCurrentAndOldRemarkLevelsProvider(): array
    {
        $evaluation = (new Evaluation());
        $stream = $this->getContainer()->get(MetamodelService::class)->getStreams()[0];
        $assessmentStream = (new AssessmentStream())->addAssessmentStreamStage($evaluation)->setStream($stream)->setAssessment(new Assessment());
        $evaluation->setAssessmentStream($assessmentStream)->setCompletedAt(new \DateTime('yesterday'));
        $maturityLevelRemark = (new MaturityLevelRemark())->setRemark((new Remark())->setFile('anotherTestFile.txt')->setStage($evaluation))->setMaturityLevel((new MaturityLevel())->setLevel(1));

        return [
            'Test that all current and old remarks levels are found by findAllCurrentAndOldRemarkLevels' => [
                $assessmentStream,
                $maturityLevelRemark, // expected remark id
            ],
        ];
    }

    /**
     * @dataProvider deleteValidationRemarkProvider
     *
     * @testdox $_dataName
     */
    public function testDeleteValidationRemark(Validation $validation)
    {
        $this->entityManager->persist($validation);
        $this->entityManager->flush();

        /** @var RemarkService $remarkService */
        $remarkService = self::getContainer()->get(RemarkService::class);
        $validation = $this->entityManager->getRepository(Validation::class)->findOneBy(['id' => $validation->getId()]);

        $remarkService->deleteValidationRemark($validation);

        $result = $this->entityManager->getRepository(Validation::class)->findBy(['id' => $validation->getId()]);

        $this->entityManager->refresh($validation);
        self::assertNull($result[0]->getComment());
    }

    private function deleteValidationRemarkProvider(): array
    {
        $validation = (new Validation())->setComment('This is a random comment');

        return [
            'Test that the validation comment is null after call to deleteValidationRemark' => [
                $validation,
            ],
        ];
    }
}
