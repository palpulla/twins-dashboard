<?php
declare(strict_types=1);

namespace Twins\BrandExperience;

interface AssetResolver
{
    public function url(string $assetKey): string;
}

interface RouteAdapter
{
    public function normalizeContext(array $requestContext): array;
    public function route(string $routeKey, string $marketKey): string;
}

interface ReviewsProvider
{
    public function collection(): array;
}

interface QuoteAdapter
{
    public function action(array $context): array;
    public function renderExperience(array $context): string;
    public function assertReady(): void;
}

interface BookingAdapter
{
    public function action(array $context): array;
    public function assertReady(): void;
}

interface ApplicationAdapter
{
    public function clientContract(array $context): array;
    public function renderExperience(array $context): string;
    public function assertReady(): void;
}
