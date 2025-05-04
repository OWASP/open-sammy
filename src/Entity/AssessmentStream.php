<?php

/**
 * This is automatically generated file using the Codific Prototizer
 * PHP version 8
 * @category PHP
 * @author   CODIFIC <info@codific.com>
 * @see     http://codific.com
 */

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Abstraction\AbstractEntity;
use App\Utils\Constants;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Serializer\Annotation\Ignore;

use App\Enum\AssessmentStatus;


// #BlockStart number=217 id=_19_0_3_40d01a2_1638283953829_657991_4947_#_0
use App\Enum\ImprovementStatus;

// #BlockEnd number=217


#[ORM\Table(name: "`assessment_stream`")]
#[ORM\Entity(repositoryClass: "App\Repository\AssessmentStreamRepository")]
#[ORM\HasLifecycleCallbacks]
class AssessmentStream extends AbstractEntity
// #BlockStart number=123123 id=_19_0_3_40d01a2_1638283953829_657991_4947_#_1
    // additional implements go here
// #BlockEnd number=123123
{

    #[ORM\ManyToOne(targetEntity: Stream::class, cascade: ["persist"], fetch: "EAGER")]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    #[MaxDepth(1)]
    protected ?Stream $stream = null;

    #[ORM\ManyToOne(targetEntity: Assessment::class, cascade: ["persist"], fetch: "EAGER", inversedBy: "assessmentAssessmentStreams")]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    #[MaxDepth(1)]
    protected ?Assessment $assessment = null;

    #[ORM\Column(name: "`status`", enumType: AssessmentStatus::class)]
    protected AssessmentStatus $status = AssessmentStatus::NEW;

    #[ORM\Column(name: "`expiration_date`", type: Types::DATE_MUTABLE, nullable: true)]
    protected ?\DateTime $expirationDate = null;

    #[ORM\Column(name: "`score`", type: Types::DECIMAL, precision: 10, scale: 2)]
    protected float $score = 0.0;

    #[ORM\Column(name: "`external_id`", type: Types::STRING, nullable: true)]
    protected ?string $externalId = "";

    #[ORM\OneToMany(mappedBy: "assessmentStream", targetEntity: Stage::class, cascade: ["persist"], fetch: "LAZY", orphanRemoval: false)]
    #[ORM\OrderBy(["id" => "ASC"])]
    #[MaxDepth(1)]
    protected Collection $assessmentStreamStages;


    /**
     * Return the status in a human-readable string version
     */
    public function getStatusString(): string
    {
        return $this->status->label();
    }

    /**
     * Set the status value by string. Try to find the string value if none found set to 0.
     */
    #[Ignore]
    public function setStatusByString(string $stringType): void
    {
        $this->setStatus(AssessmentStatus::fromLabel($stringType));
    }

    /**
     * Return a list of all constants as strings
     */
    public static function getAllStatus(): array
    {
        return array_column(AssessmentStatus::cases(), "name", "value");
    }

    public function __construct()
    {
        $this->assessmentStreamStages = new ArrayCollection();
    }

    public function setStream(?Stream $stream): self
    {
        $this->stream = $stream;

        return $this;
    }

    public function getStream(): ?Stream
    {
        return $this->stream;
    }

    public function setAssessment(?Assessment $assessment): self
    {
        $this->assessment = $assessment;

        return $this;
    }

    public function getAssessment(): ?Assessment
    {
        return $this->assessment;
    }

    public function setStatus(AssessmentStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getStatus(): AssessmentStatus
    {
        return $this->status;
    }

    public function setExpirationDate(?\DateTime $expirationDate): self
    {
        $this->expirationDate = $expirationDate;

        return $this;
    }

    public function getExpirationDate(): ?\DateTime
    {
        return $this->expirationDate;
    }

    public function setScore(float $score): self
    {
        $this->score = $score;

        return $this;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    /**
     * Get AssessmentStream Stages
     * @return Collection<Stage>
     */
    public function getAssessmentStreamStages(): Collection
    {
        return $this->assessmentStreamStages;
    }

    /**
     * Add Stages Stage
     */
    public function addAssessmentStreamStage(Stage $stage): AssessmentStream
    {
        $this->assessmentStreamStages->add($stage);

        return $this;
    }


    /**
     * This method is a copy constructor that will return a copy object (except for the id field)
     * Note that this method will not save the object
     */
    #[Ignore]
    public function getCopy(?AssessmentStream $clone = null): AssessmentStream
    {
        if ($clone === null) {
            $clone = new AssessmentStream();
        }
        $clone->setStream($this->stream);
        $clone->setAssessment($this->assessment);
        $clone->setStatus($this->status);
        $clone->setExpirationDate($this->expirationDate);
        $clone->setScore($this->score);
        $clone->setExternalId($this->externalId);
// #BlockStart number=218 id=_19_0_3_40d01a2_1638283953829_657991_4947_#_2
        // This shouldn't happen anymore, but it will fix exisitng problems

// #BlockEnd number=218

        return $clone;
    }

    /**
     * Private to string method auto generated based on the UML properties
     * This is the new way of doing things.
     */
    public function toString(): string
    {
        return "{$this->id}";
    }

    /**
     * https://symfony.com/doc/current/validation.html
     * we use php version for validation!!!
     */
    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {

// #BlockStart number=219 id=_19_0_3_40d01a2_1638283953829_657991_4947_#_3
//        to remove constraint use following code
//        unset($metadata->properties['PROPERTY']);
//        unset($metadata->members['PROPERTY']);
// #BlockEnd number=219
    }

    #[Ignore]
    public function getGeneratedFilterFields(): array
    {
        return [
            "_assessment_stream.id",
        ];
    }

    #[Ignore]
    public function getUploadFields(): array
    {
        return [

        ];
    }

    #[Ignore]
    public function getModifiableFields(): array
    {
        return [
            "stream",
            "assessment",
            "status",
            "expirationDate",
            "score",
        ];
    }

    #[Ignore]
    public function getReadOnlyFields(): array
    {
        return [
            "externalId",
        ];
    }

    #[Ignore]
    public function getParentClasses(): array
    {
        return [
            "assessment",
        ];
    }

    #[Ignore]
    public static array $manyToManyProperties = [
    ];


    #[Ignore]
    public static array $childProperties = [
        "assessmentStreamStages" => "assessmentStream",
    ];

// #BlockStart number=220 id=_19_0_3_40d01a2_1638283953829_657991_4947_#_4

    public const MODE_EVALUATION = 'Evaluation';
    public const MODE_VALIDATION = 'Validation';
    public const MODE_REJECTED = 'Evaluation (Validation Rejected)';
    public const MODE_IMPROVEMENT = 'Improvement';
    public const MODE_IMPROVEMENT_IN_PROGRESS = 'Improvement in progress';

    public const MIN_IMPROVEMENT_IMPORTANCE_RATING = 1;
    public const MAX_IMPROVEMENT_IMPORTANCE_RATING = 5;
    public const DEFAULT_IMPROVEMENT_IMPORTANCE_RATING = 3;

    /**
     * Return the status in a human readable string version.
     */
    public function getMode(): string
    {
        return $this->getModeByStatus($this->status);
    }

    public function getModeByStatus(AssessmentStatus $status): string
    {
        return match ($status) {
            \App\Enum\AssessmentStatus::NEW, \App\Enum\AssessmentStatus::IN_EVALUATION => AssessmentStream::MODE_EVALUATION,
            \App\Enum\AssessmentStatus::IN_VALIDATION => AssessmentStream::MODE_VALIDATION,
            \App\Enum\AssessmentStatus::VALIDATED => AssessmentStream::MODE_IMPROVEMENT,
            \App\Enum\AssessmentStatus::IN_IMPROVEMENT => AssessmentStream::MODE_IMPROVEMENT_IN_PROGRESS,
            default => 'NOT SPECIFIED',
        };
    }

    public function getCurrentStage(): ?Stage
    {
        $stage = $this->assessmentStreamStages->last();

        return $stage instanceof Stage ? $stage : null;
    }

    public function getLastStageByClass(string $class): ?Stage
    {
        $stage = $this->assessmentStreamStages->filter(function (Stage $stage) use ($class) {
            return $stage::class === $class;
        })->last();

        return $stage instanceof Stage ? $stage : null;
    }

    public function getLastEvaluationStage(): ?Evaluation
    {
        /** @var Evaluation|null $evaluation */
        $evaluation = $this->getLastStageByClass(Evaluation::class);

        return $evaluation;
    }

    public function getLastValidationStage(): ?Validation
    {
        /** @var Validation|null $validation */
        $validation = $this->getLastStageByClass(Validation::class);

        return $validation;
    }

    public function getLastImprovementStage(): ?Improvement
    {
        /** @var Improvement|null $improvement */
        $improvement = $this->getLastStageByClass(Improvement::class);

        return $improvement;
    }

    public function getSubmittedBy(): ?User
    {
        return (($evaluation = $this->getLastEvaluationStage()) !== null) ? $evaluation->getSubmittedBy() : null;
    }

    public function getValidatedBy(): ?User
    {
        return (($evaluation = $this->getLastValidationStage()) !== null) ? $evaluation->getSubmittedBy() : null;
    }

    public function getSubmittedAt(): ?\DateTime
    {
        return (($evaluation = $this->getLastEvaluationStage()) !== null) ? $evaluation->getCompletedAt() : null;
    }

    public function getValidatedAt(): ?\DateTime
    {
        return (($validation = $this->getLastValidationStage()) !== null) ? $validation->getCompletedAt() : null;
    }

    public function getImprovementBy(): ?User
    {
        return (($improvement = $this->getLastImprovementStage()) !== null) ? $improvement->getSubmittedBy() : null;
    }

    public function setStatusByStage(?Stage $stage): void
    {
        if ($stage === null) {
            $this->setStatus(AssessmentStatus::NEW);
        } elseif ($stage instanceof Evaluation) {
            $this->setStatus(AssessmentStatus::IN_EVALUATION);
        } elseif ($stage instanceof Validation) {
            $this->setStatus(AssessmentStatus::IN_VALIDATION);
        } elseif ($stage instanceof Improvement) {
            if ($stage->getStatus() === ImprovementStatus::NEW) {
                $this->setStatus(AssessmentStatus::VALIDATED);
            } elseif ($stage->getStatus() === ImprovementStatus::IMPROVE) {
                $this->setStatus(AssessmentStatus::IN_IMPROVEMENT);
            }
        }
    }

    /**
     * The toString method based on the private __toString autogenerated method
     * If necessary override.
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    public function getLessPurifiedFields(): array
    {
        return [];
    }

    /**
     * This method is used in a twig - do not drop it
     */
    public function getUnvalidatedScore(): float
    {
        $score = 0;
        foreach ($this->getLastEvaluationStage()?->getStageAssessmentAnswers() ?? [] as $assessmentAnswer) {
            /** @var AssessmentAnswer $assessmentAnswer */
            if ($assessmentAnswer->getType() === \App\Enum\AssessmentAnswerType::CURRENT) {
                $activity = $assessmentAnswer->getQuestion()->getActivity();
                if ($this->getStream()->getPractice()->getBusinessFunction()->getMetamodel()->getId() === Constants::SAMM_ID) {
                    $score += $assessmentAnswer->getAnswer()->getValue() / sizeof($activity->getActivityQuestions());
                } else {
                    $allActivities = sizeof($this->getStream()->getStreamActivities());
                    $score += $assessmentAnswer->getAnswer()->getValue() / $allActivities;
                }
            }
        }

        return $score;
    }

    public function getStage($stageName): ?Stage
    {
        return match ($stageName) {
            Stage::EVALUATION => $this->getLastEvaluationStage(),
            Stage::VALIDATION => $this->getLastValidationStage(),
            Stage::IMPROVEMENT => $this->getLastImprovementStage(),
            default => null,
        };
    }

    public function getActiveStageName(): ?string
    {
        $stageName = null;
        if (in_array($this->getStatus(), [\App\Enum\AssessmentStatus::NEW, \App\Enum\AssessmentStatus::IN_EVALUATION], true)) {
            $stageName = Stage::EVALUATION;
        } elseif ($this->getStatus() === \App\Enum\AssessmentStatus::IN_VALIDATION) {
            $stageName = Stage::VALIDATION;
        } elseif ($this->getStatus() === \App\Enum\AssessmentStatus::VALIDATED) {
            $stageName = Stage::IMPROVEMENT;
        }

        return $stageName;
    }

// #BlockEnd number=220

}
