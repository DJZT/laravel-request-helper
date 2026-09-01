<?php

declare(strict_types=1);

namespace DJZT\RequestHelper\Support;

/**
 * Internal sentinel used to tell "key absent" from "key present with a null value".
 *
 * A dedicated object is used instead of a magic string so that no user payload can
 * ever collide with it.
 *
 * @internal
 */
final class Missing
{
    private static ?self $instance = null;

    private function __construct() {}

    public static function instance(): self
    {
        return self::$instance ??= new self;
    }
}
