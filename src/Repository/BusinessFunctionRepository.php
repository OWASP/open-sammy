<?php

/**
 * This is automatically generated file using the Codific Prototizer
 * PHP version 8
 * @category PHP
 * @author   CODIFIC <info@codific.com>
 * @see     http://codific.com
 */

declare(strict_types=1);

namespace App\Repository;

// #BlockStart number=48 id=_19_0_3_40d01a2_1635864197250_327408_5981_#_0

use App\Entity\BusinessFunction;
use App\Entity\Metamodel;
use App\Repository\Abstraction\AbstractRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method BusinessFunction|null find($id, $lockMode = null, $lockVersion = null)
 */
class BusinessFunctionRepository extends AbstractRepository
{
    /**
     * @throws \Exception
     */
    public function findOneBy(array $criteria, ?array $orderBy = null, bool $expertMode = false, ?Metamodel $metamodel = null): ?object
    {
        if ($metamodel === null && $expertMode === false) {
            throw new \Exception('You must either provide a metamodel, or enable expert mode');
        }

        $criteria = ($metamodel !== null) ? array_merge($criteria, ['metamodel' => $metamodel]) : $criteria;

        return parent::findOneBy($criteria, $orderBy);
    }

    /**
     * @throws \Exception
     */
    public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null, bool $expertMode = false, ?Metamodel $metamodel = null): array
    {
        if ($metamodel === null && $expertMode === false) {
            throw new \Exception('You must either provide a metamodel, or enable expert mode');
        }

        $criteria = ($metamodel !== null) ? array_merge($criteria, ['metamodel' => $metamodel]) : $criteria;

        return parent::findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * @throws \Exception
     */
    public function findAll(bool $expertMode = false, ?Metamodel $metamodel = null): array
    {
        return self::findBy([], null, null, null, $expertMode, $metamodel);
    }

    /**
     * BusinessFunctionRepository constructor.
     *
     * @return void
     */
    public function __construct(ManagerRegistry $registry, string $entityClassName = BusinessFunction::class)
    {
        parent::__construct($registry, $entityClassName);
    }

    /**
     * Duplicate the object and save the duplicate.
     *
     * @param BusinessFunction $businessFunction The object to be duplicated
     */
    public function duplicate(BusinessFunction $businessFunction): BusinessFunction
    {
        $clone = $businessFunction->getCopy();
        $this->getEntityManager()->persist($clone);
        $this->getEntityManager()->flush();

        return $clone;
    }

    /**
     * @return BusinessFunction[]
     */
    public function findOptimized(?Metamodel $metamodel): array
    {
        $qb = $this->createQueryBuilder('business_function')
            ->leftJoin('business_function.businessFunctionPractices', '_practice')
            ->addSelect('_practice')
            ->leftJoin('_practice.practiceStreams', '_stream')
            ->addSelect('_stream')
            ->leftJoin('_practice.practicePracticeLevels', 'practice_level')
            ->addSelect('practice_level')
            ->join('_stream.streamActivities', '_activity')
            ->addSelect('_activity')
            ->join('_activity.activityQuestions', '_question')
            ->addSelect('_question')
            ->join('_question.answerSet', 'answer_set')
            ->addSelect('answer_set')
            ->join('answer_set.answerSetAnswers', 'answer')
            ->addSelect('answer')
            ->orderBy('business_function.order', 'ASC')
            ->addOrderBy('_practice.order', 'ASC')
            ->addOrderBy('_stream.order', 'ASC');
        if ($metamodel !== null) {
            $qb->where('business_function.metamodel = :metamodel')
                ->setParameter('metamodel', $metamodel);
        }

        return $qb->getQuery()->getResult();
    }

// #BlockEnd number=48

}
