<?php

declare(strict_types=1);

namespace App\Interface;

use Doctrine\Persistence\Event\LifecycleEventArgs;

interface EntityInterface
{
    public function getId(): ?int;

    public function setId(?int $id);

    public function getCreatedAt(): ?\DateTime;

    public function setCreatedAt(LifecycleEventArgs|\DateTime $dateTime);

    public function getUpdatedAt(): ?\DateTime;

    public function setUpdatedAt(LifecycleEventArgs|\DateTime $dateTime);

    public function getDeletedAt(): ?\DateTime;

    public function setDeletedAt(?\DateTime $deletedAt);

    public function __toString(): string;

    public function getEntityName(bool $camelCase = false): string;

    public function getAliasName(?string $propertyName = null): string;

    /**
     * @return array<string>
     */
    public function getParentClasses(): array;

    public function getUnderscoreEntityName(?string $propertyName = null): string;

    /**
     * @return array<string>
     */
    public function getUploadFields(): array;

    /**
     * @return array<string>
     */
    public function getModifiableFields(): array;

    public function getFilterFields(): array;
}
