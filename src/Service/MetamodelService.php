<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Abstraction\AbstractEntity;
use App\Entity\Answer;
use App\Entity\BusinessFunction;
use App\Entity\MaturityLevel;
use App\Entity\Metamodel;
use App\Entity\Practice;
use App\Entity\Question;
use App\Entity\Stream;
use App\Enum\MetamodelType;
use App\Repository\BusinessFunctionRepository;
use App\Repository\MetamodelRepository;
use App\Utils\Constants;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class MetamodelService
{
    private const DSOMM_MAX_SCORE = 1;

    public function __construct(
        private readonly BusinessFunctionRepository $businessFunctionRepository,
        private readonly MetamodelRepository $metamodelRepository,
        private readonly TagAwareCacheInterface $redisCache,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function getSAMM(): ?Metamodel
    {
        return $this->metamodelRepository->find(Constants::SAMM_ID);
    }


    /**
     * @return BusinessFunction[]
     *
     * @throws CacheException
     * @throws InvalidArgumentException
     */
    public function getBusinessFunctions(?Metamodel $metamodel = null): array
    {
        if ($metamodel === null) {
            $metamodel = $this->getSAMM();
        }
        /** @var ItemInterface $cache */
        $cache = $this->redisCache->getItem('businessFunctions-'.$metamodel->getId());
        if (!$cache->isHit()) {
            $businessFunctions = $this->businessFunctionRepository->findOptimized($metamodel);
            $cache->expiresAfter(Constants::DEFAULT_CACHE_EXPIRATION);
            $cache->set($businessFunctions);
            $cache->tag('businessFunctions-'.$metamodel->getId());
            $this->redisCache->save($cache);
        }

        return $cache->get();
    }

    /**
     * @return Practice[]
     *
     * @throws CacheException
     * @throws InvalidArgumentException
     */
    public function getPractices(?Metamodel $metamodel = null): array
    {
        $practices = [];
        foreach ($this->getBusinessFunctions($metamodel) as $businessFunction) {
            foreach ($businessFunction->getBusinessFunctionPractices() as $practice) {
                $practices[] = $practice;
            }
        }

        return $practices;
    }

    public function getPractice(?string $slug, ?Metamodel $metamodel = null): Practice
    {
        $practices = $this->getPractices($metamodel);
        if ($slug !== '' && $slug !== null) {
            foreach ($practices as $practice) {
                if ($practice->getSlug() === $slug) {
                    return $practice;
                }
            }
        }

        return $this->getPractices($metamodel)[0];
    }

    /**
     * @pre $indexBy should exist as a property on Stream entity, otherwise no guarantees are provided for this method
     *
     * @return Stream[]
     *
     * @throws CacheException
     * @throws InvalidArgumentException
     */
    public function getStreams(?Metamodel $metamodel = null, ?string $indexBy = null): array
    {
        $streams = [];
        foreach ($this->getPractices($metamodel) as $practice) {
            foreach ($practice->getPracticeStreams() as $stream) {
                if ($indexBy !== null && method_exists(Stream::class, 'get'.ucfirst($indexBy))) {
                    /* @phpstan-ignore-next-line */
                    $streams[$stream->{'get'.ucfirst($indexBy)}()] = $stream;
                } else {
                    $streams[] = $stream;
                }
            }
        }

        return $streams;
    }

    /**
     * @return Question[]
     */
    public function getQuestionsByStream(Stream $stream): array
    {
        $questions = [];
        foreach ($stream->getStreamActivities() as $activity) {
            foreach ($activity->getActivityQuestions() as $question) {
                $questions[] = $question;
            }
        }

        usort($questions, function($a, $b) {
            return $a->getOrder() <=> $b->getOrder();
        });

        return $questions;
    }

    /**
     * @return MaturityLevel[]
     */
    public function getMaturityLevels(?Metamodel $metamodel = null): array
    {
        $maturityLevels = [];
        foreach ($this->getPractices($metamodel) as $practice) {
            foreach ($practice->getPracticePracticeLevels() as $practiceLevel) {
                if (!isset($maturityLevels[$practiceLevel->getMaturityLevel()->getLevel()])) {
                    $maturityLevels[$practiceLevel->getMaturityLevel()->getLevel()] = $practiceLevel->getMaturityLevel();
                }
            }
        }

        return $maturityLevels;
    }

    /**
     * @return Answer[]
     */
    public function getAnswers(?Metamodel $metamodel = null): array
    {
        $answers = [];
        foreach ($this->getPractices($metamodel) as $practice) {
            foreach ($practice->getPracticeStreams() as $stream) {
                foreach ($stream->getStreamActivities() as $activity) {
                    foreach ($activity->getActivityQuestions() as $question) {
                        foreach ($question->getAnswers() as $answer) {
                            $answers[$answer->getId()] = $answer;
                        }
                    }
                }
            }
        }

        return $answers;
    }

    /**
     * Used to intersect metamodel cached arrays with repository find methods' results.
     *
     * @param AbstractEntity[] $first
     * @param AbstractEntity[] ...$arrays
     *
     * @return AbstractEntity[]
     */
    public static function entityArrayIntersectById(array $first, array ...$arrays): array
    {
        $idArrays = [];
        $idArrays[] = array_map(fn(AbstractEntity $entity) => $entity->getId(), $first);
        foreach ($arrays as $array) {
            $idArrays[] = array_map(fn(AbstractEntity $entity) => $entity->getId(), $array);
        }
        $intersectIds = array_intersect(...$idArrays);

        return array_filter($first, fn(AbstractEntity $element) => in_array($element->getId(), $intersectIds, true));
    }
    
    public function createDSOMM(): Metamodel
    {
        $metamodel = new Metamodel();
        $metamodel->setName("DSOMM variation");
        $metamodel->setMaxScore(self::DSOMM_MAX_SCORE);
        $metamodel->setType(MetamodelType::DSOMM);

        $this->entityManager->persist($metamodel);
        $this->entityManager->flush();

        return $metamodel;
    }
}
