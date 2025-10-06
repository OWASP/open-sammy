<?php

declare(strict_types=1);

namespace App\Repository\Abstraction;

use App\Service\MetamodelService;

abstract class AbstractExpertModeRepository extends AbstractRepository
{
    abstract protected function getFromMetamodelService();

    public function findOneBy(array $criteria, ?array $orderBy = null, bool $expertMode = false): ?object
    {
        $rawResult = parent::findOneBy($criteria, $orderBy);

        if ($rawResult === null || $expertMode) {
            return $rawResult;
        }

        $filteredResult = MetamodelService::entityArrayIntersectById($this->getFromMetamodelService(), [$rawResult]);
        $firstResult = reset($filteredResult);

        return ($firstResult !== false) ? $firstResult : null;
    }

    public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null, bool $expertMode = false): array
    {
        $rawResult = parent::findBy($criteria, $orderBy);

        if ($expertMode) {
            return $rawResult;
        }

        return MetamodelService::entityArrayIntersectById($this->getFromMetamodelService(), $rawResult);
    }

    public function findAll(bool $expertMode = false): array
    {
        return self::findBy([], null, null, null, $expertMode);
    }
}
