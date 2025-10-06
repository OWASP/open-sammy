<?php

declare(strict_types=1);

namespace App\Repository\Abstraction;

use App\Entity\Abstraction\AbstractEntity;
use App\Interface\EntityInterface;
use App\Pagination\Paginator;
use App\Util\AbstractRepositoryParameters;
use App\Util\RepositoryParameters;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
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

    /**
     * Get paginated list.
     */
    public function getPaginatedList(QueryBuilder $queryBuilder, RepositoryParameters $repositoryParameters): Paginator
    {
        /** @var AbstractEntity $entityName */
        $entityName = $this->getEntityName();

        $page = $repositoryParameters->getPage() ?? self::$defaultPage;

        $entityInstance = new $entityName();
        $filterFields = $entityInstance->getFilterFields();

        $queryBuilder = $this->generateSearchQuery($queryBuilder, $repositoryParameters, $filterFields);

        if (!$this->getEntityManager()->getFilters()->isEnabled('deleted_entity')) {
            $this->getEntityManager()->getFilters()->enable('deleted_entity');
        }
        $filter = $this->getEntityManager()->getFilters()->getFilter('deleted_entity');
        $filter->setParameter('deleted', $repositoryParameters->getShowDeleted());

        if (count($repositoryParameters->getOrderBy()) == 0) {
            $repositoryParameters->setOrderBy([[AbstractRepositoryParameters::$defaultOrderColumn.' '.AbstractRepositoryParameters::$defaultOrderDirection]]);
        }
        foreach ($repositoryParameters->getOrderBy() as $order) {
            $orderColumn = $order[0] ?? '';
            $orderDirection = $order[1] ?? '';
            if (property_exists($entityName, $orderColumn)) {
                $queryBuilder->addOrderBy($this->getClassMetadata()->newInstance()->getAliasName().'.'.$orderColumn, $orderDirection);
            } else {
                if (str_contains($orderColumn, '.')) {
                    $queryBuilder->addOrderBy($orderColumn, $orderDirection);
                }
            }
        }

        return (new Paginator($queryBuilder, $repositoryParameters->getPageSize()))->paginate($page);
    }

    /**
     * Generates where clause for the advanced search.
     *
     * @param QueryBuilder $queryBuilder Doctrine Query builder
     * @param array        $fields       array of columns that should be matched against the $escapeSearch string
     */
    protected function generateSearchQuery(QueryBuilder $queryBuilder, RepositoryParameters $repositoryParameters, array $fields = []): QueryBuilder
    {
        $search = $repositoryParameters->getFilter();
        if ($search != null) {
            $searchWhere = '';
            $searchArray = array_filter(explode('+', $search));
            foreach ($searchArray as $i => $searchString) {
                $subQuery = '';
                foreach ($fields as $searchField) {
                    $subQuery .= "$searchField LIKE :searchString$i OR ";
                }
                foreach ($repositoryParameters->getAdditionalSearchFields() as $searchField) {
                    $subQuery .= "$searchField LIKE :searchString$i OR ";
                }
                $searchWhere .= $subQuery;
                $queryBuilder->setParameter("searchString$i", '%'.trim($searchString).'%');
            }
            $andWhere = trim($searchWhere, ' OR ');
            $queryBuilder->andWhere('('.$andWhere.')');
        }

        return $queryBuilder;
    }

    /**
     * Returns a collection of the supplied entity class
     * filtered and ordered by the supplied repository parameters.
     */
    public function getSearchResult(RepositoryParameters $repositoryParameters): ?array
    {
        /** @var EntityInterface $reflection */
        $reflection = $this->getClassMetadata()->newInstance();
        $queryBuilder = $this->createQueryBuilder($reflection->getAliasName());
        $queryBuilder = $this->generateSearchQuery($queryBuilder, $repositoryParameters, $reflection->getFilterFields());
        foreach ($repositoryParameters->getOrderBy() as $order) {
            $queryBuilder->addOrderBy($order[0], $order[1]);
        }

        return $queryBuilder->getQuery()->getResult();
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
