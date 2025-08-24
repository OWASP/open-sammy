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
use App\Enum\MetamodelType;
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



// #BlockStart number=164 id=_19_0_3_40d01a2_1677917556636_72280_4869_#_0

// #BlockEnd number=164


#[ORM\Table(name: "`metamodel`")]
#[ORM\Entity(repositoryClass: "App\Repository\MetamodelRepository")]
#[ORM\HasLifecycleCallbacks]
class Metamodel extends AbstractEntity
// #BlockStart number=123123 id=_19_0_3_40d01a2_1677917556636_72280_4869_#_1
    // additional implements go here
// #BlockEnd number=123123
{

    #[ORM\Column(name: "`name`", type: Types::STRING, nullable: true)]
    protected ?string $name = "";

    #[ORM\Column(name: "`max_score`", type: Types::INTEGER)]
    protected int $maxScore = 0;

    #[ORM\Column(name: "`default`", type: Types::BOOLEAN)]
    protected bool $default = false;

    #[ORM\Column(name: "`type`", type: Types::INTEGER, enumType: MetamodelType::class)]
    protected MetamodelType $type = MetamodelType::SAMM;



    public function __construct()
    {
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setMaxScore(int $maxScore): self
    {
        $this->maxScore = $maxScore;

        return $this;
    }

    public function getMaxScore(): int
    {
        return $this->maxScore;
    }

    public function setDefault(bool $default): self
    {
        $this->default = $default;

        return $this;
    }

    public function getDefault(): bool
    {
        return $this->default;
    }

    public function setType(MetamodelType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getType(): MetamodelType
    {
        return $this->type;
    }


    /**
     * This method is a copy constructor that will return a copy object (except for the id field)
     * Note that this method will not save the object
     */
    #[Ignore]
    public function getCopy(?Metamodel $clone = null): Metamodel
    {
        if ($clone === null) {
            $clone = new Metamodel();
        }
        $clone->setName($this->name);
        $clone->setMaxScore($this->maxScore);
        $clone->setDefault($this->default);
        $clone->setType($this->type);
// #BlockStart number=165 id=_19_0_3_40d01a2_1677917556636_72280_4869_#_2

// #BlockEnd number=165

        return $clone;
    }

    /**
     * Private to string method auto generated based on the UML properties
     * This is the new way of doing things.
     */
    public function toString(): string
    {
        return "{$this->name}";
    }

    /**
     * https://symfony.com/doc/current/validation.html
     * we use php version for validation!!!
     */
    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('name', new Assert\NotBlank());
        $metadata->addPropertyConstraint('type', new Assert\NotNull());

// #BlockStart number=166 id=_19_0_3_40d01a2_1677917556636_72280_4869_#_3
//        to remove constraint use following code
//        unset($metadata->properties['PROPERTY']);
//        unset($metadata->members['PROPERTY']);
// #BlockEnd number=166
    }

    #[Ignore]
    public function getGeneratedFilterFields(): array
    {
        return [
            "_metamodel.id",
            "_metamodel.name",
            "_metamodel.maxScore",
            "_metamodel.type",
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
            "name",
            "maxScore",
            "default",
            "type",
        ];
    }

    #[Ignore]
    public function getReadOnlyFields(): array
    {
        return [
        ];
    }

    #[Ignore]
    public function getParentClasses(): array
    {
        return [

        ];
    }

    #[Ignore]
    public static array $manyToManyProperties = [
    ];


    #[Ignore]
    public static array $childProperties = [
    ];

// #BlockStart number=167 id=_19_0_3_40d01a2_1677917556636_72280_4869_#_4

    /**
     * The toString method based on the private __toString autogenerated method
     * If necessary override.
     */
    public function __toString(): string
    {
        return $this->toString();
    }

// #BlockEnd number=167

}