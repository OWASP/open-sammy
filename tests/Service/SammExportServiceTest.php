<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Assessment;
use App\Entity\AssessmentAnswer;
use App\Entity\AssessmentStream;
use App\Entity\Evaluation;
use App\Entity\Group;
use App\Entity\GroupProject;
use App\Entity\Project;
use App\Entity\Question;
use App\Entity\Remark;
use App\Entity\Stream;
use App\Entity\Validation;
use App\Enum\AssessmentAnswerType;
use App\Enum\AssessmentStatus;
use App\Repository\QuestionRepository;
use App\Service\MetamodelService;
use App\Service\SammExportService;
use App\Tests\_support\AbstractKernelTestCase;

class SammExportServiceTest extends AbstractKernelTestCase
{
    private SammExportService $sammExportService;
    private MetamodelService $metamodelService;

    public function setUp(): void
    {
        parent::setUp();
        $this->sammExportService = self::getContainer()->get(SammExportService::class);
        $this->metamodelService = self::getContainer()->get(MetamodelService::class);
    }

    public function testExportProducesFile(): void
    {
        $metamodel = $this->metamodelService->getSAMM();

        // Get all questions from the DB (EAGER-loaded, no proxy issues)
        $allQuestions = self::getContainer()->get(QuestionRepository::class)->findByMetamodel($metamodel);

        // Group questions by stream
        $questionsByStream = [];
        foreach ($allQuestions as $question) {
            $streamId = $question->getActivity()->getStream()->getId();
            $questionsByStream[$streamId]['stream'] = $question->getActivity()->getStream();
            $questionsByStream[$streamId]['questions'][] = $question;
        }

        // Take first 6 streams
        $streamGroups = array_values(array_slice($questionsByStream, 0, 6));

        // Create project with group
        $project = new Project();
        $project->setName('Test SAMM Export Project');
        $project->setMetamodel($metamodel);

        $assessment = new Assessment();
        $assessment->setProject($project);
        $project->setAssessment($assessment);

        $group = new Group();
        $group->setName('OWASP Test Org');

        $groupProject = new GroupProject();
        $groupProject->setGroup($group);
        $groupProject->setProject($project);

        $this->persistEntities($project, $assessment, $group, $groupProject);

        // Create assessment streams with answers
        $createdStreams = [];
        foreach ($streamGroups as $i => $streamGroup) {
            /** @var Stream $stream */
            $stream = $streamGroup['stream'];
            /** @var Question[] $questions */
            $questions = $streamGroup['questions'];

            $assessmentStream = new AssessmentStream();
            $assessmentStream->setStream($stream);
            $assessmentStream->setAssessment($assessment);
            $assessmentStream->setStatus(AssessmentStatus::IN_EVALUATION);
            $assessment->addAssessmentAssessmentStream($assessmentStream);

            $evaluation = new Evaluation();
            $evaluation->setAssessmentStream($assessmentStream);
            $assessmentStream->addAssessmentStreamStage($evaluation);

            $this->entityManager->persist($assessmentStream);
            $this->entityManager->persist($evaluation);

            foreach ($questions as $question) {
                $answerOptions = $question->getAnswerSet()->getAnswerSetAnswers();
                $answer = count($answerOptions) > 1 ? $answerOptions[1] : $answerOptions[0];

                $assessmentAnswer = new AssessmentAnswer();
                $assessmentAnswer->setQuestion($question);
                $assessmentAnswer->setAnswer($answer);
                $assessmentAnswer->setStage($evaluation);
                $assessmentAnswer->setType(AssessmentAnswerType::CURRENT);

                $this->entityManager->persist($assessmentAnswer);
                $evaluation->addStageAssessmentAnswer($assessmentAnswer);
            }

            // Add a COMMENT remark on first 2 streams
            if ($i < 2) {
                $remark = new Remark();
                $remark->setText('Evaluation comment for stream ' . $stream->getNameKey());
                $remark->setStage($evaluation);
                $this->entityManager->persist($remark);
            }

            $createdStreams[] = ['stream' => $stream, 'assessmentStream' => $assessmentStream];
        }

        $this->entityManager->flush();

        // Add a validation stage with comment on the third stream
        $thirdAssessmentStream = $createdStreams[2]['assessmentStream'];
        $thirdAssessmentStream->setStatus(AssessmentStatus::IN_VALIDATION);
        $validation = new Validation();
        $validation->setAssessmentStream($thirdAssessmentStream);
        $validation->setComment('Validation feedback: verified implementation');
        $thirdAssessmentStream->addAssessmentStreamStage($validation);
        $this->entityManager->persist($validation);
        $this->entityManager->flush();

        // Export
        $json = $this->sammExportService->export($assessment);

        // Write file to disk for manual testing in paid Sammy
        $outputPath = $this->parameterBag->get('kernel.project_dir') . '/private/exports';
        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0777, true);
        }
        $filePath = $outputPath . '/test_export.samm.json';
        file_put_contents($filePath, $json);

        // Verify output
        self::assertNotEmpty($json);
        $data = json_decode($json, true);
        self::assertNotNull($data);

        // Core structure
        self::assertEquals('1.0.0', $data['formatVersion']);
        self::assertArrayHasKey('assessment', $data);
        self::assertEquals('1.0.0', $data['assessment']['version']);
        self::assertEquals('OWASP Test Org', $data['assessment']['organization']);
        self::assertEquals('Test SAMM Export Project', $data['assessment']['scope']);
        self::assertEquals((new \DateTime('now'))->format('Y-m-d'), $data['assessment']['date']);

        // Answers
        self::assertNotEmpty($data['assessment']['answers']);
        foreach ($data['assessment']['answers'] as $answer) {
            self::assertArrayHasKey('questionCode', $answer);
            self::assertArrayHasKey('answerScore', $answer);
            self::assertMatchesRegularExpression('/^[A-Z]-[A-Z]{2}-[A-Z]-\d$/', $answer['questionCode']);
        }

        // Extensions
        self::assertArrayHasKey('extensions', $data);

        $frameworkExt = null;
        $remarksExt = null;
        foreach ($data['extensions'] as $ext) {
            if ($ext['name'] === 'Assessment Framework') {
                $frameworkExt = $ext;
            }
            if ($ext['name'] === 'Assessment Stream Remarks') {
                $remarksExt = $ext;
            }
        }

        self::assertNotNull($frameworkExt);
        self::assertEquals('1.0.0', $frameworkExt['version']);
        self::assertNotEmpty($frameworkExt['assessmentFramework']);

        // Remarks extension
        self::assertNotNull($remarksExt, 'Remarks extension should be present');
        self::assertNotEmpty($remarksExt['assessmentStreamRemarks']);

        // Check remark types
        $hasComment = false;
        $hasValidation = false;
        foreach ($remarksExt['assessmentStreamRemarks'] as $streamRemarks) {
            foreach ($streamRemarks as $remark) {
                self::assertArrayHasKey('text', $remark);
                self::assertArrayHasKey('type', $remark);
                self::assertContains($remark['type'], ['COMMENT', 'VALIDATION', 'RECOMMENDATION']);
                if ($remark['type'] === 'COMMENT') {
                    $hasComment = true;
                }
                if ($remark['type'] === 'VALIDATION') {
                    $hasValidation = true;
                }
            }
        }
        self::assertTrue($hasComment, 'Should have COMMENT remarks from evaluation stages');
        self::assertTrue($hasValidation, 'Should have VALIDATION remarks from validation stages');

        // Verify the exact validation comment text made it through
        $thirdStreamCode = $createdStreams[2]['stream']->getNameKey();
        self::assertArrayHasKey($thirdStreamCode, $remarksExt['assessmentStreamRemarks']);
        $thirdStreamRemarks = $remarksExt['assessmentStreamRemarks'][$thirdStreamCode];
        $validationTexts = array_column(
            array_filter($thirdStreamRemarks, fn($r) => $r['type'] === 'VALIDATION'),
            'text'
        );
        self::assertContains('Validation feedback: verified implementation', $validationTexts);

        // Output path for user reference
        echo "\n\nExported .samm file: {$filePath}\n";
    }
}
