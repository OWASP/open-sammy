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

// #BlockStart number=257 id=_19_0_3_40d01a2_1646802256748_639463_4967_#_0

use App\Entity\Group;
use App\Entity\GroupUser;
use App\Entity\User;
use App\Interface\EntityInterface;
use App\Pagination\Paginator;
use App\Repository\Abstraction\AbstractRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Group|null find($id, $lockMode = null, $lockVersion = null)
 * @method Group|null findOneBy(array $criteria, array $orderBy = null)
 * @method Group[]    findAll()
 * @method Group[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupRepository extends AbstractRepository
{
    /**
     * GroupRepository constructor.
     *
     * @return void
     */
    public function __construct(ManagerRegistry $registry, string $entityClassName = Group::class)
    {
        parent::__construct($registry, $entityClassName);
    }

    /**
     * Duplicate the object and save the duplicate.
     *
     * @param Group $group The object to be duplicated
     *
     * @throws ORMException
     */
    public function duplicate(Group $group): Group
    {
        $clone = $group->getCopy();
        $this->getEntityManager()->persist($clone);
        $this->getEntityManager()->flush();

        return $clone;
    }


    /**
     * @return Paginator|array
     */
    public function findAllIndexedById(string $searchTerm = '', int $page = 1, int $pageSize = 10, bool $returnPaginated = false): Paginator|array
    {
        $qb = $this->createQueryBuilder('_group', '_group.id')
            ->leftJoin('_group.groupGroupProjects', 'group_projects')
            ->addSelect('group_projects')
            ->leftJoin('_group.groupGroupUsers', 'group_users')
            ->addSelect('group_users');

        if ($searchTerm !== '') {
            $qb->andWhere('LOWER(_group.name) LIKE :searchString')
                ->setParameter('searchString', "%".$searchTerm."%");
        }

        $qb->orderBy('_group.name', 'ASC');

        return ($returnPaginated) ? (new Paginator($qb, $pageSize))->paginate($page) : $qb->getQuery()->getResult();
    }

    public function findAllByUser(User $user): array
    {
        return $this->createQueryBuilder('_group', '_group.id')
            ->join(GroupUser::class, 'groupUser', 'WITH', '_group.id = groupUser.group')
            ->where('groupUser.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    public function findWithoutParentsPaginated(int $page = 1): Paginator
    {
        $qb = $this->createQueryBuilder('_group', '_group.id')
            ->leftJoin('_group.groupGroupProjects', 'group_projects')
            ->addSelect('group_projects')
            ->leftJoin('_group.groupGroupUsers', 'group_users')
            ->addSelect('group_users')
            ->andWhere("_group.parent IS NULL");

        $qb->orderBy('_group.name', 'ASC');

        return (new Paginator($qb, 10))->paginate($page);
    }

    public function trash(EntityInterface $group): void
    {
        parent::trash($group);
    }
// #BlockEnd number=257

}
