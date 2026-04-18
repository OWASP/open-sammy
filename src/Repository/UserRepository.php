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

// #BlockStart number=33 id=_19_0_3_40d01a2_1635864059122_214388_5671_#_0

use App\Entity\Group;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\Role;
use App\Interface\EntityInterface;
use App\Pagination\Paginator;
use App\Repository\Abstraction\AbstractRepository;
use App\Service\ResetPasswordService;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends AbstractRepository implements PasswordUpgraderInterface
{
    /**
     * UserRepository constructor.
     *
     * @return void
     */
    public function __construct(ManagerRegistry $registry, string $entityClassName = User::class)
    {
        parent::__construct($registry, $entityClassName);
    }

    /**
     * Duplicate the object and save the duplicate.
     *
     * @param User $user The object to be duplicated
     */
    public function duplicate(User $user): User
    {
        $clone = $user->getCopy();
        $this->getEntityManager()->persist($clone);
        $this->getEntityManager()->flush();

        return $clone;
    }

    /**
     * Deletes the object.
     *
     * @param EntityInterface $model the object to be trashed
     */
    public function trash(EntityInterface $model): void
    {
        $reflection = $this->getClassMetadata()->newInstance();
        // hard delete all many-to-many classes
        foreach ($reflection::$manyToManyProperties as $collectionProperty => $parentProperty) {
            /** @phpstan-ignore-next-line  $entity */
            foreach ($model->{'get'.ucfirst($collectionProperty)}() as $entity) {
                $this->getEntityManager()->remove($entity);
            }
        }
        $model->setDeletedAt(new \DateTime('NOW'));
        $this->getEntityManager()->flush();
    }

    /**
     * Upgrade password to the current algorithm.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new \Exception('user is not instance of User');
        }
        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }

    public function findUserByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * @return User[]
     */
    public function findAllExcept(User $user): array
    {
        return $this->createQueryBuilder('user', 'user.id')
            ->andWhere('user != :exceptionUser')
            ->setParameter('exceptionUser', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ?User
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findNonAdminUserByEmail(string $email): ?User
    {
        try {
            $user = $this->createQueryBuilder('user')
                ->where('user.email = :email')
                ->andWhere('JSON_CONTAINS(user.roles, :adminRole) = 0')
                ->andWhere('user.deletedAt is null')
                ->setParameter('email', $email)
                ->setParameter('adminRole', '"'.Role::ADMINISTRATOR->string().'"')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (NonUniqueResultException) {
            throw new UserNotFoundException();
        }
        if ($user === null) {
            throw new UserNotFoundException();
        }

        return $user;
    }

    /**
     * @return ?User
     */
    public function findAdminUserByEmail(string $email): ?User
    {
        try {
            $user = $this->createQueryBuilder('user')
                ->where('user.email = :email')
                ->andWhere('JSON_CONTAINS(user.roles, :adminRole) = 1')
                ->andWhere('user.deletedAt is null')
                ->setParameter('email', $email)
                ->setParameter('adminRole', '"'.Role::ADMINISTRATOR->string().'"')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (NonUniqueResultException) {
            throw new UserNotFoundException();
        }
        if ($user === null) {
            throw new UserNotFoundException();
        }

        return $user;
    }

    /**
     * @return User[]
     */
    public function findAllNonAdmins(): array
    {
        return $this->createQueryBuilder('user')
            ->andWhere("JSON_CONTAINS(user.roles, :adminRole, '$') = 0")
            ->andWhere('user.deletedAt is null')
            ->setParameter('adminRole', '"'.Role::ADMINISTRATOR->string().'"')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return User[]
     */
    public function findAllAdmins(): array
    {
        return $this->createQueryBuilder('user')
            ->andWhere("JSON_CONTAINS(user.roles, :adminRole, '$') = 1")
            ->andWhere('user.deletedAt is null')
            ->setParameter('adminRole', '"'.Role::ADMINISTRATOR->string().'"')
            ->getQuery()
            ->getResult();
    }

    /**
     * @throws NonUniqueResultException
     */
    public function loadUserByPasswordResetHash(string $hash): ?User
    {
        $qb = $this->createQueryBuilder('user')
            ->where('user.passwordResetHash = :hash')
            ->andWhere("JSON_CONTAINS(user.roles, :adminRole, '$') = 0")
            ->andWhere('LENGTH(user.passwordResetHash) > 0')
            ->andWhere('user.deletedAt IS NULL')
            ->andWhere('user.passwordResetHashExpiration > CURRENT_TIMESTAMP()')
            ->setParameter('adminRole', '"'.Role::ADMINISTRATOR->string().'"')
            ->setParameter('hash', ResetPasswordService::hashToken($hash));

        $user = $qb->getQuery()->getOneOrNullResult();
        if ($user === null) {
            throw new UserNotFoundException();
        }

        return $user;
    }

    /**
     * @throws NonUniqueResultException
     */
    public function loadAdminByPasswordResetHash(string $hash): ?User
    {
        $qb = $this->createQueryBuilder('user')
            ->where('user.passwordResetHash = :hash')
            ->andWhere("JSON_CONTAINS(user.roles, :adminRole, '$') = 1")
            ->andWhere('LENGTH(user.passwordResetHash) > 0')
            ->andWhere('user.deletedAt IS NULL')
            ->andWhere('user.passwordResetHashExpiration > CURRENT_TIMESTAMP()')
            ->setParameter('adminRole', '"'.Role::ADMINISTRATOR->string().'"')
            ->setParameter('hash', ResetPasswordService::hashToken($hash));

        $user = $qb->getQuery()->getOneOrNullResult();
        if ($user === null) {
            throw new UserNotFoundException();
        }

        return $user;
    }

    public function findAllIndexedByName(string $searchTerm = '', int $page = 1, int $pageSize = 10, bool $returnPaginated = false): Paginator|array
    {
        $qb = $this->createQueryBuilder('user', 'user.id')
            ->leftJoin('user.userGroupUsers', 'groupUser')
            ->addSelect('groupUser')
            ->leftJoin('user.assignedToStages', 'stages')
            ->addSelect('stages');

        if ($searchTerm !== '') {
            $qb->andWhere('LOWER(user.name) LIKE :searchString')
                ->setParameter('searchString', "%".$searchTerm."%");
        }

        $qb->orderBy('user.name', 'ASC');

        return ($returnPaginated) ? (new Paginator($qb, $pageSize))->paginate($page) : $qb->getQuery()->getResult();
    }

    public function findAllByGroup(Group $group, string $searchTerm = '', int $page = 1, int $pageSize = 10, bool $returnPaginated = false): Paginator
    {
        // Get the user ids
        $groupUsersIdsQuery = $this->getEntityManager()->createQueryBuilder()
            ->select('user_sub.id')
            ->from(User::class, 'user_sub')
            ->leftJoin('user_sub.userGroupUsers', 'groupUser_sub')
            ->where('groupUser_sub.group = :group')
            ->orderBy('user_sub.id', 'ASC');

        $expr = $this->getEntityManager()->getExpressionBuilder();

        // join with everything
        $qb = $this->createQueryBuilder('user', 'user.id')
            ->leftJoin('user.userGroupUsers', 'groupUser')
            ->addSelect('groupUser')
            ->leftJoin('user.assignedToStages', 'stages')
            ->addSelect('stages')
            ->andWhere($expr->in('user.id', $groupUsersIdsQuery->getDQL()))->setParameter('group', $group);

        if ($searchTerm !== '') {
            $qb->andWhere('LOWER(user.name) LIKE :searchString')
                ->setParameter('searchString', "%".$searchTerm."%");
        }

        $qb->orderBy('user.id', 'ASC');

        return ($returnPaginated) ? (new Paginator($qb, $pageSize))->paginate($page) : $qb->getQuery()->getResult();
    }

    public function findAllWithProjectAccess(Project $project): array
    {
        return $this->createQueryBuilder('user', 'user.id')
            ->join('user.userGroupUsers', 'groupUser')
            ->join('groupUser.group', 'group')
            ->join('group.groupGroupProjects', 'groupProject', Join::WITH, 'groupProject.project = :project')->setParameter('project', $project)
            ->groupBy('user.id')
            ->getQuery()
            ->getResult();
    }

    public function findAllUserCount(): int
    {
        return $this->createQueryBuilder('user')
            ->select('count(user.id)')
            ->andWhere("JSON_CONTAINS(user.roles, :userRole, '$') = 1")
            ->setParameter('userRole', '"'.Role::USER->string().'"')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findAllCountRegisteredBetween(\DateTime $firstDate, \DateTime $secondDate): int
    {
        return $this->createQueryBuilder('user')
            ->select('count(user.id)')
            ->andWhere('user.createdAt >= :firstDate')
            ->andWhere('user.createdAt <= :secondDate')
            ->setParameter('firstDate', $firstDate)
            ->setParameter('secondDate', $secondDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findAllCountLoggedInBetween(\DateTime $firstDate, \DateTime $secondDate): int
    {
        return $this->createQueryBuilder('user')
            ->select('count(user.id)')
            ->andWhere('user.lastLogin >= :firstDate')
            ->andWhere('user.lastLogin <= :secondDate')
            ->setParameter('firstDate', $firstDate)
            ->setParameter('secondDate', $secondDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findAllCreatedBetweenMonthPeriod(\DateTime $firstDate, \DateTime $secondDate): array
    {
        $sql = '
        SELECT YEAR(CAST(user.created_at AS DATE)) AS `year`, MONTH(CAST(user.created_at AS DATE)) AS `month`, COUNT(user.id) AS `count` FROM user
        WHERE user.created_at >= :firstDate
        AND user.created_at <= :secondDate
        GROUP BY YEAR(CAST(user.created_at AS DATE)),
        MONTH(CAST(user.created_at AS DATE))';

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('year', 'year');
        $rsm->addScalarResult('month', 'month');
        $rsm->addScalarResult('count', 'count');

        return $this->getEntityManager()->createNativeQuery($sql, $rsm)->setParameters(
            [
                'firstDate' => $firstDate->format('Y-m-d'),
                'secondDate' => $secondDate->format('Y-m-d'),
            ]
        )->getResult();
    }

    public function findAllLoggedBetweenMonthPeriod(\DateTime $firstDate, \DateTime $secondDate): array
    {
        $sql = '
        SELECT YEAR(CAST(user.last_login AS DATE)) AS `year`, MONTH(CAST(user.last_login AS DATE)) AS `month`, COUNT(user.id) AS `count` FROM user
        WHERE user.last_login >= :firstDate
        AND user.last_login <= :secondDate
        GROUP BY YEAR(CAST(user.last_login AS DATE)),
        MONTH(CAST(user.last_login AS DATE))';

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('year', 'year');
        $rsm->addScalarResult('month', 'month');
        $rsm->addScalarResult('count', 'count');

        return $this->getEntityManager()->createNativeQuery($sql, $rsm)->setParameters(
            [
                'firstDate' => $firstDate->format('Y-m-d'),
                'secondDate' => $secondDate->format('Y-m-d'),
            ]
        )->getResult();
    }

    public function findNonCodificManagersWithEmailAndLastLogin(): array
    {
        return $this->createQueryBuilder('user')
            ->where("JSON_CONTAINS(user.roles, :role, '$.\"1\"') = 1 OR JSON_CONTAINS(user.roles, :role) = 1")
            ->andWhere("user.lastLogin IS NOT NULL")
            ->andWhere("user.email != ''")
            ->andWhere("user.email IS NOT NULL")
            ->andWhere("user.email NOT LIKE '%codific.com'")
            ->setParameter('role', '"'.Role::MANAGER->string().'"')
            ->getQuery()
            ->getResult();
    }

// #BlockEnd number=33

}
