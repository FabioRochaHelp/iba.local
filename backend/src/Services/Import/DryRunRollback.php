<?php

declare(strict_types=1);

namespace App\Services\Import;

use RuntimeException;

/** Lançada de propósito para desfazer a transação numa simulação. */
final class DryRunRollback extends RuntimeException
{
}
