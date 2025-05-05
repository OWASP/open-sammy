<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Assessment;
use App\Entity\AssessmentAnswer;
use App\Entity\AssessmentStream;
use App\Entity\Evaluation;
use App\Entity\Improvement;
use App\Entity\Metamodel;
use App\Entity\Project;
use App\Entity\Question;
use App\Entity\Validation;
use App\Enum\AssessmentStatus;
use App\Enum\ValidationStatus;
use App\Repository\AssessmentStreamRepository;
use App\Repository\QuestionRepository;
use App\Service\AssessmentService;
use App\Service\MetamodelService;
use App\Service\ScoreService;
use App\Tests\_support\AbstractKernelTestCase;

class ScoreServiceTest extends AbstractKernelTestCase
{
    private ScoreService $scoreService;
    private Metamodel $sammMetamodel;
    private Metamodel $dsommMetamodel;

    public function setUp(): void
    {
        parent::setUp();
        $this->scoreService = self::getContainer()->get(ScoreService::class);
        $this->sammMetamodel = $this->entityManager->getReference(Metamodel::class, 1);
        $this->dsommMetamodel = $this->entityManager->getReference(Metamodel::class, 2);
    }

    private function getDSOMMAssessmentWithAnswers()
    {
        /** @var QuestionRepository $questionRepository */
        $questionRepository = self::getContainer()->get(QuestionRepository::class);

        /** @var Question $dsommQuestion1 */
        $dsommQuestion1 = $questionRepository->findOneBy(['externalId' => 'f6f7737f-25a9-4317-8de2-09bf59f29b5b']); // Build 1st question
        /** @var Question $dsommQuestion2 */
        $dsommQuestion2 = $questionRepository->findOneBy(['externalId' => 'a340f46b-6360-4cb8-847b-a0d3483d09d3']);// Build 2nd question
        /** @var Question $dsommQuestion3 */
        $dsommQuestion3 = $questionRepository->findOneBy(['externalId' => 'f3c4971e-9f4d-4e59-8ed0-f0bdb6262477']); // Build 3rd question
        /** @var Question $dsommQuestion4 */
        $dsommQuestion4 = $questionRepository->findOneBy(['externalId' => '2858ac12-0179-40d9-9acf-1b839c030473']); // Build 4th question
        /** @var Question $dsommQuestion5 */
        $dsommQuestion5 = $questionRepository->findOneBy(['externalId' => '9f107927-61e9-4574-85ad-3f2b4bca8665']); // Build 5th question
        /** @var Question $dsommQuestion6 */
        $dsommQuestion6 = $questionRepository->findOneBy(['externalId' => '5786959d-0c6f-46a6-8e1c-a32ff1a50222']); // Build 6th question

        ($assessment = new Assessment())->setProject((new Project())->setMetamodel($this->dsommMetamodel));
        ($assessmentStream1 = new AssessmentStream())->setAssessment($assessment)->setStream($dsommQuestion1->getActivity()->getStream()); // Build
        ($evaluation = new Evaluation())->setAssessmentStream($assessmentStream1)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation)
                    ->setAnswer($dsommQuestion1->getAnswerSet()->getAnswerSetAnswers()[0])->setQuestion($dsommQuestion1)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            )->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation)
                    ->setAnswer($dsommQuestion2->getAnswerSet()->getAnswerSetAnswers()[0])->setQuestion($dsommQuestion2)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            )->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation)
                    ->setAnswer($dsommQuestion3->getAnswerSet()->getAnswerSetAnswers()[0])->setQuestion($dsommQuestion3)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            )->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation)
                    ->setAnswer($dsommQuestion4->getAnswerSet()->getAnswerSetAnswers()[0])->setQuestion($dsommQuestion4)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            )->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation)
                    ->setAnswer($dsommQuestion5->getAnswerSet()->getAnswerSetAnswers()[0])->setQuestion($dsommQuestion5)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            )->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation)
                    ->setAnswer($dsommQuestion6->getAnswerSet()->getAnswerSetAnswers()[1])->setQuestion($dsommQuestion6)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            );

        ($validation = new Validation())->setStatus(ValidationStatus::ACCEPTED)->setAssessmentStream($assessmentStream1)->setCompletedAt(new \DateTime());
        ($improvement = new Improvement())->setAssessmentStream($assessmentStream1)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($improvement)
                    ->setAnswer($dsommQuestion6->getAnswerSet()->getAnswerSetAnswers()[0])->setQuestion($dsommQuestion6)
                    ->setType(\App\Enum\AssessmentAnswerType::DESIRED)
            );
        $assessmentStream1->addAssessmentStreamStage($evaluation)->addAssessmentStreamStage($validation)->addAssessmentStreamStage($improvement);
        $assessmentStream1->setStatus(\App\Enum\AssessmentStatus::IN_IMPROVEMENT);

        $assessment->addAssessmentAssessmentStream($assessmentStream1);

        return $assessment;
    }


    private function getAssessmentWithAnswers()
    {
        /** @var QuestionRepository $questionRepository */
        $questionRepository = self::getContainer()->get(QuestionRepository::class);

        /** @var Question $sammQuestion1 */
        $sammQuestion1 = $questionRepository->findOneBy(['externalId' => '47c8fb0cae5944d090d7f73f7632dc9f']); // D-SA-B-1 (id: 2)
        /** @var Question $sammQuestion2 */
        $sammQuestion2 = $questionRepository->findOneBy(['externalId' => '1962ef9fe4cf488a8d10ccbcdc8bb926']); // G-EG-A-2 (id: 21)
        /** @var Question $sammQuestion2B */
        $sammQuestion2B = $questionRepository->findOneBy(['externalId' => 'fe0485b5026d4b2b9a7c99260addc912']); // G-EG-B-2 (id: 22)
        /** @var Question $sammQuestion3 */
        $sammQuestion3 = $questionRepository->findOneBy(['externalId' => '6b5eac7b9e2f49e2a2cda600ef70ad99']); // I-DM-A-3 (id: 41)
        /** @var Question $sammQuestion4 */
        $sammQuestion4 = $questionRepository->findOneBy(['externalId' => '1aa1ec270db6496d8229e094fdbbc6e5']); // O-IM-A-1 (id: 61)

        ($assessment = new Assessment())->setProject((new Project())->setMetamodel($this->sammMetamodel));
        ($assessmentStream1 = new AssessmentStream())->setAssessment($assessment)->setStream($sammQuestion1->getActivity()->getStream()); // D-SA-B
        ($evaluation = new Evaluation())->setAssessmentStream($assessmentStream1)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation)
                    ->setAnswer($sammQuestion1->getAnswerSet()->getAnswerSetAnswers()[2])->setQuestion($sammQuestion1)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            );
        ($validation = new Validation())->setStatus(ValidationStatus::ACCEPTED)->setAssessmentStream($assessmentStream1)->setCompletedAt(new \DateTime());
        ($improvement = new Improvement())->setAssessmentStream($assessmentStream1)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($improvement)
                    ->setAnswer($sammQuestion1->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion1)
                    ->setType(\App\Enum\AssessmentAnswerType::DESIRED)
            );
        $assessmentStream1->addAssessmentStreamStage($evaluation)->addAssessmentStreamStage($validation)->addAssessmentStreamStage($improvement);
        $assessmentStream1->setStatus(\App\Enum\AssessmentStatus::IN_IMPROVEMENT);

        ($assessmentStream2 = new AssessmentStream())->setAssessment($assessment)->setStream($sammQuestion2->getActivity()->getStream()); // G-EG-A
        ($evaluation2 = new Evaluation())->setAssessmentStream($assessmentStream2)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation2)
                    ->setAnswer($sammQuestion2->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion2)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            );
        ($validation2 = new Validation())->setStatus(ValidationStatus::ACCEPTED)->setAssessmentStream($assessmentStream2)->setCompletedAt(new \DateTime());
        ($improvement2 = new Improvement())->setAssessmentStream($assessmentStream2)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($improvement2)
                    ->setAnswer($sammQuestion2->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion2)
                    ->setType(\App\Enum\AssessmentAnswerType::DESIRED)
            );
        $assessmentStream2->addAssessmentStreamStage($evaluation2)->addAssessmentStreamStage($validation2)->addAssessmentStreamStage($improvement2);
        $assessmentStream2->setStatus(\App\Enum\AssessmentStatus::IN_IMPROVEMENT);

        ($assessmentStream2B = new AssessmentStream())->setAssessment($assessment)->setStream($sammQuestion2B->getActivity()->getStream()); // G-EG-B
        ($evaluation2B = new Evaluation())->setAssessmentStream($assessmentStream2B)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation2B)
                    ->setAnswer($sammQuestion2B->getAnswerSet()->getAnswerSetAnswers()[0])->setQuestion($sammQuestion2B)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            );
        ($validation2B = new Validation())->setStatus(ValidationStatus::AUTO_ACCEPTED)->setAssessmentStream($assessmentStream2B)->setCompletedAt(new \DateTime());
        ($improvement2B = new Improvement())->setAssessmentStream($assessmentStream2B)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($improvement2B)
                    ->setAnswer($sammQuestion2B->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion2B)
                    ->setType(\App\Enum\AssessmentAnswerType::DESIRED)
            );
        $assessmentStream2B->addAssessmentStreamStage($evaluation2B)->addAssessmentStreamStage($validation2B)->addAssessmentStreamStage($improvement2B);
        $assessmentStream2B->setStatus(\App\Enum\AssessmentStatus::IN_IMPROVEMENT);

        ($assessmentStream3 = new AssessmentStream())->setAssessment($assessment)->setStream($sammQuestion3->getActivity()->getStream()); // I-DM-A
        ($evaluation3 = new Evaluation())->setAssessmentStream($assessmentStream3)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation3)
                    ->setAnswer($sammQuestion3->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion3)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            );
        ($validation3 = new Validation())->setStatus(ValidationStatus::RETRACTED)->setAssessmentStream($assessmentStream3)->setCompletedAt(new \DateTime());
        $assessmentStream3->addAssessmentStreamStage($evaluation3)->addAssessmentStreamStage($validation3);
        $assessmentStream3->setStatus(\App\Enum\AssessmentStatus::VALIDATED);

        ($evaluation3B = new Evaluation())->setAssessmentStream($assessmentStream3)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation3B)
                    ->setAnswer($sammQuestion3->getAnswerSet()->getAnswerSetAnswers()[1])->setQuestion($sammQuestion3)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            );
        ($validation3B = new Validation())->setStatus(ValidationStatus::ACCEPTED)->setAssessmentStream($assessmentStream3)->setCompletedAt(new \DateTime());
        $assessmentStream3->addAssessmentStreamStage($evaluation3B)->addAssessmentStreamStage($validation3B);

        ($assessmentStream4 = new AssessmentStream())->setAssessment($assessment)->setStream($sammQuestion4->getActivity()->getStream()) // O-IM-A
        ->setStatus(\App\Enum\AssessmentStatus::IN_VALIDATION);
        ($evaluation4 = new Evaluation())->setAssessmentStream($assessmentStream4)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation4)
                    ->setAnswer($sammQuestion4->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion4)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            );
        ($validation4 = new Validation())->setStatus(ValidationStatus::NEW)->setAssessmentStream($assessmentStream4)->setCompletedAt(new \DateTime());
        $assessmentStream4->addAssessmentStreamStage($evaluation4)->addAssessmentStreamStage($validation4);

        $assessment->addAssessmentAssessmentStream($assessmentStream1)
            ->addAssessmentAssessmentStream($assessmentStream2)
            ->addAssessmentAssessmentStream($assessmentStream2B)
            ->addAssessmentAssessmentStream($assessmentStream3)
            ->addAssessmentAssessmentStream($assessmentStream4);

        return $assessment;
    }

    // I am reusing things here as the whole data setup is rather complicated
    public function testCurrentAndProjectedScoresDSOMM()
    {
        $assessment = $this->getDSOMMAssessmentWithAnswers();
        $this->entityManager->persist($assessment);
        $this->entityManager->flush();
        $scores = $this->scoreService->getScoresByAssessment($assessment);
        self::assertEquals(0.83, number_format($scores['securityPractice']['B'], 2));
        self::assertEquals(0, $scores['securityPractice']['D']);
        self::assertEquals(0, $scores['securityPractice']['PM']);
        self::assertEquals(0, $scores['securityPractice']['EAG']);
        self::assertEquals(0.28, number_format($scores['businessFunction']['Build and Deployment'], 2));
        self::assertEquals(0.0, $scores['businessFunction']['Implementation']);
        $scores = $this->scoreService->getProjectedScoresByAssessment($assessment);
        self::assertEquals(1.00, number_format($scores['securityPractice']['B'], 2));
        self::assertEquals(0.33, number_format($scores['businessFunction']['Build and Deployment'], 2));
        self::assertEquals(0.0, $scores['businessFunction']['Implementation']);
    }

    // I am reusing things here as the whole data setup is rather complicated
    public function testCurrentAndProjectedScores()
    {
        $assessment = $this->getAssessmentWithAnswers();
        $this->entityManager->persist($assessment);
        $this->entityManager->flush();
        $scores = $this->scoreService->getScoresByAssessment($assessment);

        self::assertEquals(1 / 4, $scores['securityPractice']['SA']);
        self::assertEquals(0, $scores['securityPractice']['TA']);
        self::assertEquals(1 / 2, $scores['securityPractice']['EG']);
        self::assertEquals(1 / 8, $scores['securityPractice']['DM']);
        self::assertEquals(1 / 12, $scores['businessFunction']['Design']);
        self::assertEquals(0, $scores['businessFunction']['Verification']);
        self::assertEquals(1 / 24, $scores['businessFunction']['Implementation']);
        self::assertEquals(1 / 6, $scores['businessFunction']['Governance']);
        self::assertEquals(0, $scores['businessFunction']['Operations']);
        $scores = $this->scoreService->getProjectedScoresByAssessment($assessment);
        self::assertEquals(1 / 2, $scores['securityPractice']['SA']);
        self::assertEquals(0, $scores['securityPractice']['TA']);
        self::assertEquals(1, $scores['securityPractice']['EG']);
        self::assertEquals(1 / 8, $scores['securityPractice']['DM']);
        self::assertEquals(1 / 6, $scores['businessFunction']['Design']);
        self::assertEquals(0, $scores['businessFunction']['Verification']);
        self::assertEquals(1 / 24, $scores['businessFunction']['Implementation']);
        self::assertEquals(1 / 3, $scores['businessFunction']['Governance']);
        self::assertEquals(0, $scores['businessFunction']['Operations']);
    }

    // I am reusing things here as the whole data setup is rather complicated
    public function testGetDetailedScoresByAssessments()
    {
        $assessment = $this->getAssessmentWithAnswers();
        $this->entityManager->persist($assessment);
        $this->entityManager->flush();
        $scores = $this->scoreService->getDetailedScoresByAssessment($assessment);

        self::assertEquals(1 / 2, $scores['2']);
        self::assertEquals(1, $scores['21']);
        self::assertEquals(0, $scores['22']);
        self::assertEquals(1 / 4, $scores['41']);
        self::assertEquals(0, $scores['61']);

        $scores = $this->scoreService->getDetailedScoresByAssessment($assessment, validated: false);

        self::assertEquals(1 / 2, $scores['2']);
        self::assertEquals(1, $scores['21']);
        self::assertEquals(0, $scores['22']);
        self::assertEquals(1 / 4, $scores['41']);
        self::assertEquals(1, $scores['61']);
    }

    public function testGetStreamScores()
    {
        /** @var Question $sammQuestion1 */
        $sammQuestion1 = $this->getObjectOfClass(Question::class, 1);
        $sammQuestion2 = $this->getObjectOfClass(Question::class, 2);
        $sammQuestion3 = $this->getObjectOfClass(Question::class, 3);

        ($assessment = new Assessment())->setProject(($project = new Project())->setMetamodel($this->sammMetamodel)->setName('Codific')->setAssessment($assessment));
        ($assessmentStream1 = new AssessmentStream())->setAssessment($assessment)->setStream($sammQuestion1->getActivity()->getStream())->setStatus(AssessmentStatus::VALIDATED);
        ($evaluation = new Evaluation())->setAssessmentStream($assessmentStream1)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation)
                    ->setAnswer($sammQuestion1->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion1)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            );
        ($validation = new Validation())->setStatus(ValidationStatus::ACCEPTED)->setAssessmentStream($assessmentStream1)->setCompletedAt(new \DateTime());
        $assessmentStream1->addAssessmentStreamStage($evaluation)->addAssessmentStreamStage($validation);
        $assessment->addAssessmentAssessmentStream($assessmentStream1);

        ($assessmentStream2 = new AssessmentStream())->setAssessment($assessment)->setStream($sammQuestion2->getActivity()->getStream())->setStatus(AssessmentStatus::VALIDATED);
        ($evaluation2 = new Evaluation())->setAssessmentStream($assessmentStream2)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation2)
                    ->setAnswer($sammQuestion2->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion2)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            );
        ($validation2 = new Validation())->setStatus(ValidationStatus::RETRACTED)->setAssessmentStream($assessmentStream2)->setCompletedAt(new \DateTime());

        ($evaluation3 = new Evaluation())->setAssessmentStream($assessmentStream2)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation3)
                    ->setAnswer($sammQuestion2->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion2)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            );
        ($validation3 = new Validation())->setStatus(ValidationStatus::ACCEPTED)->setAssessmentStream($assessmentStream2)->setCompletedAt(new \DateTime());

        $assessmentStream2->addAssessmentStreamStage($evaluation2)->addAssessmentStreamStage($validation2)->addAssessmentStreamStage($evaluation3)->addAssessmentStreamStage($validation3);
        $assessment->addAssessmentAssessmentStream($assessmentStream2);

        ($assessmentStream4 = new AssessmentStream())->setAssessment($assessment)->setStream($sammQuestion3->getActivity()->getStream())->setStatus(AssessmentStatus::IN_EVALUATION);
        ($evaluation4 = new Evaluation())->setAssessmentStream($assessmentStream4)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation4)
                    ->setAnswer($sammQuestion3->getAnswerSet()->getAnswerSetAnswers()[1])->setQuestion($sammQuestion3)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            );

        $assessmentStream4->addAssessmentStreamStage($evaluation4);
        $assessment->addAssessmentAssessmentStream($assessmentStream4);

        $this->entityManager->persist($assessment);
        $this->entityManager->flush();
        $scores = $this->scoreService->getProjectScores(new \DateTime(), true, $project);
        self::assertEquals(round(1 / 15, 2), current($scores)['arithmeticMean']);
        self::assertEquals('Codific', current($scores)['projectName']);
    }

    public function testGetValidatedStreamWeights(): void
    {
        $questionRepository = self::getContainer()->get(QuestionRepository::class);
        $assessmentService = self::getContainer()->get(AssessmentService::class);

        /** @var Question $sammQuestion1 */
        $sammQuestion1 = $questionRepository->find(1);
        /** @var Question $sammQuestion2 */
        $sammQuestion2 = $questionRepository->find(20);

        $project1 = new Project();
        $project2 = new Project();
        $project1->setTemplateProject($project2);
        $project2->setTemplate(true);

        $sammMetamodel = self::getContainer()->get(MetamodelService::class)->getSAMM();

        $project1->setMetamodel($sammMetamodel);
        $project2->setMetamodel($sammMetamodel);

        $this->entityManager->persist($project1);
        $this->entityManager->persist($project2);
        $this->entityManager->flush();

        /** @var Assessment $assessment1 */
        $assessment1 = $assessmentService->createAssessment($project1);
        /** @var Assessment $assessment2 */
        $assessment2 = $assessmentService->createAssessment($project2);

        $assessmentStreamRepository = self::getContainer()->get(AssessmentStreamRepository::class);

        $assessmentStreams = $assessmentStreamRepository->findBy(['assessment' => $assessment1]);

        /** @var AssessmentStream $assessmentStream */
        $assessmentStream = $assessmentStreams[1];

        ($evaluation = new Evaluation())->setAssessmentStream($assessmentStream)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation)
                    ->setAnswer($sammQuestion1->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion1)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            );
        $validation = (new Validation())->setStatus(ValidationStatus::ACCEPTED)->setAssessmentStream($assessmentStream)->setCompletedAt(new \DateTime());

        $assessmentStream->addAssessmentStreamStage($validation);
        $assessmentStream->setStatus(AssessmentStatus::VALIDATED);

        /** @var AssessmentStream $assessmentStream2 */
        $assessmentStream2 = $assessmentStreams[2];
        ($evaluation2 = new Evaluation())->setAssessmentStream($assessmentStream2)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation2)
                    ->setAnswer($sammQuestion1->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion1)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            );
        $assessmentStream2->addAssessmentStreamStage($evaluation2);
        $assessmentStream2->setStatus(AssessmentStatus::IN_EVALUATION);

        $targetPostureAssessmentStreams = $assessmentStreamRepository->findBy(['assessment' => $assessment2]);

        /** @var AssessmentStream $targetPostureAssessmentStream */
        $targetPostureAssessmentStream = $targetPostureAssessmentStreams[1];
        $targetPostureAssessmentStream->setStatus(AssessmentStatus::IN_EVALUATION);
        ($targetPostureEvaluation = new Evaluation())->setAssessmentStream($targetPostureAssessmentStream)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($targetPostureEvaluation)
                    ->setAnswer($sammQuestion1->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion1)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            )
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($targetPostureEvaluation)
                    ->setAnswer($sammQuestion2->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion1)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            )
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($targetPostureEvaluation)
                    ->setAnswer($sammQuestion2->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion1)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            );
        $targetPostureAssessmentStream->addAssessmentStreamStage($targetPostureEvaluation);
        $assessment2->addAssessmentAssessmentStream($targetPostureAssessmentStream);

        $this->entityManager->persist($evaluation);
        $this->entityManager->persist($validation);
        $this->entityManager->persist($targetPostureEvaluation);
        $this->entityManager->flush();

        $assessmentsToCall = [$assessment1, $assessment1];
        $assessmentStreamsToCall = [$assessmentStream, $assessmentStream2];

        // Positive 1 SAMM model, expects validated score
        // Positive 2 DRP model, expects validated score
        // Negative 3 SAMM Model, expects no score for not validated stream
        // vertical iteration ex. score = 1, target = 3, stream weight = 15, expected max stream weight = 100
        $expectedCurrentScoresToCall = [1, 0];
        $expectedTargetScoresToCall = [3, 0];
        $realMaxStreamWeightsToCall = [15, 63, 15];
        $expectedMaxStreamWeightsToCall = [100, 100, 100];

        for ($i = 0, $iMax = count($assessmentsToCall); $i < $iMax; $i++) {
            $assessment = $assessmentsToCall[$i];
            $assessmentStream = $assessmentStreamsToCall[$i];
            $expectedCurrentScore = $expectedCurrentScoresToCall[$i];
            $expectedTargetScore = $expectedTargetScoresToCall[$i];
            $realMaxStreamWeight = $realMaxStreamWeightsToCall[$i];
            $expectedMaxStreamWeight = $expectedMaxStreamWeightsToCall[$i];

            $streamWeights = $this->scoreService->getValidatedStreamWeights($assessment);

            foreach ($streamWeights as $streamWeight) {
                if ($streamWeight['streamId'] === $assessmentStream->getStream()->getId()) {
                    self::assertEquals($expectedCurrentScore, $streamWeight['currentScore']);
                    self::assertEquals($expectedTargetScore, $streamWeight['targetPostureScore']);
                    if ($expectedCurrentScore > $expectedTargetScore) {
                        $weight = 0;
                    } else {
                        $weight = pow(1 + $expectedTargetScore - $expectedCurrentScore, 2) - 1;
                    }
                    $scaledWeight = (int)round(100 * $weight / $realMaxStreamWeight);
                    self::assertEquals($scaledWeight, $streamWeight['streamWeight']);
                    self::assertEquals($expectedMaxStreamWeight, $streamWeight['maxStreamWeight']);
                }
            }
        }
    }

    public function testGetWithNonValidatedStreamWeights(): void
    {
        $questionRepository = self::getContainer()->get(QuestionRepository::class);
        $assessmentService = self::getContainer()->get(AssessmentService::class);

        /** @var Question $sammQuestion1 */
        $sammQuestion1 = $questionRepository->find(1);
        /** @var Question $sammQuestion2 */
        $sammQuestion2 = $questionRepository->find(20);

        $project1 = new Project();
        $project2 = new Project();
        $project1->setTemplateProject($project2);
        $project2->setTemplate(true);

        $sammMetamodel = self::getContainer()->get(MetamodelService::class)->getSAMM();

        $project1->setMetamodel($sammMetamodel);
        $project2->setMetamodel($sammMetamodel);

        $this->entityManager->persist($project1);
        $this->entityManager->persist($project2);
        $this->entityManager->flush();

        /** @var Assessment $assessment1 */
        $assessment1 = $assessmentService->createAssessment($project1);
        /** @var Assessment $assessment2 */
        $assessment2 = $assessmentService->createAssessment($project2);

        $assessmentStreamRepository = self::getContainer()->get(AssessmentStreamRepository::class);

        $assessmentStreams = $assessmentStreamRepository->findBy(['assessment' => $assessment1]);

        /** @var AssessmentStream $assessmentStream */
        $assessmentStream = $assessmentStreams[1];

        ($evaluation = new Evaluation())->setAssessmentStream($assessmentStream)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation)
                    ->setAnswer($sammQuestion1->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion1)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            );
        $validation = (new Validation())->setStatus(ValidationStatus::ACCEPTED)->setAssessmentStream($assessmentStream)->setCompletedAt(new \DateTime());

        $assessmentStream->addAssessmentStreamStage($validation);
        $assessmentStream->setStatus(AssessmentStatus::VALIDATED);

        /** @var AssessmentStream $assessmentStream2 */
        $assessmentStream2 = $assessmentStreams[2];
        ($evaluation2 = new Evaluation())->setAssessmentStream($assessmentStream2)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation2)
                    ->setAnswer($sammQuestion1->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion1)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            );
        $assessmentStream2->addAssessmentStreamStage($evaluation2);
        $assessmentStream2->setStatus(AssessmentStatus::IN_EVALUATION);

        $targetPostureAssessmentStreams = $assessmentStreamRepository->findBy(['assessment' => $assessment2]);

        /** @var AssessmentStream $targetPostureAssessmentStream */
        $targetPostureAssessmentStream = $targetPostureAssessmentStreams[1];
        $targetPostureAssessmentStream->setStatus(AssessmentStatus::IN_EVALUATION);
        ($targetPostureEvaluation = new Evaluation())->setAssessmentStream($targetPostureAssessmentStream)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($targetPostureEvaluation)
                    ->setAnswer($sammQuestion1->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion1)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            )
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($targetPostureEvaluation)
                    ->setAnswer($sammQuestion2->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion1)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            )
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($targetPostureEvaluation)
                    ->setAnswer($sammQuestion2->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion1)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            );
        $targetPostureAssessmentStream->addAssessmentStreamStage($targetPostureEvaluation);
        $assessment2->addAssessmentAssessmentStream($targetPostureAssessmentStream);

        $this->entityManager->persist($evaluation);
        $this->entityManager->persist($validation);
        $this->entityManager->persist($targetPostureEvaluation);
        $this->entityManager->flush();


        $assessmentsToCall = [$assessment1, $assessment1];
        $assessmentStreamsToCall = [$assessmentStream, $assessmentStream2];

        // Positive 1 SAMM model, expects validated score
        // Positive 3 SAMM Model, expects score for not validated stream
        // vertical iteration ex. score = 1, target = 3, stream weight = 15, expected max stream weight = 100
        $expectedCurrentScoresToCall = [1, 1];
        $expectedTargetScoresToCall = [3, 0];
        $realMaxStreamWeightsToCall = [15, 15];
        $expectedMaxStreamWeightsToCall = [100, 100];

        for ($i = 0, $iMax = count($assessmentsToCall); $i < $iMax; $i++) {
            $assessment = $assessmentsToCall[$i];
            $assessmentStream = $assessmentStreamsToCall[$i];
            $expectedCurrentScore = $expectedCurrentScoresToCall[$i];
            $expectedTargetScore = $expectedTargetScoresToCall[$i];
            $realMaxStreamWeight = $realMaxStreamWeightsToCall[$i];
            $expectedMaxStreamWeight = $expectedMaxStreamWeightsToCall[$i];

            $streamWeights = $this->scoreService->getActiveStreamWeights($assessment);

            foreach ($streamWeights as $streamWeight) {
                if ($streamWeight['streamId'] === $assessmentStream->getStream()->getId()) {
                    self::assertEquals($expectedCurrentScore, $streamWeight['currentScore']);
                    self::assertEquals($expectedTargetScore, $streamWeight['targetPostureScore']);
                    if ($expectedCurrentScore > $expectedTargetScore) {
                        $weight = 0;
                    } else {
                        $weight = pow(1 + $expectedTargetScore - $expectedCurrentScore, 2) - 1;
                    }
                    $scaledWeight = (int)round(100 * $weight / $realMaxStreamWeight);
                    self::assertEquals($scaledWeight, $streamWeight['streamWeight']);
                    self::assertEquals($expectedMaxStreamWeight, $streamWeight['maxStreamWeight']);
                }
            }
        }
    }

    /**
     * @dataProvider testGetNotValidatedScoresByAssessmentDataProvider
     */
    public function testGetNotValidatedScoresByAssessment(array $expectedResult): void
    {
        /** @var QuestionRepository $questionRepository */
        $questionRepository = self::getContainer()->get(QuestionRepository::class);
        $assessmentService = self::getContainer()->get(AssessmentService::class);

        /** @var Question $sammQuestion1 */
        $sammQuestion1 = $questionRepository->findOneBy(['externalId' => '21a9b65765a844e0b27a074f2b4306a1']); // G-EG-B-1
        $sammQuestion2 = $questionRepository->findOneBy(['externalId' => 'c4eb5618d1814173a995f8aea96f1c0b']); // D-SA-A-1
        $sammQuestion3 = $questionRepository->findOneBy(['externalId' => '1a849e8fd3ae41a4b3675947482426da']); // I-DM-B-2

        $project1 = new Project();
        $project2 = new Project();
        $project1->setTemplateProject($project2);
        $project2->setTemplate(true);

        $sammMetamodel = self::getContainer()->get(MetamodelService::class)->getSAMM();

        $project1->setMetamodel($sammMetamodel);
        $project2->setMetamodel($sammMetamodel);

        $this->entityManager->persist($project1);
        $this->entityManager->persist($project2);
        $this->entityManager->flush();

        /** @var Assessment $assessment1 */
        $assessment1 = $assessmentService->createAssessment($project1);

        $assessmentStreamRepository = $this->getContainer()->get(AssessmentStreamRepository::class);

        $assessmentStreams = $assessmentStreamRepository->findBy(['assessment' => $assessment1]);

        /** @var AssessmentStream $assessmentStream */
        $assessmentStream = current(array_filter($assessmentStreams, fn(AssessmentStream $as) => $as->getStream()->getId() === $sammQuestion1->getActivity()->getStream()->getId()));

        ($evaluation = new Evaluation())->setAssessmentStream($assessmentStream)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation)
                    ->setAnswer($sammQuestion1->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion1)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            );
        $validation = (new Validation())->setStatus(ValidationStatus::ACCEPTED)->setAssessmentStream($assessmentStream)->setCompletedAt(new \DateTime());

        $assessmentStream->addAssessmentStreamStage($evaluation)->addAssessmentStreamStage($validation);
        $assessmentStream->setStatus(AssessmentStatus::VALIDATED);

        /** @var AssessmentStream $assessmentStream2 */
        $assessmentStream2 = current(array_filter($assessmentStreams, fn(AssessmentStream $as) => $as->getStream()->getId() === $sammQuestion2->getActivity()->getStream()->getId()));
        ($evaluation2 = new Evaluation())->setAssessmentStream($assessmentStream2)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation2)
                    ->setAnswer($sammQuestion2->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion2)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            );
        $assessmentStream2->addAssessmentStreamStage($evaluation2);
        $assessmentStream2->setStatus(AssessmentStatus::IN_EVALUATION);

        $assessmentStreams = $assessmentStreamRepository->findBy(['assessment' => $assessment1]);

        $assessmentStream3 = current(array_filter($assessmentStreams, fn(AssessmentStream $as) => $as->getStream()->getId() === $sammQuestion3->getActivity()->getStream()->getId()));

        ($evaluation3 = new Evaluation())->setAssessmentStream($assessmentStream3)
            ->addStageAssessmentAnswer(
                (new AssessmentAnswer())->setStage($evaluation3)
                    ->setAnswer($sammQuestion3->getAnswerSet()->getAnswerSetAnswers()[3])->setQuestion($sammQuestion3)
                    ->setType(\App\Enum\AssessmentAnswerType::CURRENT)
            );

        $assessmentStream3->addAssessmentStreamStage($evaluation3);
        $assessmentStream3->setStatus(AssessmentStatus::IN_EVALUATION);

        $this->entityManager->persist($evaluation);
        $this->entityManager->persist($validation);
        $this->entityManager->persist($evaluation2);
        $this->entityManager->persist($evaluation3);
        $this->entityManager->flush();


        $score = $this->scoreService->getNotValidatedScoresByAssessment($assessment1);
        self::assertEquals($expectedResult, $score);
    }

    private function testGetNotValidatedScoresByAssessmentDataProvider(): array
    {
        return [
            'Positive test 1 ' => [
                [
                    'businessFunction' => [
                        'Governance' => 1 / 6,
                        'Design' => 1 / 6,
                        'Implementation' => 1 / 6,
                        'Verification' => 0.0,
                        'Operations' => 0.0,
                    ],
                    'securityPractice' => [
                        'SM' => 0.0,
                        'PC' => 0.0,
                        'EG' => 0.5,
                        'TA' => 0.0,
                        'SR' => 0.0,
                        'SA' => 0.5,
                        'SB' => 0.0,
                        'SD' => 0.0,
                        'DM' => 0.5,
                        'AA' => 0.0,
                        'RT' => 0.0,
                        'ST' => 0.0,
                        'IM' => 0.0,
                        'EM' => 0.0,
                        'OM' => 0.0,
                    ],
                ],
            ],
        ];
    }
}
