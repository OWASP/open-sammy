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

// #BlockStart number=230 id=_19_0_3_40d01a2_1646749816322_28239_5028_#_0

use App\Entity\Evaluation;
use App\Repository\Abstraction\AbstractRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Evaluation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Evaluation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Evaluation[]    findAll()
 * @method Evaluation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EvaluationRepository extends AbstractRepository
{
    /**
     * EvaluationRepository constructor.
     *
     * @return void
     */
    public function __construct(ManagerRegistry $registry, string $entityClassName = Evaluation::class)
    {
        parent::__construct($registry, $entityClassName);
    }

    /**
     * Duplicate the object and save the duplicate.
     *
     * @param Evaluation $evaluation The object to be duplicated
     *
     * @throws ORMException
     */
    public function duplicate(Evaluation $evaluation): Evaluation
    {
        /** @var Evaluation $clone */
        $clone = $evaluation->getCopy();
        $this->getEntityManager()->persist($clone);
        $this->getEntityManager()->flush();

        return $clone;
    }

// #BlockEnd number=230

}
