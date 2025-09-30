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

// #BlockStart number=248 id=_19_0_3_40d01a2_1646749822065_209622_5088_#_0

use App\Entity\Validation;
use App\Repository\Abstraction\AbstractRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Validation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Validation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Validation[]    findAll()
 * @method Validation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ValidationRepository extends AbstractRepository
{
    /**
     * ValidationRepository constructor.
     *
     * @return void
     */
    public function __construct(ManagerRegistry $registry, string $entityClassName = Validation::class)
    {
        parent::__construct($registry, $entityClassName);
    }

    /**
     * Duplicate the object and save the duplicate.
     *
     * @param Validation $validation The object to be duplicated
     *
     * @throws ORMException
     */
    public function duplicate(Validation $validation): Validation
    {
        /** @var Validation $clone */
        $clone = $validation->getCopy();
        $this->getEntityManager()->persist($clone);
        $this->getEntityManager()->flush();

        return $clone;
    }

// #BlockEnd number=248

}
