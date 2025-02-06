<?php

/**
 * This is automatically generated file using the Codific Prototizer
 * PHP version 8
 * @category PHP
 * @package  Admin
 * @author   CODIFIC <info@codific.com>
 * @link     http://codific.com
 */

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Abstraction\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Serializer\Annotation\Ignore;

// #BlockStart number=281 id=_#_0

// #BlockEnd number=281


#[ORM\Table(name: "`group_project`")]
#[ORM\Entity(repositoryClass: "App\Repository\GroupProjectRepository")]
#[ORM\HasLifecycleCallbacks]
class GroupProject extends AbstractEntity
{
    #[ORM\ManyToOne(targetEntity: Group::class, cascade: ["persist"], inversedBy: "groupGroupProjects", fetch: "LAZY")]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private Group $group;

    #[ORM\ManyToOne(targetEntity: Project::class, cascade: ["persist"], fetch: "LAZY")]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private Project $project;

    /**
     * Set group
     */
    public function setGroup(Group $group): GroupProject
    {
        $this->group = $group;
        return $this;
    }

    /**
     * Get group
     * @return Group|null
     */
    public function getGroup(): ?Group
    {
        return $this->group;
    }

    /**
     * Set project
     */
    public function setProject(Project $project): GroupProject
    {
        $this->project = $project;
        return $this;
    }

    /**
     * Get project
     * @return Project|null
     */
    public function getProject(): ?Project
    {
        return $this->project;
    }

    #[Ignore]
    public static array $manyToManyProperties = [];
    
    #[Ignore]
    public static array $childProperties = [
    ];

     /**
     * This method is a copy constructor that will return a copy object (except for the id field)
     * Note that this method will not save the object
     */
    #[Ignore]
    public function getCopy(?GroupProject $clone = null): GroupProject
    {
        if ($clone === null) {
            $clone = new GroupProject();
        }
// #BlockStart number=282 id=_#_1

// #BlockEnd number=282

        return $clone;
    }


    /**
     * Private to string method auto generated based on the UML properties
     * This is the new way of doing things.
     */
    public function toString(): string
    {
        return "";
    }

// #BlockStart number=283 id=_#_2

    /**
     * The toString method based on the private __toString autogenerated method
     * If necessary override.
     */
    public function __toString(): string
    {
        return $this->toString();
    }
// #BlockEnd number=283

}
