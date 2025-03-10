<?php

declare(strict_types=1);

namespace Talleu\RedisOm\Om\Mapping;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Property
{
    public const TEXT_TYPE = 'TEXT';
    public const NUMERIC_TYPE = 'NUMERIC';

    public function __construct(
        public ?string $name = null,
        public ?string $format = null,
    ) {
    }
}
