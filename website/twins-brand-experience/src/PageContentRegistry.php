<?php
declare(strict_types=1);

namespace Twins\BrandExperience;

final class PageContentRegistry
{
    /**
     * Fixed record shape for the 2026-08-18 service-page system: answer-first
     * block with fact chips, warning signs, process steps, a real-price cost
     * section, a safety module gated to the tension pages, service-tagged
     * review quotes, and a five-question FAQ. Every key is present on every
     * record; nullable keys are null where a capability does not apply.
     */
    private const REQUIRED_KEYS = [
        'h1',
        'directAnswer',
        'answerFacts',
        'priceRange',
        'warningSigns',
        'process',
        'costParagraphs',
        'safety',
        'plans',
        'reviewTag',
        'faqs',
        'links',
    ];

    private const BESPOKE_PATHS = [
        '/garage-door-repair/',
        '/garage-door-installation/',
        '/garage-door-spring-repair/',
        '/garage-door-opener-repair/',
        '/emergency-garage-services/',
        '/garage-door-services/',
        '/garage-door-cable-repair/',
        '/garage-door-openers/',
        '/garage-weatherstripping-repair/',
        '/garage-door-tune-up/',
        '/maintenance-plans/',
        '/property-management-services/',
    ];

    private const FALLBACK_TITLES = [
        '/garage-door-services/' => 'Garage Door Services',
        '/garage-door-cable-repair/' => 'Garage Door Cable Repair',
        '/garage-door-openers/' => 'Garage Door Openers',
        '/garage-weatherstripping-repair/' => 'Weatherstripping Repair',
        '/garage-door-tune-up/' => 'Garage Door Tune-Up',
        '/property-management-services/' => 'Property Management Services',
        '/maintenance-plans/' => 'TwinShield Protection Plan',
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
        'openers',
        'maintenance-plans',
    ];

    private const MARKET_PREFIXES = ['wi', 'ky', 'il'];

    /**
     * Pages that render the mandatory tension-safety module. Only these three
     * carry a safety string; every other record keeps safety null so the
     * module cannot quietly spread to pages it was not written for.
     */
    private const SAFETY_PATHS = [
        '/garage-door-spring-repair/',
        '/garage-door-cable-repair/',
        '/emergency-garage-services/',
    ];

    /** Service tags available in config/review-quotes.php. */
    private const REVIEW_TAGS = ['springs', 'openers', 'installation', 'emergency', 'general'];

    /**
     * Approved price figures from docs/marketing/website-rebuild/data/
     * price-ranges.json (completed HCP jobs, last 24 months, p20/p80), plus
     * the two approved offers ($0 service call with repair, $49 tune-up).
     * Any other dollar figure on a non-membership page fails closed here.
     */
    private const APPROVED_SERVICE_AMOUNTS = [
        '0', '49', '50', '100', '300', '325', '575', '625',
        '775', '1,225', '1,400', '2,625', '3,425', '3,525', '4,400',
    ];

    /**
     * Approved p20/p80 pairs from price-ranges.json for schema offers, plus
     * the combined installation span (single p20 to double p80) already
     * approved in build/seo-meta.json for door-installation pages.
     */
    private const APPROVED_PRICE_RANGES = [
        [575, 1225],
        [100, 300],
        [775, 1400],
        [325, 625],
        [2625, 3525],
        [3425, 4400],
        [2625, 4400],
        [50, 100],
    ];

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
        $answer = $this->plain($record['directAnswer'], 200, 520, 'direct answer');
        $words = preg_split('/\s+/', trim($answer));
        $wordCount = is_array($words) ? count($words) : 0;
        if ($wordCount < 40 || $wordCount > 60) {
            throw new \InvalidArgumentException('A direct answer is outside the fixed word boundary.');
        }

        $facts = $record['answerFacts'];
        if (!is_array($facts)) {
            throw new \InvalidArgumentException('A fixed answer-fact panel is invalid.');
        }
        $factKeys = array_keys($facts);
        sort($factKeys);
        if ($factKeys !== ['call', 'cost', 'timing']) {
            throw new \InvalidArgumentException('A fixed answer-fact panel has an unknown shape.');
        }
        foreach (['cost', 'timing'] as $optionalFact) {
            if ($facts[$optionalFact] !== null) {
                $this->plain($facts[$optionalFact], 1, 160, 'answer fact ' . $optionalFact);
            }
        }
        $this->plain($facts['call'], 1, 160, 'answer fact call');

        $range = $record['priceRange'];
        if ($range !== null) {
            if (!is_array($range)) {
                throw new \InvalidArgumentException('A fixed price range is invalid.');
            }
            $rangeKeys = array_keys($range);
            sort($rangeKeys);
            if ($rangeKeys !== ['max', 'min'] || !is_int($range['min']) || !is_int($range['max'])) {
                throw new \InvalidArgumentException('A fixed price range has an unknown shape.');
            }
            if (!in_array([$range['min'], $range['max']], self::APPROVED_PRICE_RANGES, true)) {
                throw new \InvalidArgumentException('A fixed price range is not an approved data figure.');
            }
        }

        $this->stringList($record['warningSigns'], 3, 5, 'warning signs');
        $this->stringList($record['process'], 3, 5, 'process');
        $this->stringList($record['costParagraphs'], 1, 4, 'cost paragraphs', 500);

        $isSafetyPath = in_array($path, self::SAFETY_PATHS, true);
        if ($isSafetyPath) {
            $safety = $this->plain($record['safety'], 20, 500, 'safety');
            if (stripos($safety, 'dangerous tension') === false || stripos($safety, 'trained professionals') === false) {
                throw new \InvalidArgumentException('The fixed tension safety boundary is incomplete.');
            }
        } elseif ($record['safety'] !== null) {
            throw new \InvalidArgumentException('A safety module is registered outside the fixed tension pages.');
        }

        // No page-content record publishes program rates.
        //
        // Until 2026-08-27 exactly one could: MEMBERSHIP_PATH named
        // /protection-plans/, and only that record was permitted a `plans`
        // array, checked against a MEMBERSHIP_RATES allowlist. That page has
        // been retired into /maintenance-plans/, which carries the real tiers
        // from config/twinshield-program.php through
        // components/twinshield-plan.php: a verbatim transcription of the
        // Housecall Pro plan templates, where this `plans` array was a
        // paraphrase of a subset of them.
        //
        // The guarantee "only one page may publish plan rates" did not leave
        // with those constants. It moved, and it got stricter:
        //   - config/twinshield-program.php is keyed by page path, and
        //     tests/contracts/twinshield-program.test.cjs asserts that key set
        //     is exactly ['/maintenance-plans/'], so the path itself is
        //     pinned, not merely how many there are;
        //   - twins_brand_twinshield_assert() re-checks every published amount
        //     against TWINS_TWINSHIELD_RATES, the same eighteen figures this
        //     class used to hold, and throws instead of rendering on drift.
        //
        // So the rule here is now unconditional. `plans` stays in the record
        // shape, always null, and templates/service.php keeps its null gate:
        // the key is the thing a future record would reach for, and it must
        // fail closed at the registry rather than quietly render a second,
        // thinner plan block beside the real one.
        if ($record['plans'] !== null) {
            throw new \InvalidArgumentException('Plan tiers no longer belong in page content.');
        }

        if (!is_string($record['reviewTag']) || !in_array($record['reviewTag'], self::REVIEW_TAGS, true)) {
            throw new \InvalidArgumentException('A fixed review tag is unknown.');
        }

        $this->nestedList($record['faqs'], 5, 5, ['question', 'answer'], [
            'question' => [5, 180],
            'answer' => [20, 600],
        ], 'faqs');
        $this->nestedList($record['links'], 2, 4, ['label', 'route'], [
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
            || preg_match('/\x{2013}|\x{2014}/u', $customerText)
            || preg_match('/24\s*\/\s*7|\b365\b|\blifetime\b/i', $customerText)
            || preg_match('/#1|number one|No\.\s*1|top-rated/i', $customerText)
            || preg_match('/\bbest\b/i', $customerText)
            || preg_match('/\b(?:warrant(?:y|ies)|guaranteed?)\b/i', $customerText)
            || preg_match('/\b(?:certified|certification|years in business)\b/i', $customerText)
            || preg_match('/replace (?:the )?spring yourself|DIY spring/i', $customerText)
            || preg_match('/\$\d+(?:\.\d+)?k\b/i', $customerText)
        ) {
            throw new \InvalidArgumentException('A fixed page-content record contains prohibited copy.');
        }
        $this->assertApprovedAmounts($customerText);
    }

    /**
     * Every published dollar amount must trace to an approved source: the
     * price-ranges.json figures plus the two approved offers. Program rates
     * are not page content and are validated in components/twinshield-plan.php.
     */
    private function assertApprovedAmounts(string $customerText): void
    {
        if (preg_match('/\bUSD\b|\d+\s*(?:-|to)\s*\d+\s*dollars?/i', $customerText) === 1) {
            throw new \InvalidArgumentException('A published amount uses an unapproved currency form.');
        }
        if (preg_match_all('/\$(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)/', $customerText, $amounts) === false) {
            throw new \InvalidArgumentException('A published amount could not be read.');
        }
        $approved = self::APPROVED_SERVICE_AMOUNTS;
        foreach ($amounts[1] as $amount) {
            if (in_array($amount, $approved, true)) {
                continue;
            }
            if (in_array(str_replace(',', '', $amount), $approved, true)) {
                continue;
            }
            throw new \InvalidArgumentException('A published amount is not an approved figure.');
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

    private function stringList($items, int $minimum, int $maximum, string $field, int $itemMaximum = 240): void
    {
        if (!is_array($items) || !$this->isList($items) || count($items) < $minimum || count($items) > $maximum) {
            throw new \InvalidArgumentException('A fixed ' . $field . ' list is invalid.');
        }
        foreach ($items as $item) {
            $this->plain($item, 1, $itemMaximum, $field);
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
            'answerFacts' => [
                'cost' => null,
                'timing' => null,
                'call' => 'Call when you want a technician to inspect the door and price the work first.',
            ],
            'priceRange' => null,
            'warningSigns' => [
                'The door is behaving differently and you are not sure which service fits.',
                'You want a technician to inspect the specific project.',
                'You want to review available next steps before authorizing work.',
            ],
            'process' => [
                'Describe what you observed without repeatedly operating the door.',
                'A technician inspects the specific service concern.',
                'Review the findings and available next steps.',
                'Review the exact price before work begins.',
            ],
            'costParagraphs' => [
                'This page does not publish a one-size-fits-all price. A technician inspects the project and provides the exact price before any work begins.',
            ],
            'safety' => null,
            'plans' => null,
            'reviewTag' => 'general',
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
                [
                    'question' => 'Which number should I call?',
                    'answer' => 'Use the number shown on this page. It follows the service area selected in the shared Twins experience, so the right one is already on your screen.',
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
