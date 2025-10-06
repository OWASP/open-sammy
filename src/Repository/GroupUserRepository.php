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


// #BlockStart number=289 id=_#_0

use App\Entity\GroupUser;
use App\Entity\User;
use App\Interface\EntityInterface;
use App\Repository\Abstraction\AbstractRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method GroupUser|null find($id, $lockMode = null, $lockVersion = null)
 * @method GroupUser|null findOneBy(array $criteria, array $orderBy = null)
 * @method GroupUser[]    findAll()
 * @method GroupUser[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupUserRepository extends AbstractRepository
{
    /**
     * GroupUserRepository constructor.
     *
     * @return void
     */
    public function __construct(ManagerRegistry $registry, string $entityClassName = GroupUser::class)
    {
        parent::__construct($registry, $entityClassName);
    }

    /**
     * Duplicate the object and save the duplicate.
     *
     * @param GroupUser $groupUser The object to be duplicated
     *
     * @throws ORMException
     */
    public function duplicate(GroupUser $groupUser): GroupUser
    {
        $clone = $groupUser->getCopy();
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

    public function findByUserAndGroups(User $user, array $groups): array
    {
        return $this->createQueryBuilder('groupUser')
            ->where('groupUser.user = :user')
            ->andWhere('groupUser.group IN (:groups)')
            ->setParameter('user', $user)
            ->setParameter('groups', $groups)
            ->getQuery()
            ->getResult();
    }
}

// #BlockEnd number=289
