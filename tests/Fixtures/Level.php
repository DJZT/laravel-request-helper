<?php

declare(strict_types=1);

namespace DJZT\RequestHelper\Tests\Fixtures;

/**
 * A string-backed enum with numeric values, to cover int -> string normalisation.
 */
enum Level: string
{
    case One = '1';
    case Two = '2';
}
