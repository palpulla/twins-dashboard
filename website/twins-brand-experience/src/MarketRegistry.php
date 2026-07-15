<?php
declare(strict_types=1);

namespace Twins\BrandExperience;

final class MarketRegistry
{
    private array $markets;

    public function __construct(array $markets)
    {
        if (array_keys($markets) !== ['main', 'wi', 'ky', 'il-preview']) {
            throw new \InvalidArgumentException('The fixed market registry is incomplete.');
        }
        $this->markets = $markets;
    }

    public function all(string $environment): array
    {
        $flag = $environment === 'staging' ? 'stagingEnabled' : ($environment === 'production' ? 'productionEnabled' : '');
        if ($flag === '') throw new \DomainException('Unknown environment.');
        return array_filter($this->markets, static fn(array $market): bool => $market[$flag] === true);
    }

    public function resolve(string $key, string $environment): array
    {
        $enabled = $this->all($environment);
        if (!isset($enabled[$key])) throw new \DomainException('Market is unavailable in this environment.');
        return $enabled[$key] + ['key' => $key];
    }
}
