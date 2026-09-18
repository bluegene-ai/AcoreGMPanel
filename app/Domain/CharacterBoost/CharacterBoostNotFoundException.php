<?php
/**
 * File: app/Domain/CharacterBoost/CharacterBoostNotFoundException.php
 * Purpose: 直升目标角色不存在。
 */

declare(strict_types=1);

namespace Acme\Panel\Domain\CharacterBoost;

use RuntimeException;

class CharacterBoostNotFoundException extends RuntimeException
{
}
