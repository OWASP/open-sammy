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

// #BlockStart number=129 id=_19_0_3_40d01a2_1635865714358_177864_6732_#_0

use App\Entity\Assessment;
use App\Entity\AssessmentStream;
use App\Entity\Project;
use App\Entity\Stage;
use App\Interface\EntityInterface;
use App\Repository\Abstraction\AbstractRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Assessment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Assessment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Assessment[]    findAll()
 * @method Assessment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AssessmentRepository extends AbstractRepository
{
    /**
     * AssessmentRepository constructor.
     *
     * @return void
     */
    public function __construct(ManagerRegistry $registry, string $entityClassName = Assessment::class)
    {
        parent::__construct($registry, $entityClassName);
    }

    /**
     * Duplicate the object and save the duplicate.
     *
     * @param Assessment $assessment The object to be duplicated
     */
    public function duplicate(Assessment $assessment): Assessment
    {
        $clone = $assessment->getCopy();
        $this->getEntityManager()->persist($clone);
        $this->getEntityManager()->flush();

        return $clone;
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findByProjectOptimized(Project $project): ?Assessment
    {
        $qb = $this->createQueryBuilder('assessment')
            ->join('assessment.assessmentAssessmentStreams', 'assessmentStream')
            ->addSelect('assessmentStream')
            ->join('assessmentStream.stream', 'stream')
            ->leftJoin('assessmentStream.assessmentStreamStages', 'stage')
            ->addSelect('stage')
            ->leftJoin('stage.stageAssessmentAnswers', 'assessmentAnswer')
            ->addSelect('assessmentAnswer')
            ->join(Project::class, 'project', Join::WITH, 'project.assessment = assessment')
            ->where('project = :project')
            ->setParameter('project', $project);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findAllAssessmentCount(): int
    {
        return $this->createQueryBuilder('assessment')
            ->select('count(assessment.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findAllCountRegisteredBetween(\DateTime $firstDate, \DateTime $secondDate): int
    {
        return $this->createQueryBuilder('assessment')
            ->select('count(assessment.id)')
            ->andWhere('assessment.createdAt >= :firstDate')
            ->andWhere('assessment.createdAt <= :secondDate')
            ->setParameter('firstDate', $firstDate)
            ->setParameter('secondDate', $secondDate)
            ->getQuery()
            ->getSingleScalarResult();
    }


    public function findAllWithExternalId(): array
    {
        return $this->createQueryBuilder('assessment', 'assessment.id')
            ->join('assessment.project', 'project')
            ->where('project.externalId IS NOT NULL')
            ->getQuery()->getResult();
    }

    public function deepRestore(EntityInterface $assessment): void
    {
        parent::restore($assessment);

        $isDeletedFilterEnabled = false;
        if ($this->getEntityManager()->getFilters()->isEnabled('deleted_entity')) {
            $this->getEntityManager()->getFilters()->disable('deleted_entity');
            $isDeletedFilterEnabled = true;
        }

        $assessmentStreamRepository = $this->getEntityManager()->getRepository(AssessmentStream::class);
        $stageRepository = $this->getEntityManager()->getRepository(Stage::class);
        $assessmentStreams = $assessmentStreamRepository->findBy(['assessment' => $assessment]);

        foreach ($assessmentStreams as $assessmentStream) {
            $assessmentStreamRepository->restore($assessmentStream);

            $stages = $stageRepository->findBy(['assessmentStream' => $assessmentStream]);
            foreach($stages as $stage) {
                $stageRepository->deepRestore($stage); /* @phpstan-ignore-line */
            }
        }
        if ($isDeletedFilterEnabled) {
            $this->getEntityManager()->getFilters()->enable('deleted_entity');
        }
    }

    public function trash(EntityInterface $assessment): void
    {
        parent::trash($assessment);
        $assessmentStreamRepository = $this->getEntityManager()->getRepository(AssessmentStream::class);
        $assessmentStreams = $assessmentStreamRepository->findBy(['assessment' => $assessment]);
        foreach ($assessmentStreams as $assessmentStream) {
            $assessmentStreamRepository->trash($assessmentStream);
        }
    }
// #BlockEnd number=129

}
