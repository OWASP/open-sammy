<?php

declare(strict_types=1);

namespace App\Entity\Abstraction;

use App\Interface\EntityInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Id;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[ORM\MappedSuperclass]
abstract class AbstractEntity implements EntityInterface
{
    #[Id, ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    protected ?int $id = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    protected ?\DateTime $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true, options: ['default' => 'CURRENT_TIMESTAMP'])]
    protected ?\DateTime $updatedAt = null;

    #[ORM\Column(name: 'deleted_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    protected ?\DateTime $deletedAt = null;

    /**
     * Get id attribute.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Set id attribute.
     *
     * @param int|null $id the id attribute value
     */
    public function setId(?int $id): AbstractEntity
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get createdAt attribute.
     */
    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    /**
     * Set createdAt attribute.
     */
    #[Orm\PrePersist]
    public function setCreatedAt(LifecycleEventArgs|\DateTime $dateTime): AbstractEntity
    {
        if ($dateTime instanceof \DateTime) {
            $this->createdAt = $dateTime;
        } else {
            $this->createdAt = new \DateTime('NOW');
        }

        return $this;
    }

    /**
     * Get updatedAt attribute.
     */
    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    /**
     * Set updatedAt attribute.
     */
    #[Orm\PrePersist]
    #[Orm\PreUpdate]
    public function setUpdatedAt(LifecycleEventArgs|\DateTime $dateTime): AbstractEntity
    {
        if ($dateTime instanceof \DateTime) {
            $this->updatedAt = $dateTime;
        } else {
            $this->updatedAt = new \DateTime('NOW');
        }

        return $this;
    }

    /**
     * Get deletedAt attribute.
     */
    public function getDeletedAt(): ?\DateTime
    {
        return $this->deletedAt;
    }

    /**
     * Set deletedAt attribute.
     *
     * @param \DateTime|null $deletedAt the deletedAt attribute value
     */
    public function setDeletedAt(?\DateTime $deletedAt): AbstractEntity
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    /**
     * Returns whether the entity is deleted or not.
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Get name.
     */
    public function getEntityName(bool $camelCase = false): string
    {
        if ($camelCase) {
            $temp = explode('\\', get_class($this));

            return lcfirst(end($temp));
        } else {
            $temp = explode('\\', strtolower(get_class($this)));

            return end($temp);
        }
    }

    /**
     * Get underscore name.
     */
    public function getUnderscoreEntityName(?string $propertyName = null): string
    {
        $temp = explode('\\', get_class($this));
        if ($propertyName !== null) {
            $temp = explode('\\', $propertyName);
        }

        return trim(strtolower(preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', end($temp))), '_');
    }

    /**
     * Get query builder alias.
     */
    public function getAliasName(?string $propertyName = null): string
    {
        return '_'.$this->getUnderscoreEntityName($propertyName);
    }

    /**
     * Get the fields for less purifying.
     */
    public function getLessPurifiedFields(): array
    {
        return [];
    }

    public function getUploadFields(): array
    {
        return [];
    }

    public function getModifiableFields(): array
    {
        return [];
    }

    public function getGeneratedFilterFields(): array
    {
        return [];
    }

    public function getFilterFields(): array
    {
        return $this->getGeneratedFilterFields();
    }

    public function getParentClasses(): array
    {
        return [];
    }
}
