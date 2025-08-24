<?php

declare(strict_types=1);

namespace App\Utils;

class Constants
{
    public const DEFAULT_CACHE_EXPIRATION = 3600;

    public const SCORE_KEY_PREFIX_VALIDATED = 'validated-assessment-scores-';
    public const SCORE_KEY_PREFIX_ACTIVE = 'active-assessment-scores-';
    public const ASSESSMENT_PROGRESS_KEY_PREFIX_ACTIVE = 'assessment-progress-';
    public const ASSESSMENT_ANSWERS_CACHE_KEY_PREFIX = 'assessment-answers-';

    public const CSRF_HEADER = 'Anti-Csrf-Token';
    public const CSRF_SAFE_METHODS = ['HEAD', 'OPTIONS'];

    public const SAMM_ID = 1;

    public static function getMaxScore(int $metamodelId): int
    {
        if ($metamodelId === self::SAMM_ID) {
            return 3;
        } else {
            return 1;
        }
    }

    public static function getMaxMaturityLevels(int $metamodelId): int
    {
        if ($metamodelId === self::SAMM_ID) {
            return 3;
        } else {
            return 5;
        }
    }
}
