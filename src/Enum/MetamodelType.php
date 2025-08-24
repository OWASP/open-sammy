<?php

declare(strict_types=1);

namespace App\Enum;

enum MetamodelType: int
{
    case SAMM = 0;
    case DSOMM = 1;

    public function label(): string
    {
        return match ($this) {
            self::SAMM => "SAMM",
            self::DSOMM => "DSOMM",
        };
    }

    public static function fromLabel(string $label): self
    {
        $type = str_replace(" ", "_", $label);
        $value = array_search($type, array_column(self::cases(), "name", "value"), true);
        if ($value === false) {
            return self::SAMM;
        }

        return self::from($value);
    }
}