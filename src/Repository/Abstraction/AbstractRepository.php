<?php

declare(strict_types=1);

namespace App\Repository\Abstraction;

use App\Interface\EntityInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Query\Parameter;
use Doctrine\Persistence\ManagerRegistry;

abstract class AbstractRepository extends ServiceEntityRepository
{
    public static int $defaultPage = 1;

    /**
     * AbstractRepository constructor.
     *
     * @return void
     */
    public function __construct(ManagerRegistry $registry, string $entityClassName)
    {
        parent::__construct($registry, $entityClassName);
        $config = $this->getEntityManager()->getConfiguration();
        $config->addCustomNumericFunction('STRING', 'App\Query\CastForLike');
    }

    /**
     * Delete the object by the given id from the database.
     *
     * @param EntityInterface $model       the object to be deleted
     * @param bool            $forceDelete a flag that indicates whether this object should be definitively deleted (no trash)
     *
     * @return void
     *
     * @throws OptimisticLockException
     */
    public function delete(EntityInterface $model, bool $forceDelete = false)
    {
        if ($forceDelete) {
            $this->getEntityManager()->remove($model);
            $this->getEntityManager()->flush();
        } else {
            $this->cascadeSoftDelete($model);
        }
    }

    /**
     * Deletes the object.
     *
     * @param EntityInterface $model the object to be trashed
     *
     * @throws OptimisticLockException
     */
    public function trash(EntityInterface $model): void
    {
        $reflection = $this->getClassMetadata()->newInstance();
        foreach ($reflection::$childProperties as $childProperty => $parentProperty) {
            foreach ($model->{'get'.ucfirst($childProperty)}() as $entity) {
                $this->getEntityManager()->getRepository(get_class($entity))->trash($entity);
                $this->getEntityManager()->persist($entity);
            }
        }
        $model->setDeletedAt(new \DateTime('NOW'));
        $this->getEntityManager()->flush();
    }

    /**
     * Hard deletes the object and all its childProperty related objects.
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function cascadeHardDelete(EntityInterface $model)
    {
        $reflection = $this->getClassMetadata()->newInstance();
        foreach ($reflection::$childProperties as $childProperty => $parentProperty) {
            foreach ($model->{'get'.ucfirst($childProperty)}() as $entity) {
                $this->getEntityManager()->getRepository(get_class($entity))->cascadeHardDelete($entity);
                $this->getEntityManager()->persist($entity);
            }
        }
        $this->getEntityManager()->remove($model);
        $this->getEntityManager()->flush();
    }

    /**
     * Soft deletes the object and all other objects related via childProperties.
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function cascadeSoftDelete(EntityInterface $model)
    {
        $reflection = $this->getClassMetadata()->newInstance();
        foreach ($reflection::$childProperties as $childProperty => $parentProperty) {
            foreach ($model->{'get'.ucfirst($childProperty)}() as $entity) {
                $this->getEntityManager()->getRepository(get_class($entity))->cascadeSoftDelete($entity);
                $this->getEntityManager()->persist($entity);
            }
        }
        $model->setDeletedAt(new \DateTime('NOW'));
        $this->getEntityManager()->flush();
    }

    /**
     * Restores the deleted status of this object.
     *
     * @param EntityInterface $model the object to be restored
     *
     * @return void
     *
     * @throws OptimisticLockException
     */
    public function restore(EntityInterface $model)
    {
        $model->setDeletedAt(null);
        $this->getEntityManager()->flush();
    }

    public function findAllIn(array $inCollection): array
    {
        $reflection = $this->getClassMetadata()->newInstance();
        $qb = $this->createQueryBuilder($reflection->getAliasName());
        $qb->where($reflection->getAliasName().' in (:inCollection)')
            ->setParameters(new ArrayCollection([new Parameter('inCollection', $inCollection)]));

        return $qb->getQuery()->getResult();
    }
}
