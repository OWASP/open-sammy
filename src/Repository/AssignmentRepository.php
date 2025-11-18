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

// #BlockStart number=291 id=_19_0_3_40d01a2_1652174082929_735674_4866_#_0

use App\Entity\Assessment;
use App\Entity\AssessmentStream;
use App\Entity\Assignment;
use App\Entity\Project;
use App\Entity\Stage;
use App\Entity\User;
use App\Repository\Abstraction\AbstractRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Assignment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Assignment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Assignment[]    findAll()
 * @method Assignment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AssignmentRepository extends AbstractRepository
{
    /**
     * AssignmentRepository constructor.
     *
     * @return void
     */
    public function __construct(ManagerRegistry $registry, string $entityClassName = Assignment::class)
    {
        parent::__construct($registry, $entityClassName);
    }

    /**
     * Duplicate the object and save the duplicate.
     *
     * @param Assignment $assignment The object to be duplicated
     */
    public function duplicate(Assignment $assignment): Assignment
    {
        $clone = $assignment->getCopy();
        $this->getEntityManager()->persist($clone);
        $this->getEntityManager()->flush();

        return $clone;
    }

    /**
     * @return void
     */
    public function complete(Assignment $assignment)
    {
        $assignment->setCompletedAt(new \DateTime('NOW'));
        $this->getEntityManager()->flush();
    }

    /**
     * @param Stage[] $stages
     *
     * @return Assignment[]
     */
    public function findAllForMultipleStages(array $stages): array
    {
        return $this->createQueryBuilder('assignment')
            ->where('assignment.stage in (:stages)')->setParameter('stages', $stages)
            ->orderBy('assignment.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param User[] $users
     *
     * @return Assignment[]
     */
    public function findActiveForUsers(array $users): array
    {
        return $this->createQueryBuilder('assignment')
            ->join('assignment.stage', 'stage')
            ->addSelect('stage')
            ->where('assignment.user in (:users)')->setParameter('users', $users)
            ->andWhere('stage.completedAt IS NULL')
            ->orderBy('assignment.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // TODO DRP use assessment instead of assessmentStreams
    /**
     * Finds the active assignment for each assessmentStream.
     *
     * @return Assignment[]
     */
    public function findActiveForAssessmentStreams(array $assessmentStreams, ?string $indexBy = null): array
    {
        $indexBy = $indexBy !== null ? "assignment.{$indexBy}" : null;

        return $this->createQueryBuilder('assignment', $indexBy)
            ->join(Stage::class, 'stage', Join::WITH, 'assignment.stage = stage')
            ->join(AssessmentStream::class, 'assessmentStream', Join::WITH, 'stage.assessmentStream = assessmentStream')
            ->where('assessmentStream in (:assessmentStreams)')->setParameter('assessmentStreams', $assessmentStreams)
            ->andWhere('stage.completedAt IS NULL')
            ->orderBy('assignment.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds the active assignment for each assessmentStream.
     *
     * @return Assignment[]
     */
    public function findActiveForProjectAndUser(Project $project, User $user): array
    {
        return $this->createQueryBuilder('assignment')
            ->join('assignment.stage', 'stage')
            ->addSelect('stage')
            ->join(AssessmentStream::class, 'assessmentStream', Join::WITH, 'stage.assessmentStream = assessmentStream')
            ->join(Assessment::class, 'assessment', Join::WITH, 'assessmentStream.assessment = assessment')
            ->join(Project::class, 'project', Join::WITH, 'project.assessment = assessment')
            ->where('project = :project')->setParameter('project', $project)
            ->andWhere('assignment.user = :user')->setParameter('user', $user)
            ->andWhere('assessmentStream.status != :archived')->setParameter('archived', \App\Enum\AssessmentStatus::ARCHIVED)
            ->andWhere('stage.completedAt IS NULL')
            ->orderBy('assignment.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * This is done this way because findOneBy does not match deleted users.
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findByStage(Stage $stage): ?Assignment
    {
        return $this->createQueryBuilder('assignment')
            ->join('assignment.stage', 'stage')
            ->addSelect('stage')
            ->where('stage = :stage')->setParameter('stage', $stage)
            ->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult();
    }

// #BlockEnd number=291

}
