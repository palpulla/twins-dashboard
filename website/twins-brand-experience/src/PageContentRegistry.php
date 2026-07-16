<?php
declare(strict_types=1);

namespace Twins\BrandExperience;

final class PageContentRegistry
{
    private const REQUIRED_KEYS = [
        'h1',
        'directAnswer',
        'needs',
        'safety',
        'process',
        'options',
        'prepare',
        'faqs',
        'links',
    ];

    private const BESPOKE_PATHS = [
        '/garage-door-repair/',
        '/garage-door-installation/',
        '/garage-door-spring-repair/',
        '/garage-door-opener-repair/',
        '/emergency-garage-services/',
    ];

    private const FALLBACK_TITLES = [
        '/garage-door-services/' => 'Garage Door Services',
        '/garage-door-cable-repair/' => 'Garage Door Cable Repair',
        '/garage-door-openers/' => 'Garage Door Openers',
        '/garage-weatherstripping-repair/' => 'Weatherstripping Repair',
        '/garage-door-tune-up/' => 'Garage Door Tune-Up',
        '/property-management-services/' => 'Property Management Services',
        '/maintenance-plans/' => 'Maintenance Plans',
        '/protection-plans/' => 'TwinShield Protection Plan',
    ];

    private const LINK_ROUTES = [
        'services',
        'repair',
        'installation',
        'spring-repair',
        'opener-repair',
        'emergency-service',
        'garage-doors',
        'door-builder',
        'contact',
    ];

    private const MARKET_PREFIXES = ['wi', 'ky', 'il'];

    private array $records;

    public function __construct(array $records)
    {
        if (array_keys($records) !== self::BESPOKE_PATHS) {
            throw new \InvalidArgumentException('The fixed page-content registry is incomplete.');
        }
        foreach ($records as $path => $record) {
            if (!is_array($record)) {
                throw new \InvalidArgumentException('A fixed page-content record is invalid.');
            }
            $this->validateRecord($path, $record);
        }
        $this->records = $records;
    }

    public function resolve(string $path, string $title): array
    {
        $path = $this->normalizePath($path);
        if (isset($this->records[$path])) {
            return $this->records[$path];
        }
        if (!isset(self::FALLBACK_TITLES[$path])) {
            throw new \DomainException('The path is outside the fixed service registry.');
        }
        $fallback = $this->genericServiceRecord(self::FALLBACK_TITLES[$path]);
        $this->validateRecord($path, $fallback);
        return $fallback;
    }

    private function normalizePath(string $path): string
    {
        if ($path === '' || strlen($path) > 240) {
            throw new \InvalidArgumentException('Service path length is outside the fixed boundary.');
        }
        if ($path[0] !== '/' || strncmp($path, '//', 2) === 0) {
            throw new \InvalidArgumentException('Service path must be root-relative.');
        }
        if (
            strpos($path, '//') !== false
            || strpos($path, '?') !== false
            || strpos($path, '#') !== false
            || strpos($path, '\\') !== false
            || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $path)
            || preg_match('~%(2f|5c)~i', $path)
            || preg_match('~%[0-9a-f]{2}~i', $path)
            || preg_match('/[\x00-\x20\x7f]/', $path)
        ) {
            throw new \InvalidArgumentException('Service path contains an unsafe segment.');
        }

        if (
            preg_match('~^/([a-z]{2})(?:/(.*))?/?$~D', $path, $marketMatch) === 1
            && in_array($marketMatch[1], self::MARKET_PREFIXES, true)
        ) {
            $path = '/' . (isset($marketMatch[2]) ? trim($marketMatch[2], '/') : '');
        }
        if (preg_match('~^/[a-z0-9]+(?:-[a-z0-9]+)*/?$~D', $path) !== 1) {
            throw new \InvalidArgumentException('Service path is not a normalized terminal slug.');
        }
        return '/' . trim($path, '/') . '/';
    }

    private function validateRecord(string $path, array $record): void
    {
        $keys = array_keys($record);
        sort($keys);
        $required = self::REQUIRED_KEYS;
        sort($required);
        if ($keys !== $required) {
            throw new \InvalidArgumentException('A page-content record has an unknown shape.');
        }

        $this->plain($record['h1'], 1, 80, 'h1');
        $answer = $this->plain($record['directAnswer'], 200, 500, 'direct answer');
        $words = preg_split('/\s+/', trim($answer));
        $wordCount = is_array($words) ? count($words) : 0;
        if ($wordCount < 40 || $wordCount > 60) {
            throw new \InvalidArgumentException('A direct answer is outside the fixed word boundary.');
        }
        $this->stringList($record['needs'], 3, 5, 'needs');
        $this->plain($record['safety'], 20, 400, 'safety');
        $this->stringList($record['process'], 3, 5, 'process');
        $this->nestedList($record['options'], 2, 4, ['option', 'tradeoff'], [
            'option' => [1, 100],
            'tradeoff' => [1, 300],
        ], 'options');
        $this->stringList($record['prepare'], 3, 5, 'prepare');
        $this->nestedList($record['faqs'], 4, 6, ['question', 'answer'], [
            'question' => [5, 180],
            'answer' => [20, 500],
        ], 'faqs');
        $this->nestedList($record['links'], 3, 5, ['label', 'route'], [
            'label' => [1, 100],
            'route' => [1, 40],
        ], 'links');

        $questions = [];
        foreach ($record['faqs'] as $faq) {
            if (substr($faq['question'], -1) !== '?') {
                throw new \InvalidArgumentException('A fixed FAQ question lacks punctuation.');
            }
            $normalized = strtolower($faq['question']);
            if (isset($questions[$normalized])) {
                throw new \InvalidArgumentException('A fixed FAQ question is duplicated.');
            }
            $questions[$normalized] = true;
        }

        $linkRoutes = [];
        foreach ($record['links'] as $link) {
            if (!in_array($link['route'], self::LINK_ROUTES, true)) {
                throw new \InvalidArgumentException('A fixed page-content route key is unknown.');
            }
            if (isset($linkRoutes[$link['route']])) {
                throw new \InvalidArgumentException('A fixed page-content route key is duplicated.');
            }
            $linkRoutes[$link['route']] = true;
        }

        $values = $this->flattenValues($record);
        $customerText = implode("\n", $values);
        if (
            preg_match('/\(\d{3}\)\s*\d{3}-\d{4}/', $customerText)
            || preg_match('/\b(?:Wisconsin|Kentucky|Illinois|Madison|Milwaukee|Rockford|Lexington)\b/i', $customerText)
            || preg_match('/(?:\$|USD)\s*\d|\d+\s*[-–]\s*\d+\s*(?:dollars?|USD)/i', $customerText)
            || preg_match('/#1|number one|No\.\s*1|top-rated|\bbest\b/i', $customerText)
            || preg_match('/\b(?:warranty|guarantee|certified|certification|years in business|cycle rating)\b/i', $customerText)
            || preg_match('/\b(?:24\/7|365|same-day|same-visit|in-one-visit|fastest|most|often|usually|likely|quieter)\b|lower cost|higher cost|fewer return visits/i', $customerText)
            || preg_match('/replace (?:the )?spring yourself|DIY spring|with the proper tools/i', $customerText)
        ) {
            throw new \InvalidArgumentException('A fixed page-content record contains prohibited copy.');
        }
        if ($path === '/garage-door-spring-repair/') {
            if (stripos($record['safety'], 'dangerous tension') === false || stripos($record['safety'], 'trained professionals') === false) {
                throw new \InvalidArgumentException('The fixed spring safety boundary is incomplete.');
            }
        }
    }

    private function plain($value, int $minimum, int $maximum, string $field): string
    {
        if (!is_string($value) || $value !== trim($value)) {
            throw new \InvalidArgumentException('A fixed ' . $field . ' value is invalid.');
        }
        $length = strlen($value);
        if ($length < $minimum || $length > $maximum) {
            throw new \InvalidArgumentException('A fixed ' . $field . ' value is outside its boundary.');
        }
        if (
            preg_match('/[\x00-\x1f\x7f]/', $value)
            || strpos($value, '<') !== false
            || strpos($value, '>') !== false
            || preg_match('~(?:[a-z][a-z0-9+.-]*:)?//~i', $value)
        ) {
            throw new \InvalidArgumentException('A fixed ' . $field . ' value is not plain text.');
        }
        return $value;
    }

    private function stringList($items, int $minimum, int $maximum, string $field): void
    {
        if (!is_array($items) || !$this->isList($items) || count($items) < $minimum || count($items) > $maximum) {
            throw new \InvalidArgumentException('A fixed ' . $field . ' list is invalid.');
        }
        foreach ($items as $item) {
            $this->plain($item, 1, 240, $field);
        }
    }

    private function nestedList($items, int $minimum, int $maximum, array $keys, array $bounds, string $field): void
    {
        if (!is_array($items) || !$this->isList($items) || count($items) < $minimum || count($items) > $maximum) {
            throw new \InvalidArgumentException('A fixed ' . $field . ' list is invalid.');
        }
        $sortedKeys = $keys;
        sort($sortedKeys);
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('A fixed ' . $field . ' item is invalid.');
            }
            $itemKeys = array_keys($item);
            sort($itemKeys);
            if ($itemKeys !== $sortedKeys) {
                throw new \InvalidArgumentException('A fixed ' . $field . ' item has an unknown shape.');
            }
            foreach ($keys as $key) {
                $this->plain($item[$key], $bounds[$key][0], $bounds[$key][1], $field . ' ' . $key);
            }
        }
    }

    private function isList(array $items): bool
    {
        return array_keys($items) === range(0, count($items) - 1);
    }

    private function flattenValues(array $record): array
    {
        $values = [];
        array_walk_recursive($record, static function ($value) use (&$values): void {
            if (is_string($value)) {
                $values[] = $value;
            }
        });
        return $values;
    }

    private function genericServiceRecord(string $boundedTitle): array
    {
        return [
            'h1' => $boundedTitle,
            'directAnswer' => 'This page provides general guidance for a garage door service without service-specific content. A technician can inspect the project, explain the available next steps, and provide an exact price before work begins. Use the regional call or quote option shown on the page, and avoid operating the door if it appears unsafe.',
            'needs' => [
                'You need help identifying the appropriate garage door service path.',
                'You want a technician to inspect the specific project.',
                'You want to review available next steps before authorizing work.',
            ],
            'safety' => 'If the door appears unsafe, stop using it and keep people, pets, and vehicles clear. Do not handle the spring system; springs are under dangerous tension and should be handled by trained professionals.',
            'process' => [
                'Describe what you observed without repeatedly operating the door.',
                'A technician inspects the specific service concern.',
                'Review the findings and available next steps.',
                'Review the exact price before work begins.',
            ],
            'options' => [
                [
                    'option' => 'Service-specific next step',
                    'tradeoff' => 'Use the inspection findings to select the appropriate fixed service path.',
                ],
                [
                    'option' => 'Pause before authorizing work',
                    'tradeoff' => 'Review the findings and exact price before choosing a next step.',
                ],
            ],
            'prepare' => [
                'Note what you observed from a safe distance.',
                'Keep the door area clear if the situation appears unsafe.',
                'Do not handle or adjust the spring system.',
            ],
            'faqs' => [
                [
                    'question' => 'Why am I seeing general service guidance?',
                    'answer' => 'This service route does not yet have bespoke content. The page provides safe general guidance without inventing a diagnosis, process, price, or outcome.',
                ],
                [
                    'question' => 'Will a technician inspect the specific concern?',
                    'answer' => 'A technician inspects the specific project before repair work is priced. This fixed page does not diagnose the door in advance.',
                ],
                [
                    'question' => 'Will I know the exact price before work begins?',
                    'answer' => 'Review the exact price before work begins. This page does not publish a one-size-fits-all price.',
                ],
                [
                    'question' => 'What should I do if the door appears unsafe?',
                    'answer' => 'Stop using it, keep people, pets, and vehicles clear, and do not force it. Do not handle the spring system.',
                ],
            ],
            'links' => [
                ['label' => 'All Garage Door Services', 'route' => 'services'],
                ['label' => 'Emergency Garage Door Service', 'route' => 'emergency-service'],
                ['label' => 'Contact Twins', 'route' => 'contact'],
            ],
        ];
    }
}
