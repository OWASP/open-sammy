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

// #BlockStart number=221 id=_19_0_3_40d01a2_1646749661905_888722_4949_#_0

use App\Entity\Remark;
use App\Repository\Abstraction\AbstractRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Remark|null find($id, $lockMode = null, $lockVersion = null)
 * @method Remark|null findOneBy(array $criteria, array $orderBy = null)
 * @method Remark[]    findAll()
 * @method Remark[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RemarkRepository extends AbstractRepository
{
    /**
     * RemarkRepository constructor.
     *
     * @return void
     */
    public function __construct(ManagerRegistry $registry, string $entityClassName = Remark::class)
    {
        parent::__construct($registry, $entityClassName);
    }

    /**
     * Duplicate the object and save the duplicate.
     *
     * @param Remark $remark The object to be duplicated
     *
     * @throws ORMException
     */
    public function duplicate(Remark $remark): Remark
    {
        $clone = $remark->getCopy();
        $this->getEntityManager()->persist($clone);
        $this->getEntityManager()->flush();

        return $clone;
    }

    /**
     * @return Remark[]
     */
    public function findAllForMultipleStages(array $stages): array
    {
        return $this->createQueryBuilder('remark')
            ->where('remark.stage in (:stages)')
            ->setParameter('stages', $stages)
            ->orderBy('remark.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Remark[]
     */
    public function findByAssessmentStreams(array $assessmentStreams, string $createdAtOrder = 'DESC'): array
    {
        return $this->createQueryBuilder('remark')
            ->join('remark.stage', 'stage')
            ->join('stage.assessmentStream', 'assessmentStream')
            ->where('stage.assessmentStream in (:assessmentStreams)')
            ->andWhere('remark.deletedAt IS NULL')
            ->setParameter('assessmentStreams', $assessmentStreams)
            ->orderBy('remark.createdAt', $createdAtOrder)
            ->getQuery()
            ->getResult();
    }

//    public function deepRestore(EntityInterface $remark): void
//    {
//        parent::restore($remark);
//
//        $isDeletedFilterEnabled = false;
//        if ($this->getEntityManager()->getFilters()->isEnabled('deleted_entity')) {
//            $this->getEntityManager()->getFilters()->disable('deleted_entity');
//            $isDeletedFilterEnabled = true;
//        }
//
//        $maturityLevelRemarkRepository = $this->getEntityManager()->getRepository(MaturityLevelRemark::class);
//
//        $maturityLevelRemarks = $maturityLevelRemarkRepository->findBy(['remark' => $remark]);
//        foreach ($maturityLevelRemarks as $maturityLevelRemark) {
//            $maturityLevelRemarkRepository->restore($maturityLevelRemark);
//        }
//
//        if ($isDeletedFilterEnabled) {
//            $this->getEntityManager()->getFilters()->enable('deleted_entity');
//        }
//    }
// #BlockEnd number=221

}
