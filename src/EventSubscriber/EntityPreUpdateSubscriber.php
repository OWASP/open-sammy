<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\SanitizerService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
class EntityPreUpdateSubscriber
{
    public function __construct(private readonly SanitizerService $sanitizeService)
    {
    }
    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        foreach ($entity->getLessPurifiedFields() as $field) {
            $value = $entity->{'get'.ucfirst($field)}(); /* @phpstan-ignore-line */
            $sanitizedValue = $this->sanitizeService->sanitizeValue($value, SanitizerService::LIBERAL);
            $entity->{'set'.ucfirst($field)}($sanitizedValue); /* @phpstan-ignore-line */
        }
    }
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        foreach ($args->getEntityChangeSet() as $fieldName => $values) {
            if (in_array($fieldName, $entity->getLessPurifiedFields(), true)) {
                $sanitizedValue = $this->sanitizeService->sanitizeValue($args->getNewValue($fieldName), SanitizerService::LIBERAL);
                $args->setNewValue($fieldName, $sanitizedValue);
            }
        }
    }
}
