<?php
/**
 * File: app/Domain/CharacterBoost/CharacterBoostSoapException.php
 * Purpose: 直升过程中 SOAP 命令执行失败。
 */

declare(strict_types=1);

namespace Acme\Panel\Domain\CharacterBoost;

use RuntimeException;

class CharacterBoostSoapException extends RuntimeException
{
}
