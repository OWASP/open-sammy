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

// #BlockStart number=19 id=_19_0_3_40d01a2_1635863991426_510700_5593_#_0

use App\Entity\Assessment;
use App\Entity\GroupProject;
use App\Entity\GroupUser;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\Role;
use App\Interface\EntityInterface;
use App\Pagination\Paginator;
use App\Repository\Abstraction\AbstractRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Project|null find($id, $lockMode = null, $lockVersion = null)
 * @method Project|null findOneBy(array $criteria, array $orderBy = null)
 * @method Project[]    findAll()
 * @method Project[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProjectRepository extends AbstractRepository
{
    /**
     * ProjectRepository constructor.
     *
     * @return void
     */
    public function __construct(ManagerRegistry $registry, string $entityClassName = Project::class)
    {
        parent::__construct($registry, $entityClassName);
    }

    /**
     * Duplicate the object and save the duplicate.
     *
     * @param Project $project The object to be duplicated
     */
    public function duplicate(Project $project): Project
    {
        $clone = $project->getCopy();
        $this->getEntityManager()->persist($clone);
        $this->getEntityManager()->flush();

        return $clone;
    }

    /**
     * We use this method because otherwise AssessmentStreams won't be loaded.
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneByProjectAndUserOptimized(Project $project, User $user): ?Project
    {
        $qb = $this->createQueryBuilder('project')
            ->leftJoin(GroupProject::class, 'groupProject', Join::WITH, 'groupProject.project = project')
            ->leftJoin(GroupUser::class, 'groupUser', Join::WITH, 'groupProject.group = groupUser.group')
            ->leftJoin('project.assessment', '_assessment')
            ->addSelect('_assessment')
            ->leftJoin('_assessment.assessmentAssessmentStreams', '_assessmentStream')
            ->addSelect('_assessmentStream')
            ->andWhere('project = :project')->setParameter('project', $project)
            ->orderBy('project.id', 'DESC');

        if (in_array(Role::MANAGER->string(), $user->getRoles(), true) === false) {
            $qb->andWhere('groupUser.user = :user')->setParameter('user', $user);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @return Paginator|array
     */
    public function findOptimized(
        ?bool $template = false,
        ?string $indexBy = null,
        string $searchTerm = '',
        int $page = 1,
        int $pageSize = 10,
        int $archived = 0,
        bool $returnPaginated = false
    ): Paginator|array {
        $qb = $this->createQueryBuilder('project', $indexBy)
            ->leftJoin('project.assessment', '_assessment')
            ->addSelect('_assessment')
            ->leftJoin('_assessment.assessmentAssessmentStreams', '_assessmentStream')
            ->addSelect('_assessmentStream');

        if ($template !== null) {
            $qb->andWhere('project.template = :template')->setParameter('template', $template);
        }

        if ($searchTerm !== '') {
            $qb->andWhere('LOWER(project.name) LIKE :searchString')
                ->setParameter('searchString', "%".$searchTerm."%");
        }

        if ($archived === 1) {
            $qb->andWhere('project.deletedAt IS NOT NULL');
        }

        $qb->orderBy('project.id', 'ASC');

        return ($returnPaginated) ? (new Paginator($qb, $pageSize))->paginate($page) : $qb->getQuery()->getResult();
    }

    /**
     * @return Project[]
     */
    public function findByGroups(array $groups, ?bool $template = false): array
    {
        $qb = $this->createQueryBuilder('project', 'project.id')
            ->join(GroupProject::class, 'groupProject', Join::WITH, 'groupProject.project = project and groupProject.group IN (:groups)')
            ->setParameter('groups', $groups);

        if ($template !== null) {
            $qb->andWhere('project.template = :template')->setParameter('template', $template);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Project[]
     */
    public function findByUser(User $user, ?bool $template = false): array
    {
        $qb = $this->createQueryBuilder('project', 'project.id')
            ->join(GroupProject::class, 'groupProject', Join::WITH, 'groupProject.project = project')
            ->join(GroupUser::class, 'groupUser', Join::WITH, 'groupProject.group = groupUser.group')
            ->where('groupUser.user = :user')->setParameter('user', $user)
            ->orderBy('project.name', 'ASC');

        if ($template !== null) {
            $qb->andWhere('project.template = :template')->setParameter('template', $template);
        }

        return $qb->getQuery()->getResult();
    }

    public function trash(EntityInterface $project): void
    {
        parent::trash($project);
        /** @var Project $project */
        $assessment = $project->getAssessment();

        $assessmentRepository = $this->getEntityManager()->getRepository(Assessment::class);
        $assessmentRepository->trash($assessment);
    }

    public function deepRestore(EntityInterface $project)
    {
        parent::restore($project);

        $isDeletedFilterEnabled = false;
        if ($this->getEntityManager()->getFilters()->isEnabled('deleted_entity')) {
            $this->getEntityManager()->getFilters()->disable('deleted_entity');
            $isDeletedFilterEnabled = true;
        }

        $assessmentRepository = $this->getEntityManager()->getRepository(Assessment::class);
        $groupProjectRepository = $this->getEntityManager()->getRepository(GroupProject::class);

        /** @var Project $project */
        $assessment = $project->getAssessment();
        $groupProject = $groupProjectRepository->findOneBy(['project' => $project]);

        $assessmentRepository->deepRestore($assessment);
        $groupProjectRepository->restore($groupProject);

        if ($isDeletedFilterEnabled) {
            $this->getEntityManager()->getFilters()->enable('deleted_entity');
        }
    }
// #BlockEnd number=19

}
