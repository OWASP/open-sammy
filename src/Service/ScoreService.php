<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Assessment;
use App\Entity\Metamodel;
use App\Entity\Project;
use App\Enum\MetamodelType;
use App\Repository\AssessmentStreamRepository;
use App\Utils\Constants;
use App\Utils\DateTimeUtil;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class ScoreService
{
    public function __construct(
        private readonly AssessmentStreamRepository $assessmentStreamRepository,
        private readonly AssessmentAnswersService $assessmentAnswersService,
        private readonly AssessmentStreamFilterService $assessmentStreamFilterService,
        private readonly EntityManagerInterface $entityManager,
        private readonly TagAwareCacheInterface $redisCache,
        private readonly DateTimeUtil $dateTimeUtil
    ) {
    }

    public function getTargetPostureScoresByAssessment(Assessment $assessment): array
    {
        $project = $assessment->getProject();
        $templateProject = $project->getTemplateProject();
        if ($templateProject === null) {
            return ['businessFunction' => [], 'securityPractice' => []];
        }
        $assessmentStreams = AssessmentStreamFilterService::getActiveStreams($templateProject->getAssessment());
        $metamodel = $project->getMetamodel();

        return $this->getScoreArrayByAssessmentStreams($assessmentStreams, $metamodel);
    }

    public function getScoresByAssessment(Assessment $assessment, \DateTime $dateTime = new \DateTime('today'), bool $validated = true): array
    {
        $prefix = $validated ? Constants::SCORE_KEY_PREFIX_VALIDATED : Constants::SCORE_KEY_PREFIX_ACTIVE;
        $tag = $prefix.$assessment->getId();
        $cacheName = $tag.$dateTime->format('Y-m-d');
        $cache = $this->redisCache->getItem($cacheName);
        if (!$cache->isHit()) {
            $metamodel = $assessment->getProject()->getMetamodel();

            $assessmentStreams = $validated ?
                $this->assessmentStreamFilterService->getValidatedAssessmentStreamsByDate($assessment, $dateTime) :
                $this->assessmentStreamFilterService->getAssessmentStreamsByDate($assessment, $dateTime);

            $scores = $this->getScoreArrayByAssessmentStreams($assessmentStreams, $metamodel);
            $cache->expiresAfter(Constants::DEFAULT_CACHE_EXPIRATION);
            $cache->set($scores);
            $cache->tag($tag);
            $this->redisCache->save($cache);
        }

        return $cache->get();
    }

    public function getDetailedScoresByAssessment(Assessment $assessment, \DateTime $dateTime = new \DateTime('today'), bool $validated = true): array
    {
        $prefix = $validated ? Constants::SCORE_KEY_PREFIX_VALIDATED : Constants::SCORE_KEY_PREFIX_ACTIVE;
        $tag = $prefix.'detailed-'.$assessment->getId();
        $cacheName = $tag.$dateTime->format('Y-m-d');
        $cache = $this->redisCache->getItem($cacheName);
        if (!$cache->isHit()) {
            $metamodel = $assessment->getProject()->getMetamodel();

            $assessmentStreams = $validated ?
                $this->assessmentStreamFilterService->getValidatedAssessmentStreamsByDate($assessment, $dateTime) :
                $this->assessmentStreamFilterService->getAssessmentStreamsByDate($assessment, $dateTime);

            $scores = $this->getDetailedScoreArrayByAssessmentStreams($assessmentStreams, $metamodel);
            $cache->expiresAfter(Constants::DEFAULT_CACHE_EXPIRATION);
            $cache->set($scores);
            $cache->tag($tag);
            $this->redisCache->save($cache);
        }

        return $cache->get();
    }

    public function getNotValidatedScoresByAssessment(Assessment $assessment): array
    {
        $metamodel = $assessment->getProject()->getMetamodel();
        $assessmentStreams = $this->assessmentStreamRepository->findActiveByAssessment($assessment);

        return $this->getScoreArrayByAssessmentStreams($assessmentStreams, $metamodel);
    }

    public function getProjectedScoresByAssessment(Assessment $assessment, ?\DateTime $improvementLastDate = null): array
    {
        $metamodel = $assessment->getProject()->getMetamodel();

        $assessmentStreams = $this->assessmentStreamFilterService->getValidatedAssessmentStreamsByDate($assessment, new \DateTime('today'));

        return $this->getProjectedScoreArrayByAssessmentStreams($assessmentStreams, $metamodel, improvementLastDate: $improvementLastDate);
    }

    /**
     * @return array{
     *              businessFunction: array,
     *              securityPractice: array
     * }
     */
    private function getScoreArrayByAssessmentStreams(array $assessmentStreams, Metamodel $metamodel): array
    {
        return $this->getCompleteScoreArrayByAssessmentStreams($assessmentStreams, $metamodel)[0];
    }

    /**
     * @return array{
     *              businessFunction: array,
     *              securityPractice: array
     * }
     */
    private function getProjectedScoreArrayByAssessmentStreams(array $assessmentStreams, Metamodel $metamodel, ?\DateTime $improvementLastDate = null): array
    {
        return $this->getCompleteScoreArrayByAssessmentStreams($assessmentStreams, $metamodel, improvementLastDate: $improvementLastDate)[1];
    }

    // TODO: write test
    public function getProjectedScoresIndexedByAssessmentStreamAndQuestion(array $assessmentStreams): array
    {
        $answers = $this->assessmentAnswersService->getLatestAnswersByAssessmentStreams($assessmentStreams);
        $desiredAnswers = $this->assessmentAnswersService->getLatestAnswersByAssessmentStreams(
            $assessmentStreams,
            \App\Enum\AssessmentAnswerType::DESIRED
        );

        $scoresArray = [];
        foreach ($answers as $answer) {
            $assessmentStream = $answer->getAssessmentStream();
            $question = $answer->getQuestion();
            $scoresArray[$assessmentStream->getId()][$question->getId()] = $answer->getAnswer()->getValue();
        }

        foreach ($desiredAnswers as $answer) {
            $assessmentStream = $answer->getAssessmentStream();
            $question = $answer->getQuestion();
            if ($assessmentStream->getStatus() === \App\Enum\AssessmentStatus::IN_IMPROVEMENT) {
                $scoresArray[$assessmentStream->getId()][$question->getId()] = $answer->getAnswer()->getValue();
            }
        }

        return $scoresArray;
    }

    public static function calculateMeanScore(array $scores): float
    {
        $count = count($scores);

        return $count > 0 ? round(array_sum($scores) / $count, 2) : 0;
    }

    public function getProjectScores(\DateTime $dateTime, bool $validated, Project ...$projects): array
    {
        $result = [];

        foreach ($projects as $project) {
            $scores = $project->isTemplate() ?
                $this->getScoresByAssessment($project->getAssessment(), new \DateTime('now'), false) :
                $this->getScoresByAssessment($project->getAssessment(), $dateTime, $validated);
            $result[$project->getId()]['arithmeticMean'] = self::calculateMeanScore($scores['businessFunction'] ?? []);
            $result[$project->getId()]['projectName'] = $project->getName();
        }

        return $result;
    }

    public function getProjectScoresByQuestion(\DateTime $dateTime, bool $validated, Project ...$projects): array
    {
        $result = [];

        foreach ($projects as $project) {
            $scores = $project->isTemplate() ?
                $this->getDetailedScoresByAssessment($project->getAssessment(), new \DateTime('now'), false) :
                $this->getDetailedScoresByAssessment($project->getAssessment(), $dateTime, $validated);
            $result[$project->getId()]['score'] = $scores;
            $result[$project->getId()]['projectName'] = $project->getName();
        }

        return $result;
    }

    public function getScoreForAssessmentPerDates(Assessment $currentAssessment, bool $validated): array
    {
        $result = [];
        $dates = $this->getQuarterDates();
        foreach ($dates['current'] as $date) {
            $scoresForThisDate = $this->getScoresByAssessment($currentAssessment, $date, $validated);

            $result[$date->format('Y-m-d')] = self::calculateMeanScore($scoresForThisDate['businessFunction']);
        }

        foreach ($dates['projected'] as $date) {
            $scoresForThisDate = $this->getProjectedScoresByAssessment($currentAssessment, $date);
            $result[$date->format('Y-m-d')] = self::calculateMeanScore($scoresForThisDate['businessFunction']);
        }

        if ($currentAssessment->getProject()->getTemplateProject() !== null) {
            $targetPostureScores = $this->getTargetPostureScoresByAssessment($currentAssessment);
            $result['Target'] = self::calculateMeanScore($targetPostureScores['businessFunction']);
        } else {
            $result['Target'] = 'null';
        }

        return $result;
    }

    /**
     * @return array{
     *     current: \DateTime[],
     *     projected: \DateTime[],
     *     }
     */
    private function getQuarterDates(): array
    {
        $keyDates = [];
        $keyDates['current'][] = $this->dateTimeUtil->getRelativeQuarterEndDate(-2);
        $keyDates['current'][] = $this->dateTimeUtil->getRelativeQuarterEndDate(-1);
        $keyDates['current'][] = new \DateTime('now');
        $keyDates['projected'][] = $this->dateTimeUtil->getRelativeQuarterEndDate();

        return $keyDates;
    }

    public function getValidatedStreamWeights(Assessment $assessment): array
    {
        $assessmentStreams = $this->assessmentStreamRepository->findLatestValidatedByAssessmentAndDate($assessment, new \DateTime('now'));

        return $this->getStreamWeights($assessment, $assessmentStreams);
    }

    public function getActiveStreamWeights(Assessment $assessment): array
    {
        $assessmentStreams = $this->assessmentStreamRepository->findActiveByAssessment($assessment);

        return $this->getStreamWeights($assessment, $assessmentStreams);
    }

    private function getStreamWeights(Assessment $assessment, array $assessmentStreams): array
    {
        if ($assessment->getProject()->getTemplateProject() !== null) {
            $targetPostureAssessmentStreams = AssessmentStreamFilterService::getActiveStreams($assessment->getProject()->getTemplateProject()->getAssessment());
            foreach ($targetPostureAssessmentStreams as $targetPostureAssessmentStream) {
                $assessmentStreams[] = $targetPostureAssessmentStream;
            }
        }

        $minScore = '0.00';
        $weightFormula = $this->getWeightFormulaSql((string)$assessment->getProject()->getMetamodel()->getMaxScore(), $minScore);

        $nestedWeightFormula = $this->getWeightFormulaSql(
            'SUM(targetPostureScore)',
            'SUM(score)'
        );

        $dsommType = MetamodelType::DSOMM->value;
        $streamQuestionCountQuery = <<<SQL
    SELECT stream_id, COUNT(DISTINCT question_id) as stream_question_count
    FROM question_fully_joined
    GROUP BY stream_id
SQL;

        $sql = /* @lang SQL */
            <<<SQL
    SELECT t4.stream_id,
           t4.stream_name,
           t4.currentScore,
           t4.targetPostureScore,
           t4.streamWeight,
           t4.maxStreamWeight,
           t4.targetPostureScore - t4.currentScore as delta,
           (CASE 
               WHEN t4.streamWeight >= (t4.maxStreamWeight / 2) THEN "High" 
               WHEN t4.streamWeight >= (t4.maxStreamWeight / 4) THEN "Medium"
               WHEN t4.streamWeight > 0 THEN "Low"
               ELSE "None"
           END) as streamPriority
    FROM (
        SELECT T3.stream_id,
               stream_name,
               ROUND(SUM(score), 2) as currentScore,
               ROUND(SUM(targetPostureScore), 2) AS targetPostureScore,
               {$nestedWeightFormula} as streamWeight,
               {$weightFormula} as maxStreamWeight
        FROM (
            SELECT T1.stream_id AS stream_id, 
                   T1.stream_name AS stream_name, 
                   SUM(currentScore) / COALESCE(
                      CASE WHEN T1.metamodel_type = {$dsommType} THEN stream_question_count ELSE questionCount END, 
                      1) AS score, 
                   SUM(targetPostureScore) / COALESCE(
                      CASE WHEN T1.metamodel_type = {$dsommType} THEN stream_question_count ELSE questionCount END, 
                      1) AS targetPostureScore 
            FROM (
                SELECT stream.id as stream_id,  
                       stream.name as stream_name,
                       current_score as currentScore,
                       target_posture_score as targetPostureScore,
                       activity_id,
                       metamodel.type as metamodel_type
                FROM stream
                LEFT JOIN current_and_desired_scores_by_assessment_stream 
                  ON stream.id = current_and_desired_scores_by_assessment_stream.stream_id 
                 AND current_and_desired_scores_by_assessment_stream.assessment_stream_id IN (:assessmentStreams)
                LEFT JOIN practice ON stream.practice_id = practice.id
                LEFT JOIN business_function ON business_function.id = practice.business_function_id
                LEFT JOIN metamodel ON metamodel.id = business_function.metamodel_id
                WHERE metamodel.id = :metamodelId
            ) AS T1
            LEFT JOIN
            (SELECT question_count AS questionCount, activity_id
             FROM activity_question_count
            ) AS T2 ON T2.activity_id = T1.activity_id
            LEFT JOIN ({$streamQuestionCountQuery}) AS T3_stream_counts 
            ON T3_stream_counts.stream_id = T1.stream_id
            GROUP BY stream_name, T2.activity_id, T1.metamodel_type
        ) AS T3 
        GROUP BY stream_id
    ) AS t4
    SQL;

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('stream_id', 'streamId');
        $rsm->addScalarResult('stream_name', 'streamName');
        $rsm->addScalarResult('streamWeight', 'streamWeight');
        $rsm->addScalarResult('currentScore', 'currentScore');
        $rsm->addScalarResult('targetPostureScore', 'targetPostureScore');
        $rsm->addScalarResult('delta', 'delta');
        $rsm->addScalarResult('maxStreamWeight', 'maxStreamWeight');
        $rsm->addScalarResult('streamPriority', 'streamPriority');

        $queryResults = $this->entityManager->createNativeQuery($sql, $rsm)->setParameters(
            [
                'assessmentStreams' => $assessmentStreams,
                'metamodelId' => $assessment->getProject()->getMetamodel()->getId(),
            ]
        )->getResult();

        $indexedResult = [];
        foreach ($queryResults as $queryResult) {
            // We convert to 0-100 format and round to int to get nice numbers
            $queryResult['streamWeight'] = $queryResult['maxStreamWeight'] !== 0 ? 100 * ($queryResult['streamWeight'] / $queryResult['maxStreamWeight']) : 0;
            $queryResult['maxStreamWeight'] = 100;
            $queryResult['streamWeight'] = (int)round($queryResult['streamWeight']);

            $indexedResult[$queryResult['streamId']] = $queryResult;
        }

        return $indexedResult;
    }

    private function getWeightFormulaSql(string $targetScore, string $currentScore): string
    {
        return "CASE WHEN ({$targetScore} < {$currentScore}) THEN 0 ELSE GREATEST(POW(1 + {$targetScore} - {$currentScore}, 2) - 1, 0) END";
    }

    /**
     * Returns an SQL that selects current and desired scores, per question
     * The questions are bucketed by businessFunction (+ order), practice (+ order), stream and activity
     * Left join is used so the buckets exist even if there is no data (score defaults to 0),
     * this ensures we have a complete dataset that we can aggregate safely.
     * If Desired score doesn't exist it defaults to the current score.
     */
    private function getScoreAndDesiredScoreByQuestionSQL(?\DateTime $improvementLastDate = null): string
    {
        $dateCheck = $improvementLastDate !== null ? "STR_TO_DATE(stage.target_date, '%Y-%m-%d')<= '{$improvementLastDate->format('Y-m-d H:i:s')}'" : 'true';

        return /* @lang SQL */
            <<<SQL
            SELECT bf_name,
                   bf_order,
                   short_name,
                   p_order,
                   stream_name,
                   activity_id,
                   question_fully_joined.question_id,
                   metamodel.id as metamodel_id,
                   metamodel.type as metamodel_type,
                   IF(assessment_answer.type = 0, answer.value, 0) as score,
                   IF((SUM(IF(assessment_answer.type=1, answer.value, null)) is null), 
                       SUM(IF(assessment_answer.type=0, answer.value, 0)), 
                       SUM(IF(assessment_answer.type=1, answer.value, 0))) as desiredScore,
                   question_fully_joined.stream_id
                FROM question_fully_joined
                    LEFT JOIN assessment_stream ON assessment_stream.stream_id = question_fully_joined.stream_id AND assessment_stream.id IN (:assessmentStreams)
                    LEFT JOIN stage ON stage.assessment_stream_id = assessment_stream.id AND stage.id IN
                        (
                        SELECT max(`id`) as id
                        FROM stage
                        WHERE (stage.dType = 'evaluation' or (stage.dType = 'improvement' and $dateCheck))
                        GROUP BY stage.assessment_stream_id, stage.dType
                        )
                    LEFT JOIN assessment_answer ON  assessment_answer.stage_id=stage.id and assessment_answer.deleted_at is null and assessment_answer.question_id = question_fully_joined.question_id
                    LEFT JOIN answer ON assessment_answer.answer_id = answer.id
                LEFT JOIN metamodel ON question_fully_joined.metamodel_id = metamodel.id
                WHERE metamodel.id = :metamodel AND (answer.value>=0 OR answer.value is null)
                GROUP BY question_fully_joined.question_id
            SQL;
    }

    /**
     * @return array{
     *     array{businessFunction: array, securityPractice: array},
     *     array{businessFunction: array, securityPractice: array},
     *     }
     *     [0] => currentScores
     *     [1] => desiredScores
     */
    private function getCompleteScoreArrayByAssessmentStreams(array $assessmentStreams, ?Metamodel $metamodel, ?\DateTime $improvementLastDate = null): array
    {
        $assessmentStream = reset($assessmentStreams);
        $assessment = $assessmentStream ? $assessmentStream->getAssessment() : null;

        // the query starts with a join from business function, practice and stream to make sure we have a full data set
        // then a left join to pull the answers
        // the CASE WHEN SUM puts the answers of type 0 (current) and 1 (desired) in separate bins
        // an additional CASE WHEN takes care of setting desired to the current value if its not set (we assume that desired = current in case no desired has been explicitly provided)
        // we need a third select to make sure that the binning is done per question then per stream then per practice

        $scoreAndDesiredScoreByQuestion = $this->getScoreAndDesiredScoreByQuestionSQL($improvementLastDate);
        $streamQuestionCountQuery = "SELECT stream_id, COUNT(DISTINCT question_id) as stream_question_count
                            FROM question_fully_joined
                            GROUP BY stream_id";

        $dsommType = MetamodelType::DSOMM->value;
        $sql = /* @lang SQL */
            <<<SQL
               SELECT name, short_name, AVG(score) as averageScore, AVG(desiredScore) as averageDesiredScore
                FROM (
                    SELECT stream_name, name, short_name, SUM(desiredScore) as desiredScore, SUM(score) as score, bf_order, p_order
                    FROM (
                        SELECT name, short_name, stream_name, score, desiredScore, bf_order, p_order, V1.activity_id
                        FROM (
                            SELECT bf_name as name, 
                               short_name,
                               stream_name, 
                               SUM(desiredScore) / COALESCE(
                                  CASE WHEN metamodel_type = {$dsommType} THEN stream_question_count ELSE question_count END, 
                                  1) as desiredScore,
                               SUM(score) / COALESCE(
                                  CASE WHEN metamodel_type = {$dsommType} THEN stream_question_count ELSE question_count END, 
                                  1) as score,
                               bf_order, 
                               p_order, 
                               T1.activity_id,
                               metamodel_id,
                               metamodel_type
                            FROM ($scoreAndDesiredScoreByQuestion) AS T1
                                LEFT JOIN activity_question_count AS T2 ON T1.activity_id = T2.activity_id
                                -- Add join for stream question counts
                                LEFT JOIN ($streamQuestionCountQuery) AS T3 ON T1.stream_id = T3.stream_id
                            GROUP BY T1.activity_id
                            ) as V1
                        ) as W1
                    GROUP BY name, short_name, stream_name
                    ) as W2
                GROUP BY name, short_name
                ORDER BY bf_order, p_order
            SQL;

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('short_name', 'shortName');
        $rsm->addScalarResult('averageDesiredScore', 'averageDesiredScore');
        $rsm->addScalarResult('averageScore', 'averageScore');
        $queryResults = $this->entityManager->createNativeQuery($sql, $rsm)
            ->setParameters(['assessmentStreams' => $assessmentStreams, 'metamodel' => $metamodel, 'assessment' => $assessment])->getResult();
        $resultDesired = ['businessFunction' => [], 'securityPractice' => []];
        $result = ['businessFunction' => [], 'securityPractice' => []];
        $tempCounter = $tempScore = $tempScoreDesired = 0;
        $previousBusinessFunction = '';
        foreach ($queryResults as $queryResult) {
            $result['securityPractice'][$queryResult['shortName']] = (float)$queryResult['averageScore'];
            $resultDesired['securityPractice'][$queryResult['shortName']] = (float)$queryResult['averageDesiredScore'];
            if ($queryResult['name'] !== $previousBusinessFunction) {
                if ($previousBusinessFunction !== '') {
                    $tempCounter = $tempCounter !== 0 ? $tempCounter : 1;
                    $resultDesired['businessFunction'][$previousBusinessFunction] = $tempScoreDesired / $tempCounter;
                    $result['businessFunction'][$previousBusinessFunction] = $tempScore / $tempCounter;
                }
                $previousBusinessFunction = $queryResult['name'];
                $tempCounter = 0;
                $tempScoreDesired = 0;
                $tempScore = 0;
            }
            ++$tempCounter;
            $tempScoreDesired += (float)$queryResult['averageDesiredScore'];
            $tempScore += (float)$queryResult['averageScore'];
        }

        $tempCounter = $tempCounter !== 0 ? $tempCounter : 1;

        $result['businessFunction'][$previousBusinessFunction] = $tempScore / $tempCounter;
        $resultDesired['businessFunction'][$previousBusinessFunction] = $tempScoreDesired / $tempCounter;

        return [$result, $resultDesired];
    }

    /**
     * @return array{
     *     array{businessFunction: array, securityPractice: array},
     *     array{businessFunction: array, securityPractice: array}
     *     }
     *     [0] => currentScores
     *     [1] => desiredScores
     */
    private function getDetailedScoreArrayByAssessmentStreams(array $assessmentStreams, ?Metamodel $metamodel): array
    {
        $assessmentStream = reset($assessmentStreams);
        $assessment = $assessmentStream ? $assessmentStream->getAssessment() : null;

        $scoreAndDesiredScoreByQuestion = $this->getScoreAndDesiredScoreByQuestionSQL();
        $sql = /* @lang SQL */
            <<<SQL
                SELECT bf_name as name, 
                       short_name,
                       stream_name, 
                       SUM(score) as score,
                       bf_order, 
                       p_order, 
                       question_id
                FROM ($scoreAndDesiredScoreByQuestion) as T1
                GROUP BY question_id
            SQL;


        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('question_id', 'questionId');
        $rsm->addScalarResult('score', 'score');

        $queryResults = $this->entityManager->createNativeQuery($sql, $rsm)
            ->setParameters(['assessmentStreams' => $assessmentStreams, 'metamodel' => $metamodel])->getResult();

        return array_combine(array_column($queryResults, 'questionId'), array_column($queryResults, 'score'));
    }

    public function getExternallyVerifiedScoreByQuestion(Assessment $assessment): array
    {
        $sql = $this->getExternallyVerifiedScoresQuery();

        $metamodel = $assessment->getProject()->getMetamodel();

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('bf_name', 'name');
        $rsm->addScalarResult('short_name', 'shortName');
        $rsm->addScalarResult('stream_name', 'streamName');
        $rsm->addScalarResult('activity_id', 'activityId');
        $rsm->addScalarResult('question_id', 'questionId');
        $rsm->addScalarResult('score', 'verifiedScore');
        $rsm->addScalarResult('answer_id', 'answerId');
        $rsm->addScalarResult('date', 'date');

        $queryResults = $this->entityManager->createNativeQuery($sql, $rsm)
            ->setParameters(['assessment' => $assessment, 'metamodel' => $metamodel])->getResult();

        $result = [];
        foreach ($queryResults as $row) {
            $result[$row['questionId']] = ['score' => $row['verifiedScore'], 'answer' => $row['answerId'], 'date' => $row['date'] !== null ? new \DateTime($row['date']) : null];
        }

        return $result;
    }

    public function getExternallyVerifiedScoreArray(Assessment $assessment): array
    {
        $dsommType = MetamodelType::DSOMM->value;
        $verifiedScoreByQuestion = $this->getExternallyVerifiedScoresQuery();
        $streamQuestionCountQuery = <<<SQL
            SELECT stream_id, COUNT(DISTINCT question_id) as stream_question_count
            FROM question_fully_joined
            GROUP BY stream_id
        SQL;
        $sql = /* @lang SQL */
            <<<SQL
        SELECT name, short_name, AVG(score) as averageScore
        FROM (
            SELECT stream_name, name, short_name, SUM(score) as score, bf_order, p_order
            FROM (
                SELECT name, short_name, stream_name, score, bf_order, p_order, V1.activity_id
                FROM (
                    SELECT bf_name as name, 
                           short_name,
                           stream_name, 
                           SUM(IF(score, score, 0)) / COALESCE(
                              CASE WHEN metamodel_type = {$dsommType} THEN stream_question_count ELSE question_count END, 
                              1) as score,
                           bf_order, 
                           p_order, 
                           T1.activity_id,
                           metamodel_id
                    FROM ($verifiedScoreByQuestion) AS T1
                        LEFT JOIN activity_question_count AS T2 ON T1.activity_id = T2.activity_id
                        LEFT JOIN ($streamQuestionCountQuery) AS T3 ON T1.stream_id = T3.stream_id
                    GROUP BY T1.activity_id
                    ) as V1
                ) as W1
            GROUP BY name, short_name, stream_name
            ) as W2
        GROUP BY name, short_name
        ORDER BY bf_order, p_order
    SQL;

        $metamodel = $assessment->getProject()->getMetamodel();

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('name', 'name');
        $rsm->addScalarResult('short_name', 'shortName');
        $rsm->addScalarResult('averageScore', 'verifiedScore');

        $queryResults = $this->entityManager->createNativeQuery($sql, $rsm)
            ->setParameters(['assessment' => $assessment, 'metamodel' => $metamodel])->getResult();

        $result = ['businessFunction' => [], 'securityPractice' => []];
        $tempCounter = $tempScore = 0;
        $previousBusinessFunction = '';
        foreach ($queryResults as $queryResult) {
            $result['securityPractice'][$queryResult['shortName']] = (float)$queryResult['verifiedScore'];
            if ($queryResult['name'] !== $previousBusinessFunction) {
                if ($previousBusinessFunction !== '') {
                    $tempCounter = $tempCounter !== 0 ? $tempCounter : 1;
                    $result['businessFunction'][$previousBusinessFunction] = $tempScore / $tempCounter;
                }
                $previousBusinessFunction = $queryResult['name'];
                $tempCounter = 0;
                $tempScore = 0;
            }
            ++$tempCounter;
            $tempScore += (float)$queryResult['verifiedScore'];
        }

        $tempCounter = $tempCounter !== 0 ? $tempCounter : 1;

        $result['businessFunction'][$previousBusinessFunction] = $tempScore / $tempCounter;

        return $result;
    }


    private function getExternallyVerifiedScoresQuery(): string
    {
        return  <<<SQL
        SELECT bf_name,
               bf_order,
               short_name,
               p_order,
               stream_name,
               activity_id,
               question_fully_joined.question_id,
               question_fully_joined.stream_id,
               metamodel_id,
               S1.score as score,
               S1.date as date,
               S1.answer_id as answer_id,
               metamodel.type as metamodel_type
        FROM question_fully_joined
        LEFT JOIN (
                    SELECT 
                        assessment_stream.id,
                        assessment_stream.stream_id,
                        assessment_answer.question_id as question_id,
                        answer.value as score,
                        externalValidation.completed_at as date,
                        IF(answer.id, answer.id, null) as answer_id
                    FROM assessment_stream
                    JOIN stage as externalValidation
                        ON externalValidation.id =
                           (
                               SELECT max(stage.id) as validationId
                               FROM stage
                                        JOIN validation on stage.id = validation.id
                                        JOIN user on stage.submitted_by_id = user.id
                               WHERE (stage.assessment_stream_id = assessment_stream.id and
                                      validation.status = 2 and
                                      JSON_UNQUOTE(user.roles) LIKE '%ROLE_AUDITOR%')
                           )
                    JOIN stage
                        ON stage.id =
                           (
                               SELECT max(stage.id) as id
                               FROM stage
                               WHERE (stage.assessment_stream_id = assessment_stream.id and
                                      stage.dType = 'evaluation' and
                                      stage.id < externalValidation.id)
                           )
                    LEFT JOIN assessment_answer ON  assessment_answer.stage_id=stage.id and assessment_answer.deleted_at is null
                    LEFT JOIN answer ON assessment_answer.answer_id = answer.id
                    WHERE assessment_stream.assessment_id = :assessment
                    ) as S1 on S1.question_id = question_fully_joined.question_id
        LEFT JOIN metamodel ON question_fully_joined.metamodel_id = metamodel.id
        WHERE metamodel_id = :metamodel
        GROUP BY question_fully_joined.question_id
        ORDER BY bf_order, p_order, stream_name
    SQL;
    }
}
