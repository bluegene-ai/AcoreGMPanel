<?php
/**
 * File: app/Domain/CharacterBoost/CharacterBoostGuardException.php
 * Purpose: 直升前置校验未通过（模板无效、目标等级非法、账号等级不达标等）。
 */

declare(strict_types=1);

namespace Acme\Panel\Domain\CharacterBoost;

use RuntimeException;

class CharacterBoostGuardException extends RuntimeException
{
}
