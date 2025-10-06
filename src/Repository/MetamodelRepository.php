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

// #BlockStart number=168 id=_19_0_3_40d01a2_1677917556636_72280_4869_#_0

use App\Entity\Metamodel;
use App\Repository\Abstraction\AbstractRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Metamodel|null find($id, $lockMode = null, $lockVersion = null)
 * @method Metamodel|null findOneBy(array $criteria, array $orderBy = null)
 * @method Metamodel[]    findAll()
 * @method Metamodel[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MetamodelRepository extends AbstractRepository
{
    /**
     * MetamodelRepository constructor.
     *
     * @return void
     */
    public function __construct(ManagerRegistry $registry, string $entityClassName = Metamodel::class)
    {
        parent::__construct($registry, $entityClassName);
    }

    /**
     * Duplicate the object and save the duplicate.
     *
     * @param Metamodel $metamodel The object to be duplicated
     *
     * @throws ORMException
     */
    public function duplicate(Metamodel $metamodel): Metamodel
    {
        $clone = $metamodel->getCopy();
        $this->getEntityManager()->persist($clone);
        $this->getEntityManager()->flush();

        return $clone;
    }

// #BlockEnd number=168

}
