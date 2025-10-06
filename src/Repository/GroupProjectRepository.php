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


// #BlockStart number=284 id=_#_0

use App\Entity\Group;
use App\Entity\GroupProject;
use App\Entity\Project;
use App\Interface\EntityInterface;
use App\Repository\Abstraction\AbstractRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method GroupProject|null find($id, $lockMode = null, $lockVersion = null)
 * @method GroupProject|null findOneBy(array $criteria, array $orderBy = null)
 * @method GroupProject[]    findAll()
 * @method GroupProject[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupProjectRepository extends AbstractRepository
{
    /**
     * GroupProjectRepository constructor.
     *
     * @return void
     */
    public function __construct(ManagerRegistry $registry, string $entityClassName = GroupProject::class)
    {
        parent::__construct($registry, $entityClassName);
    }

    /**
     * Duplicate the object and save the duplicate.
     *
     * @param GroupProject $groupProject The object to be duplicated
     *
     * @throws ORMException
     */
    public function duplicate(GroupProject $groupProject): GroupProject
    {
        $clone = $groupProject->getCopy();
        $this->getEntityManager()->persist($clone);
        $this->getEntityManager()->flush();

        return $clone;
    }

    /**
     * Delete the object from the database.
     *
     * @param EntityInterface $model The object to be deleted
     * @param bool $forceDelete A flag that indicates whether this object should be definitively deleted (no trash)
     *
     * @return void
     *
     * @throws ORMException
     */
    public function delete(EntityInterface $model, bool $forceDelete = false)
    {
        parent::delete($model, true);
    }

    /**
     * @return GroupProject[]
     */
    public function findAllOptimized(): array
    {
        return $this->createQueryBuilder('groupProject')
            ->join('groupProject.group', '_group')
            ->join('groupProject.project', 'project')
            ->getQuery()
            ->getResult();
    }

    public function findAllByGroup(Group $group): mixed
    {
        return $this->createQueryBuilder('groupProject')
            ->join('groupProject.group', '_group')
            ->join('groupProject.project', 'project')
            ->where('_group = :_group')
            ->setParameter('_group', $group)
            ->getQuery()
            ->getResult();
    }

    public function findAllByProject(Project $project): mixed
    {
        return $this->createQueryBuilder('groupProject')
            ->join('groupProject.group', 'group')
            ->join('groupProject.project', 'project')
            ->where('project = :project')->setParameter('project', $project)
            ->getQuery()
            ->getResult();
    }
}

// #BlockEnd number=284
