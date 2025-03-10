<?php

declare(strict_types=1);

namespace Talleu\RedisOm\Client;

use Talleu\RedisOm\Exception\RedisClientResponseException;
use Talleu\RedisOm\Om\Mapping\Property;
use Talleu\RedisOm\Om\RedisFormat;

class RedisClient extends \Redis implements RedisClientInterface
{
    public static function createClient(?array $options = null): RedisClient
    {
        $client = new self($options);
        $client->pconnect(array_key_exists('host', $options) ? $options['host'] : 'redis');

        return $client;
    }

    public function hashMultiSet(string $key, array $data): bool|self
    {
        return $this->hMSet(RedisClient::convertPrefix($key), $data);
    }

    public function hashGetAll(string $key): array
    {
        return $this->hGetAll(RedisClient::convertPrefix($key));
    }

    public function remove(string $key): false|int|self
    {
        return $this->del(RedisClient::convertPrefix($key));
    }

    public function jsonGet(string $key): ?string
    {
        $result = $this->rawCommand(RedisCommands::JSON_GET, static::convertPrefix($key));

        if (!$result) {
            if (($error = $this->getLastError()) === null) {
                return null;
            }

            $this->handleError(RedisCommands::JSON_GET, $error);
        }

        return $result;
    }

    public function jsonSet(string $key, ?string $path = '$', ?string $value = '{}'): ?bool
    {
        $result = $this->rawCommand(RedisCommands::JSON_SET, static::convertPrefix($key), $path, $value);
        if (!$result) {
            $this->handleError(RedisCommands::JSON_SET, $this->getLastError());
        }

        return true;
    }

    public function jsonDel(string $key, ?string $path = '$'): ?bool
    {
        return $this->rawCommand(RedisCommands::JSON_DELETE, static::convertPrefix($key), $path);
    }

    /**
     * @param \ReflectionProperty[] $properties
     */
    public function createIndex(string $prefixKey, ?string $format = 'HASH', ?array $properties = []): bool
    {
        $prefixKey = static::convertPrefix($prefixKey);

        $arguments = [
            RedisCommands::CREATE_INDEX,
            $prefixKey,
            'ON',
            $format,
        ];

        if ($format === RedisFormat::HASH) {
            $arguments[] = 'PREFIX';
            $arguments[] = '1';
            $arguments[] = $prefixKey . ':';
        }

        $arguments[] = 'SCHEMA';

        foreach ($properties as $reflectionProperty) {
            if (($propertyAttribute = $reflectionProperty->getAttributes(Property::class)) === []) {
                continue;
            }

            /** @var Property $property */
            $property = $propertyAttribute[0]->newInstance();
            if (!in_array($reflectionProperty->getType()->getName(), ['int', 'string', 'float', 'bool'])) {
                continue;
            }

            $type = ($reflectionProperty->getType() === 'int' || $reflectionProperty->getType() === 'float') ? Property::NUMERIC_TYPE : Property::TEXT_TYPE;

            $arguments[] = ($format === RedisFormat::JSON ? '$.' : '') . ($property->name !== null ? $property->name : $reflectionProperty->name);
            $arguments[] = 'AS';
            $arguments[] = $property->name ?? $reflectionProperty->name;
            $arguments[] = $type;
            $arguments[] = 'SORTABLE';
        }

        /** @var bool $rawResult */
        $rawResult = call_user_func_array([$this, 'rawCommand'], $arguments);

        return $rawResult;
    }

    public function dropIndex(string $prefixKey): bool
    {
        try {
            $key = static::convertPrefix($prefixKey);
            $this->rawCommand(RedisCommands::DROP_INDEX, $key);
        } catch (\RedisException $e) {
            return false;
        }

        return true;
    }

    public function count(string $prefixKey, array $criterias = []): int
    {
        $arguments = [RedisCommands::SEARCH, static::convertPrefix($prefixKey)];

        foreach ($criterias as $property => $value) {
            $arguments[] = sprintf("@%s:%s", $property, $value);
        }

        $rawResult = call_user_func_array([$this, 'rawCommand'], $arguments);

        return (int) $rawResult[0];
    }

    public function scanKeys(string $prefixKey): array
    {
        $keys = [];
        $iterator = null;
        while($iterator !== 0) {
            $scans = $this->scan($iterator, sprintf('%s*', static::convertPrefix($prefixKey)));
            foreach($scans as $scan) {
                $keys[] = $scan;
            }
        }

        return $keys;
    }

    public function search(string $prefixKey, array $search, array $orderBy, ?string $format = RedisFormat::HASH, ?int $numberOfResults = null): array
    {
        $arguments = [RedisCommands::SEARCH, static::convertPrefix($prefixKey)];

        if ($search === []) {
            $arguments[] = '*';
        } else {
            $criteria = '';
            foreach ($search as $property => $value) {
                $criteria .= sprintf("@%s:%s ", $property, $value);
            }

            $arguments[] = $criteria;
        }

        foreach ($orderBy as $property => $direction) {
            $arguments[] = 'SORTBY';
            $arguments[] = $property;
            $arguments[] = $direction;
        }

        try {
            $rawResult = call_user_func_array([$this, 'rawCommand'], $arguments);
        } catch (\RedisException $e) {
            $this->handleError(RedisCommands::SEARCH, $e->getMessage());
        }

        if ($rawResult[0] === 0) {
            return [];
        }

        $entities = [];
        foreach ($rawResult as $key => $redisData) {
            if ($key > 0 && $key % 2 == 0) {

                if ($format === RedisFormat::JSON) {
                    $data = json_decode($redisData[1], true);
                } else {
                    $data = [];
                    for ($i = 0; $i < count($redisData); $i += 2) {
                        $property = $redisData[$i];
                        $value = $redisData[$i + 1];
                        $data[$property] = $value;
                    }
                }

                $entities[] = $data;
                if (count($entities) === $numberOfResults) {
                    return $entities;
                }
            }
        }

        return $entities;
    }

    public static function convertPrefix(string $key): string
    {
        return str_replace('\\', '_', $key);
    }

    private function handleError(string $command, ?string $errorMessage = 'Unknown error'): void
    {
        throw new RedisClientResponseException(
            sprintf("something was wrong when executing %s command, reason: %s", $command, $errorMessage)
        );
    }
}
