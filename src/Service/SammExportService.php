<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Assessment;
use App\Entity\AssessmentAnswer;
use App\Entity\AssessmentStream;
use App\Entity\Evaluation;
use App\Entity\Remark;
use App\Entity\Validation;
use App\Exception\SammExportValidationException;
use App\Repository\GroupProjectRepository;
use App\Repository\RemarkRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class SammExportService
{
    public function __construct(
        private readonly AssessmentService $assessmentService,
        private readonly AssessmentAnswersService $assessmentAnswersService,
        private readonly RemarkRepository $remarkRepository,
        private readonly GroupProjectRepository $groupProjectRepository,
        private readonly SammSchemaValidatorService $sammSchemaValidatorService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws SammExportValidationException
     */
    public function export(Assessment $assessment): string
    {
        $project = $assessment->getProject();
        $assessmentStreams = $this->assessmentService->getActiveStreams($assessment, true);
        $assessmentAnswers = $this->assessmentAnswersService->getLatestAnswersByAssessmentStreams($assessmentStreams);

        // Determine organization name from group
        $organizationName = 'Organization';
        $groupProjects = $this->groupProjectRepository->findAllByProject($project);
        if (count($groupProjects) > 0) {
            $organizationName = $groupProjects[0]->getGroup()->getName() ?? $organizationName;
        }

        $sammFileContent = [
            'formatVersion' => '1.0.0',
            'assessment' => [
                'version' => '1.0.0',
                'organization' => $organizationName,
                'scope' => $project?->getName() ?? 'project',
                'date' => (new \DateTime('now'))->format('Y-m-d'),
            ],
        ];

        // Build answers sorted by hierarchy
        $sammFileContent['assessment']['answers'] = [];

        $sortedAnswers = $this->sortAnswersByHierarchy($assessmentAnswers);
        foreach ($sortedAnswers as $assessmentAnswer) {
            $questionCode = $assessmentAnswer->getQuestion()->getActivity()->getNameKey();

            $sammFileContent['assessment']['answers'][] = [
                'questionCode' => $questionCode,
                'answerScore' => $assessmentAnswer->getAnswer()->getValue(),
            ];
        }

        // Build remarks extension
        $remarks = $this->remarkRepository->findByAssessmentStreams($assessmentStreams);
        $this->addRemarksExtension($sammFileContent, $assessmentStreams, $remarks);

        // Build assessment framework extension
        $frameworkName = $project?->getMetamodel()?->getName() ?? 'SAMM';
        $sammFileContent['extensions'][] = [
            'name' => 'Assessment Framework',
            'version' => '1.0.0',
            'assessmentFramework' => $frameworkName,
        ];

        // Validate against schema
        $validationErrors = $this->sammSchemaValidatorService->validate($sammFileContent);
        if (count($validationErrors) > 0) {
            $this->logger->log(LogLevel::ERROR, 'SAMM export validation failed', ['errors' => $validationErrors]);
            throw new SammExportValidationException($validationErrors);
        }

        try {
            return json_encode($sammFileContent, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        } catch (\JsonException) {
            return '';
        }
    }

    /**
     * Sort answers by business function order, practice order, stream order, maturity level.
     *
     * @param AssessmentAnswer[] $assessmentAnswers
     * @return AssessmentAnswer[]
     */
    private function sortAnswersByHierarchy(array $assessmentAnswers): array
    {
        usort($assessmentAnswers, function (AssessmentAnswer $a, AssessmentAnswer $b) {
            $aStream = $a->getAssessmentStream()->getStream();
            $bStream = $b->getAssessmentStream()->getStream();

            $aKey = (int) (
                $aStream->getPractice()->getBusinessFunction()->getOrder()
                . $aStream->getPractice()->getOrder()
                . $aStream->getOrder()
                . ($a->getQuestion()->getActivity()->getPracticeLevel()?->getMaturityLevel()?->getLevel() ?? 0)
                . $a->getQuestion()->getOrder()
            );

            $bKey = (int) (
                $bStream->getPractice()->getBusinessFunction()->getOrder()
                . $bStream->getPractice()->getOrder()
                . $bStream->getOrder()
                . ($b->getQuestion()->getActivity()->getPracticeLevel()?->getMaturityLevel()?->getLevel() ?? 0)
                . $b->getQuestion()->getOrder()
            );

            return $aKey <=> $bKey;
        });

        return $assessmentAnswers;
    }

    /**
     * @param AssessmentStream[] $assessmentStreams
     * @param Remark[] $remarks
     */
    private function addRemarksExtension(array &$sammFileContent, array $assessmentStreams, array $remarks): void
    {
        $remarksByStreamCode = [];

        // Collect Remark entities (attached to stages) as COMMENTs
        foreach ($remarks as $remark) {
            $text = $remark->getText();
            if ($text === null || $text === '') {
                continue;
            }

            $stage = $remark->getStage();
            if ($stage === null) {
                continue;
            }

            $assessmentStream = $stage->getAssessmentStream();
            if ($assessmentStream === null) {
                continue;
            }

            $streamCode = $assessmentStream->getStream()->getNameKey();

            $remarksByStreamCode[$streamCode][] = [
                'text' => $text,
                'type' => 'COMMENT',
            ];
        }

        // Collect stage comments (Evaluation::getComment() and Validation::getComment())
        foreach ($assessmentStreams as $assessmentStream) {
            $streamCode = $assessmentStream->getStream()->getNameKey();

            $validation = $assessmentStream->getLastValidationStage();
            $validationComment = $validation instanceof Validation ? $validation->getComment() : null;

            if ($validationComment !== null && $validationComment !== '') {
                // Validation comment supersedes the evaluation draft
                $remarksByStreamCode[$streamCode][] = [
                    'text' => $validationComment,
                    'type' => 'VALIDATION',
                ];
            } else {
                // Fall back to evaluation draft comment
                $evaluation = $assessmentStream->getLastEvaluationStage();
                $evaluationComment = $evaluation instanceof Evaluation ? $evaluation->getComment() : null;

                if ($evaluationComment !== null && $evaluationComment !== '') {
                    $remarksByStreamCode[$streamCode][] = [
                        'text' => $evaluationComment,
                        'type' => 'VALIDATION',
                    ];
                }
            }
        }

        if (count($remarksByStreamCode) > 0) {
            $sammFileContent['extensions'][] = [
                'name' => 'Assessment Stream Remarks',
                'version' => '1.0.0',
                'assessmentStreamRemarks' => $remarksByStreamCode,
            ];
        }
    }
}
