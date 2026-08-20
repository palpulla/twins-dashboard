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
        foreach ($markets as $market) {
            if (!isset($market['retired']) || !is_bool($market['retired'])) {
                throw new \InvalidArgumentException('Every market must declare a boolean retired flag.');
            }
        }
        $this->markets = $markets;
    }

    public function all(string $environment): array
    {
        $flag = $environment === 'staging' ? 'stagingEnabled' : ($environment === 'production' ? 'productionEnabled' : '');
        if ($flag === '') throw new \DomainException('Unknown environment.');
        return array_filter($this->markets, static fn(array $market): bool => $market[$flag] === true);
    }

    /**
     * Markets a visitor may be offered: enabled for the environment AND not retired.
     * Every visitor-facing market list must use this, never all(). all() stays the
     * resolution surface so an archived market keeps rendering for its own subsite
     * and for the isolation harnesses.
     */
    public function selectable(string $environment): array
    {
        return array_filter(
            $this->all($environment),
            static fn(array $market): bool => $market['retired'] === false
        );
    }

    public function resolve(string $key, string $environment): array
    {
        $enabled = $this->all($environment);
        if (!isset($enabled[$key])) throw new \DomainException('Market is unavailable in this environment.');
        return $enabled[$key] + ['key' => $key];
    }
}
