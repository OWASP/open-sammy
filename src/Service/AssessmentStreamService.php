<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Assessment;
use App\Entity\AssessmentStream;
use App\Entity\Stream;
use App\Entity\User;
use App\Enum\AssessmentStatus;
use App\Repository\AssessmentStreamRepository;
use App\Repository\ImprovementRepository;
use App\Utils\Constants;
use App\Voter\AssessmentStreamVoterHelper;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AssessmentStreamService
{
    public function __construct(
        private readonly ImprovementRepository $improvementRepository,
        private readonly AssessmentStreamRepository $assessmentStreamRepository,
        private readonly AssessmentAnswersService $assessmentAnswersService,
        private readonly AssessmentStreamVoterHelper $assessmentStreamVoterHelper
    ) {
    }

    public function getAssessmentStream(Assessment $assessment, Stream $stream): ?AssessmentStream
    {
        $assessmentStream = $assessment->getAssessmentAssessmentStreams()->filter(
            fn (AssessmentStream $assessmentStream) => (
                $assessmentStream->getStream()->getId() === $stream->getId() &&
                $assessmentStream->getStatus() !== AssessmentStatus::ARCHIVED
            )
        )->first();

        return ($assessmentStream instanceof AssessmentStream) ? $assessmentStream : null;
    }

    /**
     * @return AssessmentStream[]
     */
    private function filterByStatus(array $assessmentStreams, array $statuses): array
    {
        return array_filter($assessmentStreams, function (AssessmentStream $assessmentStream) use ($statuses) {
            return in_array($assessmentStream->getStatus(), $statuses, true);
        });
    }

    public function getEvaluationStreams(Assessment $assessment, ?User $user = null, array $streamWeights = []): array
    {
        $assessmentStreams = $this->filterByStatus(
            $assessment->getAssessmentAssessmentStreams()->toArray(),
            [\App\Enum\AssessmentStatus::NEW, \App\Enum\AssessmentStatus::IN_EVALUATION]
        );

        $assessmentStreams = $this->filterByAssignedTo($assessmentStreams, $user);

        return AssessmentService::sortAssessmentStreams($assessmentStreams, $streamWeights);
    }

    public function getNonVerifiedAnswers(Assessment $assessment, ?User $user = null, array $streamWeights = []): array
    {
        $assessmentStreams = $this->filterByStatus(
            $assessment->getAssessmentAssessmentStreams()->toArray(),
            [\App\Enum\AssessmentStatus::IN_VALIDATION]
        );

        $assessmentStreams = $this->filterByAssignedTo($assessmentStreams, $user);

        return AssessmentService::sortAssessmentStreams($assessmentStreams, $streamWeights);
    }

    public function getStreamsInOrForImprovement(Assessment $assessment, ?User $user = null, array $streamWeights = []): array
    {
        $assessmentStreams = $this->filterByStatus(
            $assessment->getAssessmentAssessmentStreams()->toArray(),
            [\App\Enum\AssessmentStatus::VALIDATED, \App\Enum\AssessmentStatus::IN_IMPROVEMENT]
        );

        $assessmentStreams = $this->filterByAssignedTo($assessmentStreams, $user);

        return AssessmentService::sortAssessmentStreams($assessmentStreams, $streamWeights);
    }

    public function getCompletedStreams(Assessment $assessment, array $streamWeights = []): array
    {
        $assessmentStreams = $this->filterByStatus(
            $assessment->getAssessmentAssessmentStreams()->toArray(),
            [\App\Enum\AssessmentStatus::COMPLETE]
        );

        return AssessmentService::sortAssessmentStreams($assessmentStreams, $streamWeights);
    }

    public function getStreamsCurrentStages(array $assessmentStreams): array
    {
        $result = [];
        /** @var AssessmentStream $assessmentStream */
        foreach ($assessmentStreams as $assessmentStream) {
            $result[] = $assessmentStream->getCurrentStage()->getId();
        }
        return $result;
    }

    /**
     * @param AssessmentStream[] $assessmentStreams
     *
     * @return AssessmentStream[]
     */
    protected function filterByAssignedTo(array $assessmentStreams, ?User $user = null): array
    {
        if ($user !== null && count($assessmentStreams) !== 0) {
            $userStreams = $this->assessmentStreamRepository->findUserAssignedStreamsByAssessments(reset($assessmentStreams)->getAssessment(), $user);
            $assessmentStreams = array_intersect($assessmentStreams, $userStreams);
        }

        return $assessmentStreams;
    }

    public function getPreviousAssessmentStream(AssessmentStream $assessmentStream): ?AssessmentStream
    {
        return $this->improvementRepository->findOneBy(['new' => $assessmentStream])?->getAssessmentStream();
    }

    public function setScore(AssessmentStream $assessmentStream): bool
    {
        return $this->setScoreWithCondition(
            $assessmentStream,
            fn (AssessmentStream $assessmentStream) => ($assessmentStream->getStatus()->value >= AssessmentStatus::VALIDATED->value)
        );
    }

    public function setScoreWithCondition(AssessmentStream $assessmentStream, callable $condition): bool
    {
        $oldScore = $assessmentStream->getScore();
        $score = $this->getPreviousAssessmentStream($assessmentStream)?->getScore() ?? 0;

        if ($condition($assessmentStream)) {
            $score = 0;
            $assessmentAnswers = $this->assessmentAnswersService->getLatestAssessmentStreamAnswers($assessmentStream);
            foreach ($assessmentAnswers as $assessmentAnswer) {
                $activity = $assessmentAnswer->getQuestion()->getActivity();
                if ($assessmentStream->getStream()->getPractice()->getBusinessFunction()->getMetamodel()->getId() === Constants::SAMM_ID) {
                    $score += $assessmentAnswer->getAnswer()->getValue() / sizeof($activity->getActivityQuestions());
                } else {
                    $allActivities = sizeof($assessmentStream->getStream()->getStreamActivities());
                    $score += $assessmentAnswer->getAnswer()->getValue() / $allActivities;
                }
            }
        }

        $assessmentStream->setScore($score);

        return $score !== $oldScore;
    }

    public function getByAssessmentAndIds(Assessment $assessment, array $assessmentStreamIds): array
    {
        return $this->assessmentStreamRepository->findBy(['id' => $assessmentStreamIds, 'assessment' => $assessment]);
    }

    public function canStreamBeRetracted(User $user, AssessmentStream $assessmentStream): bool
    {
        return $this->assessmentStreamVoterHelper->canStreamBeRetracted($user, $assessmentStream);
    }

}
