<?php

declare(strict_types=1);

namespace Talleu\RedisOm\Om\Mapping;

use Attribute;
use Talleu\RedisOm\Client\RedisClient;
use Talleu\RedisOm\Client\RedisClientInterface;
use Talleu\RedisOm\Om\Converters\ConverterInterface;
use Talleu\RedisOm\Om\Converters\HashModel\HashObjectConverter;
use Talleu\RedisOm\Om\Converters\JsonModel\JsonObjectConverter;
use Talleu\RedisOm\Om\Persister\HashModel\HashPersister;
use Talleu\RedisOm\Om\Persister\JsonModel\JsonPersister;
use Talleu\RedisOm\Om\Persister\PersisterInterface;
use Talleu\RedisOm\Om\RedisFormat;
use Talleu\RedisOm\Om\Repository\HashModel\HashRepository;
use Talleu\RedisOm\Om\Repository\JsonModel\JsonRepository;
use Talleu\RedisOm\Om\Repository\RepositoryInterface;

#[Attribute(Attribute::TARGET_CLASS)]
final class Entity
{
    public function __construct(
        public ?array                $options = [],
        public ?string               $prefix = null,
        public ?int                  $expires = null,
        public ?string               $format = null,
        public ?PersisterInterface   $persister = null,
        public ?ConverterInterface   $converter = null,
        public ?RepositoryInterface  $repository = null,
        public ?RedisClientInterface $redisClient = null,
    ) {
        $this->persister = $persister ?? ($format === RedisFormat::JSON ? new JsonPersister($options) : new HashPersister($options));
        $this->converter = $converter ?? ($format === RedisFormat::JSON ? new JsonObjectConverter() : new HashObjectConverter());
        $this->repository = $repository ?? ($format === RedisFormat::JSON ? new JsonRepository() : new HashRepository());
        $this->redisClient = $redisClient ?? RedisClient::createClient($options);
    }
}
