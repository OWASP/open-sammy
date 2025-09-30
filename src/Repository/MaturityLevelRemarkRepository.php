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


// #BlockStart number=307 id=_#_0

use App\Entity\MaturityLevelRemark;
use App\Entity\Remark;
use App\Interface\EntityInterface;
use App\Repository\Abstraction\AbstractRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method MaturityLevelRemark|null find($id, $lockMode = null, $lockVersion = null)
 * @method MaturityLevelRemark|null findOneBy(array $criteria, array $orderBy = null)
 * @method MaturityLevelRemark[]    findAll()
 * @method MaturityLevelRemark[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MaturityLevelRemarkRepository extends AbstractRepository
{
    /**
     * MaturityLevelRemarkRepository constructor.
     *
     * @return void
     */
    public function __construct(ManagerRegistry $registry, string $entityClassName = MaturityLevelRemark::class)
    {
        parent::__construct($registry, $entityClassName);
    }

    /**
     * Duplicate the object and save the duplicate.
     *
     * @param MaturityLevelRemark $maturityLevelRemark The object to be duplicated
     *
     * @throws ORMException
     */
    public function duplicate(MaturityLevelRemark $maturityLevelRemark): MaturityLevelRemark
    {
        $clone = $maturityLevelRemark->getCopy();
        $this->getEntityManager()->persist($clone);
        $this->getEntityManager()->flush();

        return $clone;
    }

    /**
     * Delete the object from the database.
     *
     * @param EntityInterface $model       The object to be deleted
     * @param bool            $forceDelete A flag that indicates whether this object should be definitively deleted (no trash)
     *
     * @return void
     *
     * @throws ORMException
     */
    public function delete(EntityInterface $model, bool $forceDelete = false)
    {
        parent::delete($model, true);
    }

    public function findByRemarkIndexedByMaturityLevel(Remark $remark): array
    {
        return $this->createQueryBuilder('maturityLevelRemark', 'maturityLevelRemark.maturityLevel')
            ->where('maturityLevelRemark.remark = :remark')
            ->setParameter('remark', $remark)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MaturityLevelRemark[]
     */
    public function findAllForMultipleStages(array $stages): array
    {
        return $this->createQueryBuilder('maturityLevelRemark')
            ->join('maturityLevelRemark.remark', 'remark')
            ->join('remark.stage', 'stage')
            ->where('stage IN (:stages)')
            ->setParameter('stages', $stages)
            ->getQuery()
            ->getResult();
    }
}

// #BlockEnd number=307
