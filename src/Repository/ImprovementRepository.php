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

// #BlockStart number=239 id=_19_0_3_40d01a2_1646749819286_690779_5058_#_0

use App\Entity\AssessmentStream;
use App\Entity\Improvement;
use App\Repository\Abstraction\AbstractRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Improvement|null find($id, $lockMode = null, $lockVersion = null)
 * @method Improvement|null findOneBy(array $criteria, array $orderBy = null)
 * @method Improvement[]    findAll()
 * @method Improvement[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ImprovementRepository extends AbstractRepository
{
    /**
     * ImprovementRepository constructor.
     *
     * @return void
     */
    public function __construct(ManagerRegistry $registry, string $entityClassName = Improvement::class)
    {
        parent::__construct($registry, $entityClassName);
    }

    /**
     * Duplicate the object and save the duplicate.
     *
     * @param Improvement $improvement The object to be duplicated
     *
     * @throws ORMException
     */
    public function duplicate(Improvement $improvement): Improvement
    {
        /** @var Improvement $clone */
        $clone = $improvement->getCopy();
        $this->getEntityManager()->persist($clone);
        $this->getEntityManager()->flush();

        return $clone;
    }

    /**
     * @param AssessmentStream[] $assessmentStreams
     */
    public function findByAssessmentStreams(array $assessmentStreams): array
    {
        return $this->createQueryBuilder('improvement', 'improvement.assessmentStream')
            ->where('improvement.assessmentStream IN (:old)')
            ->andWhere('improvement.new IS NULL')
            ->setParameter('old', $assessmentStreams)
            ->getQuery()
            ->getResult();
    }

// #BlockEnd number=239

}
