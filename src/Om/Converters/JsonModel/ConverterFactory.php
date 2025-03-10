<?php

declare(strict_types=1);

namespace Talleu\RedisOm\Om\Converters\JsonModel;

use Talleu\RedisOm\Om\Converters\AbstractConverterFactory;
use Talleu\RedisOm\Om\Converters\ConverterInterface;
use Talleu\RedisOm\Om\Converters\EnumConverter;
use Talleu\RedisOm\Om\Converters\ScalarConverter;

class ConverterFactory extends AbstractConverterFactory
{
    /**
     * @var ConverterInterface[]
     */
    protected static function getConvertersCollection(): array
    {
        return [
            new JsonObjectConverter(),
            new ArrayConverter(),
            new ScalarConverter(),
            new NullConverter(),
            new DateTimeConverter(),
            new DateTimeImmutableConverter(),
            new EnumConverter(),
        ];
    }
}
