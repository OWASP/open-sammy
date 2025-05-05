<?php

declare(strict_types=1);

namespace App\Service\Processing;

use App\Enum\ModelEntitySyncEnum;
use App\Repository\BusinessFunctionRepository;
use App\Repository\PracticeRepository;
use App\Repository\StreamRepository;
use App\Repository\MaturityLevelRepository;
use App\Repository\PracticeLevelRepository;
use App\Repository\ActivityRepository;
use App\Repository\QuestionRepository;
use App\Repository\AnswerSetRepository;
use App\Repository\AnswerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;

class DsommYamlToDbRecordsSyncer extends ModelToDbSyncer
{
    private array $parsedData = [];

    private const ANSWER_SET_EXTERNAL_ID = "answer_set_DSOMM1";
    private const MATURITY_LEVEL_EXTERNAL_ID_PREFIX = "DSOMM";
    private const ANSWER_YES_EXTERNAL_ID = "DSOMM1";
    private const ANSWER_NO_EXTERNAL_ID = "DSOMM2";

    private array $maturityLevels = [
        1 => "Level 1: Basic understanding of security practices",
        2 => "Level 2: Adoption of basic security practices",
        3 => "Level 3: High adoption of security practices",
        4 => "Level 4: Very high adoption of security practices",
        5 => "Level 5: Advanced deployment of security practices at scale",
    ];

    private array $maturityLevelsExternalIdByLevel = [];

    private array $practiceApplicableMaturityLevels = [];

    private array $practiceLevelExternalIdsByPracticeExternalIdAndLevel = [];

    private array $maturityLevelsByExternalIds = [];

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly BusinessFunctionRepository $businessFunctionRepository,
        private readonly PracticeRepository $practiceRepository,
        private readonly MaturityLevelRepository $maturityLevelRepository,
        private readonly PracticeLevelRepository $practiceLevelRepository,
        private readonly StreamRepository $streamRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly QuestionRepository $questionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly AnswerSetRepository $answerSetRepository,
        private readonly AnswerRepository $answerRepository,
    ) {
        parent::__construct(
            $this->entityManager,
            $this->businessFunctionRepository,
            $this->practiceRepository,
            $this->streamRepository,
            $this->maturityLevelRepository,
            $this->practiceLevelRepository,
            $this->activityRepository,
            $this->answerSetRepository,
            $this->answerRepository,
            $this->questionRepository,
        );
        $yamlContent = file_get_contents("{$this->getModelsFolder()}/generated.yaml");
        $this->parsedData = Yaml::parse($yamlContent);
    }

    /**
     * @return int[]
     */
    public function syncBusinessFunctions(): array
    {
        $added = $modified = 0;
        $order = 1;

        // Process each top-level category as a business function
        foreach ($this->parsedData as $topLevelCategory => $categoryData) {
            $entityStatus = $this->syncBusinessFunction(
                $this->generateExternalId($topLevelCategory),
                $topLevelCategory,
                "DSOMM {$topLevelCategory} Business Function",
                $order++
            );

            if ($entityStatus === ModelEntitySyncEnum::ADDED) {
                ++$added;
            } elseif ($entityStatus === ModelEntitySyncEnum::MODIFIED) {
                ++$modified;
            }
        }

        $this->entityManager->flush();

        return [$added, $modified];
    }

    public function syncSecurityPractices(): array
    {
        $added = $modified = 0;
        $order = 1;

        foreach ($this->parsedData as $topLevelCategory => $categoryData) {
            $businessFunctionId = $this->generateExternalId($topLevelCategory);
            foreach ($categoryData as $secondLevelCategory => $measures) {
                $entityStatus = $this->syncSecurityPractice(
                    $this->generateExternalId($secondLevelCategory),
                    $businessFunctionId,
                    $secondLevelCategory,
                    $this->generateShortName($secondLevelCategory),
                    "DSOMM {$secondLevelCategory} Security Practice",
                    "Detailed description for {$secondLevelCategory} security practice",
                    $order++
                );

                if ($entityStatus === ModelEntitySyncEnum::ADDED) {
                    ++$added;
                } elseif ($entityStatus === ModelEntitySyncEnum::MODIFIED) {
                    ++$modified;
                }
            }
        }

        $this->entityManager->flush();

        return [$added, $modified];
    }

    public function syncPracticeLevels(): array
    {
        foreach ($this->parsedData as $categoryData) {
            foreach ($categoryData as $secondLevelCategory => $streamsData) {
                foreach ($streamsData as $streamData) {
                    $practiceExternalId = $this->generateExternalId($secondLevelCategory);
                    $maturityLevelExternalId = $this->maturityLevelsExternalIdByLevel[$streamData["level"]];
                    if (!array_key_exists($practiceExternalId, $this->practiceApplicableMaturityLevels)) {
                        $this->practiceApplicableMaturityLevels[$practiceExternalId] = [];
                    }

                    if (!in_array($maturityLevelExternalId, $this->practiceApplicableMaturityLevels[$practiceExternalId])) {
                        $this->practiceApplicableMaturityLevels[$practiceExternalId][] = $maturityLevelExternalId;
                    }
                }
            }
        }

        $added = $modified = 0;
        foreach ($this->practiceApplicableMaturityLevels as $practiceExternalId => $levels) {
            foreach ($levels as $maturityLevelExternalId) {
                $practiceLevelExternalId = $practiceExternalId."x".$maturityLevelExternalId;
                $objective = "";
                $this->practiceLevelExternalIdsByPracticeExternalIdAndLevel[$practiceExternalId][$this->maturityLevelsByExternalIds[$maturityLevelExternalId]] = $practiceLevelExternalId;
                $entityStatus = $this->syncPracticeLevel($practiceLevelExternalId, $practiceExternalId, $maturityLevelExternalId, $objective);
                if ($entityStatus === ModelEntitySyncEnum::ADDED) {
                    ++$added;
                } elseif ($entityStatus === ModelEntitySyncEnum::MODIFIED) {
                    ++$modified;
                }
            }
        }

        $this->entityManager->flush();

        return [$added, $modified];
    }

    public function syncStreams(): array
    {
        $added = $modified = 0;
        $order = 1;

        foreach ($this->parsedData as $categoryData) {
            foreach ($categoryData as $secondLevelCategory => $measures) {
                $practiceExternalId = $this->generateExternalId($secondLevelCategory);
                $entityStatus = $this->syncStream(
                    $practiceExternalId,
                    $practiceExternalId,
                    $secondLevelCategory,
                    "DSOMM {$secondLevelCategory} Stream",
                    $order++
                );

                if ($entityStatus === ModelEntitySyncEnum::ADDED) {
                    ++$added;
                } elseif ($entityStatus === ModelEntitySyncEnum::MODIFIED) {
                    ++$modified;
                }
            }
        }

        $this->entityManager->flush();

        return [$added, $modified];
    }

    private function parseQuality(array|string $quality)
    {
        $result = [];

        if (is_string($quality)) {
            if (strpos($quality, "\n") !== false) {
                $lines = explode("\n", $quality);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, '- ') === 0) {
                        $line = substr($line, 2);
                    }

                    if (empty($line)) {
                        continue;
                    }

                    $result[] = $line;
                }
            } else {
                $result[] = $quality;
            }
        }

        return $result;
    }

    public function syncQuestions(): array
    {
        $added = $modified = 0;

        foreach ($this->parsedData as $categoryData) {
            foreach ($categoryData as $streamsData) {
                $order = 1;
                foreach ($streamsData as $thirdLevelCategory => $streamData) {
                    $entityStatus = $this->syncQuestion(
                        $streamData["uuid"],
                        $streamData["uuid"],
                        $thirdLevelCategory,
                        $this->parseQuality($streamData["measure"] ?? ""),
                        self::ANSWER_SET_EXTERNAL_ID,
                        $streamData["level"]
                    );

                    if ($entityStatus === ModelEntitySyncEnum::ADDED) {
                        ++$added;
                    } elseif ($entityStatus === ModelEntitySyncEnum::MODIFIED) {
                        ++$modified;
                    }
                }
            }
        }

        $this->entityManager->flush();

        return [$added, $modified];
    }

    private function cutToFullSentence(string $text, int $maxLength): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        $cutText = substr($text, 0, $maxLength);

        // Find the position of the last sentence-ending punctuation
        $lastPunctuation = max(
            strrpos($cutText, '.'),
            strrpos($cutText, '!'),
            strrpos($cutText, '?')
        );

        // If no punctuation found, return the original cut text (or whole word fallback could be used)
        if ($lastPunctuation === false) {
            return rtrim($cutText).'...'; // or just $cutText;
        }

        return substr($cutText, 0, $lastPunctuation + 1);
    }

    public function syncActivities(): array
    {
        $added = $modified = 0;

        foreach ($this->parsedData as $categoryData) {
            foreach ($categoryData as $secondLevelCategory => $streamsData) {
                $practiceExternalId = $this->generateExternalId($secondLevelCategory);
                foreach ($streamsData as $thirdLevelCategory => $streamData) {
                    $streamLevel = $streamData["level"];
                    $entityStatus = $this->syncActivity(
                        $streamData["uuid"],
                        $practiceExternalId,
                        $thirdLevelCategory,
                        $this->cutToFullSentence($streamData["risk"], 255),
                        $streamData["description"] ?? "",
                        $streamData["description"] ?? "",
                        $this->practiceLevelExternalIdsByPracticeExternalIdAndLevel[$practiceExternalId][$streamLevel]
                    );

                    if ($entityStatus === ModelEntitySyncEnum::ADDED) {
                        ++$added;
                    } elseif ($entityStatus === ModelEntitySyncEnum::MODIFIED) {
                        ++$modified;
                    }

                }
            }
        }

        $this->entityManager->flush();

        return [$added, $modified];
    }

    public function syncAnswerSets(): array
    {
        $added = $modified = 0;
        $externalId = self::ANSWER_SET_EXTERNAL_ID;
        $answerSetStatus = $this->syncAnswerSet($externalId);

        if ($answerSetStatus === ModelEntitySyncEnum::ADDED) {
            ++$added;
            $this->entityManager->flush();
        }

        $answerSetEntity = $this->answerSetRepository->findOneBy(['externalId' => $externalId]);

        $answers =
            [
                ["id" => self::ANSWER_NO_EXTERNAL_ID, "order" => 1, "text" => "No", "value" => 0, "weight" => 0],
                ["id" => self::ANSWER_YES_EXTERNAL_ID, "order" => 0, "text" => "Yes", "value" => 1, "weight" => 1],
            ];
        foreach ($answers as $answerData) {
            $entityStatus = $this->syncAnswer($answerSetEntity, $answerData['order'], $answerData['text'], $answerData['value'], $answerData['weight']);
            if ($entityStatus === ModelEntitySyncEnum::MODIFIED) {
                ++$modified;
            }
        }

        $this->entityManager->flush();

        return [$added, $modified];
    }

    public function syncMaturityLevels(): array
    {
        $added = $modified = 0;
        foreach ($this->maturityLevels as $level => $description) {
            $externalId = self::MATURITY_LEVEL_EXTERNAL_ID_PREFIX.$level;
            $entityStatus = $this->syncMaturityLevel($externalId, $level, $description);
            if ($entityStatus === ModelEntitySyncEnum::ADDED) {
                ++$added;
            } elseif ($entityStatus === ModelEntitySyncEnum::MODIFIED) {
                ++$modified;
            }
            $this->maturityLevelsExternalIdByLevel[$level] = $externalId;
            $this->maturityLevelsByExternalIds[$externalId] = $level;
        }

        $this->entityManager->flush();

        return [$added, $modified];
    }


    private function getModelsFolder(): string
    {
        return "{$this->parameterBag->get('kernel.project_dir')}/private/dsomm/src/assets/YAML/generated";
    }

    private function generateExternalId(string $name): string
    {
        return 'dsomm-'.strtolower(str_replace(' ', '-', $name));
    }

    private function generateShortName(string $name): string
    {
        // Take first letter of each word
        $words = explode(' ', $name);
        $shortName = '';
        foreach ($words as $word) {
            $shortName .= strtoupper(substr($word, 0, 1));
        }

        return $shortName;
    }
}
