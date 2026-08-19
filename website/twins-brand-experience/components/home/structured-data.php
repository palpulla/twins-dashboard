<?php
declare(strict_types=1);

// Homepage structured data owned by this template, per
// docs/marketing/website-rebuild/build/schema/sitewide-org.jsonld:
// Organization + FAQPage (mirroring exactly the accordion rendered in
// components/home/closing.php).
//
// The renderer layer (twins-staging-overhaul/renderers.php,
// twins_overhaul_brand_schema_markup) already emits the LocalBusiness
// Madison-HQ node with the verified NAP and live aggregateRating for the
// home-brand classification, so this component deliberately does NOT emit a
// LocalBusiness and only adds the nodes the renderer never carries.
//
// Emission is gated on WordPress being present: absolute URLs only make
// sense on a real host, and the portable harness composition stays free of
// external URLs by contract.
$homeStructuredData = '';
if (function_exists('home_url') && isset($homeFaqs) && $homeFaqs !== []) {
    $homeMarketsConfig = require dirname(__DIR__, 2) . '/config/markets.php';
    $homeOrgPhoneHref = (string) ($homeMarketsConfig['main']['phoneHref'] ?? 'tel:+18338332010');
    $homeOrgTelephone = str_replace('tel:', '', $homeOrgPhoneHref);
    $homeOrgUrl = (string) home_url('/');
    $homeSchemaGraph = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => $homeOrgUrl . '#organization',
                'name' => 'Twins Garage Doors',
                'url' => $homeOrgUrl,
                'logo' => (string) home_url('/wp-content/mu-plugins/twins-brand-experience/assets/images/brand/twins-logo.png'),
                'slogan' => 'Your Garage Door, Done Right.',
                'email' => 'contact@twinsgaragedoors.com',
                'telephone' => $homeOrgTelephone,
                'founder' => [
                    ['@type' => 'Person', 'name' => 'Daniel Joseph'],
                    ['@type' => 'Person', 'name' => 'Tal Joseph'],
                ],
                'sameAs' => [
                    'https://www.facebook.com/twinsgaragedoors',
                    'https://www.linkedin.com/company/twinsgaragedoors/',
                    'https://www.youtube.com/channel/UCVjQn3Pq7RoUNVyo_MLSDmQ',
                ],
            ],
            [
                '@type' => 'FAQPage',
                '@id' => $homeOrgUrl . '#faq',
                'mainEntity' => array_map(
                    static fn(array $faq): array => [
                        '@type' => 'Question',
                        'name' => (string) $faq['question'],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => (string) $faq['answer']],
                    ],
                    $homeFaqs
                ),
            ],
        ],
    ];
    $homeStructuredDataCandidate = json_encode(
        $homeSchemaGraph,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
    );
    if (is_string($homeStructuredDataCandidate) && $homeStructuredDataCandidate !== '') {
        $homeStructuredData = $homeStructuredDataCandidate;
    }
}
?>
<?php if ($homeStructuredData !== ''): ?>
  <script type="application/ld+json"><?= $homeStructuredData ?></script>
<?php endif; ?>
