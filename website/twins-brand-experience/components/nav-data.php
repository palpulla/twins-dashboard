<?php
declare(strict_types=1);

/**
 * Shared navigation data for the branded header and footer.
 * Requires $experience, $marketKey, and $environment in scope.
 */

$twinsNavServiceCatalog = [
    ['All Services', 'services'],
    ['Garage Door Repair', 'repair'],
    ['Garage Door Installation', 'installation'],
    ['Spring Repair', 'spring-repair'],
    ['Opener Repair', 'opener-repair'],
    ['Cable Repair', 'cable-repair'],
    ['Weatherstripping Repair', 'weatherstripping'],
    ['Protection Plan', 'maintenance-plans'],
    ['Property Management', 'property-management'],
    ['Emergency Service', 'emergency-service'],
];
// A per-market statement of what that market's route table carries, because
// route() is fail-closed: a key listed here that the table lacks is a fatal,
// not a dead link. WI and IL genuinely had no 'maintenance-plans' route when
// this list was written (0c7d4dc2, 2026-07-17), so leaving it out was correct
// then. The r30 host capture (603a097f, 2026-08-18) gave both markets the
// shared main-site '/maintenance-plans/' and this list was never updated, so
// the plan page kept its homepage CTA in every market while the WI and IL
// headers offered no way in at all. Kentucky routes to its own
// '/ky/maintenance-plans/'; IL omits 'spring-repair' on purpose, its route
// points at the same page as 'repair'.
$twinsNavServiceAvailability = [
    'main' => ['services', 'repair', 'installation', 'spring-repair', 'opener-repair', 'cable-repair', 'weatherstripping', 'maintenance-plans', 'property-management', 'emergency-service'],
    'wi' => ['services', 'repair', 'installation', 'spring-repair', 'opener-repair', 'cable-repair', 'weatherstripping', 'maintenance-plans', 'property-management', 'emergency-service'],
    'ky' => ['services', 'repair', 'installation', 'spring-repair', 'opener-repair', 'cable-repair', 'maintenance-plans', 'emergency-service'],
    'il-preview' => ['services', 'repair', 'installation', 'opener-repair', 'maintenance-plans', 'emergency-service'],
];
$twinsNavAllowedServices = $twinsNavServiceAvailability[$marketKey] ?? $twinsNavServiceAvailability['main'];
$serviceItems = array_values(array_filter(
    $twinsNavServiceCatalog,
    static fn(array $item): bool => in_array($item[1], $twinsNavAllowedServices, true)
));

$garageDoorItems = [
    ['Garage Door Collections', 'garage-doors'],
    ['Garage Door Openers', 'openers'],
    ['Design Your Door', 'door-builder'],
];

$twinsNavCityLinks = [
    'wi' => [
        ['Wisconsin Service Areas', 'service-area'],
        ['Madison', 'city-madison'],
        ['Milwaukee', 'city-milwaukee'],
        ['Baraboo', 'city-baraboo'],
        ['Barneveld', 'city-barneveld'],
        ['Belleville', 'city-belleville'],
        ['Beloit', 'city-beloit'],
        ['Brookfield', 'city-brookfield'],
        ['Brooklyn', 'city-brooklyn'],
        ['Cambridge', 'city-cambridge'],
        ['Columbus', 'city-columbus'],
        ['Cottage Grove', 'city-cottage-grove'],
        ['Cross Plains', 'city-cross-plains'],
        ['Deerfield', 'city-deerfield'],
        ['DeForest', 'city-deforest'],
        ['Edgerton', 'city-edgerton'],
        ['Evansville', 'city-evansville'],
        ['Fall River', 'city-fall-river'],
        ['Fitchburg', 'city-fitchburg'],
        ['Fort Atkinson', 'city-fort-atkinson'],
        ['Greenfield', 'city-greenfield'],
        ['Janesville', 'city-janesville'],
        ['Lodi', 'city-lodi'],
        ['Marshall', 'city-marshall'],
        ['McFarland', 'city-mcfarland'],
        ['Middleton', 'city-middleton'],
        ['Milton', 'city-milton'],
        ['Monona', 'city-monona'],
        ['Monroe', 'city-monroe'],
        ['Mount Horeb', 'city-mount-horeb'],
        ['New Berlin', 'city-new-berlin'],
        ['New Glarus', 'city-new-glarus'],
        ['Oak Creek', 'city-oak-creek'],
        ['Oregon', 'city-oregon'],
        ['Pardeeville', 'city-pardeeville'],
        ['Portage', 'city-portage'],
        ['Prairie du Sac', 'city-prairie-du-sac'],
        ['Reedsburg', 'city-reedsburg'],
        ['Rio', 'city-rio'],
        ['Sauk City', 'city-sauk-city'],
        ['Stoughton', 'city-stoughton'],
        ['Sun Prairie', 'city-sun-prairie'],
        ['Verona', 'city-verona'],
        ['Watertown', 'city-watertown'],
        ['Waukesha', 'city-waukesha'],
        ['Waunakee', 'city-waunakee'],
        ['Wauwatosa', 'city-wauwatosa'],
        ['Windsor', 'city-windsor'],
    ],
    // No 'ky' entry. Kentucky is retired (Brand Toolkit v1.0) and every front-end
    // /ky/ request is 301'd by twins_overhaul_redirect_retired_ky_market() (r33),
    // so its city link was the one genuinely dead link on the site. The archived
    // blog-3 legacy nav in the host package still carries it, behind that
    // redirect. tests/contracts/service-area-tree.test.cjs pins its absence here.
    'il-preview' => [
        ['Illinois Service Areas', 'service-area'],
        ['Rockford', 'city-rockford'],
        ['Loves Park', 'city-loves-park'],
        ['Machesney Park', 'city-machesney-park'],
        ['Belvidere', 'city-belvidere'],
        ['Roscoe', 'city-roscoe'],
        ['Rockton', 'city-rockton'],
        ['Cherry Valley', 'city-cherry-valley'],
        ['Poplar Grove', 'city-poplar-grove'],
        ['South Beloit', 'city-south-beloit'],
        ['Winnebago', 'city-winnebago'],
        ['Byron', 'city-byron'],
        ['Caledonia', 'city-caledonia'],
    ],
];

$serviceAreasCompact = [];
if ($marketKey === 'main') {
    $serviceAreasCompact[] = ['All Service Areas', 'service-area'];
}
// selectable(), never all(): a retired market must never be offered to a visitor.
foreach ($experience->markets()->selectable($environment) as $twinsNavMarketKey => $twinsNavMarket) {
    if ($twinsNavMarketKey === 'main') continue;
    $serviceAreasCompact[] = [$twinsNavMarket['label'], $twinsNavMarketKey];
}
$marketCityLinks = $twinsNavCityLinks[$marketKey] ?? [];

/**
 * Service-area tree. One entry per metro Twins actually staffs.
 *
 * ONE RULE GENERATES THIS WHOLE STRUCTURE:
 *   a town appears in the header menu if and only if it has a record in
 *   config/location-content.php, filed under that record's own 'metro' field.
 *   Every other provisioned town lives on the metro hub page, which every metro
 *   group links to. 35 records: 27 madison, 7 milwaukee, 1 rockford. The old
 *   flat menu carried 60 entries with markets, hubs and villages at one level.
 *
 *   'label'      customer-facing metro name (Lilita One caps in the panel).
 *   'market'     the market key whose route table owns this metro's city routes.
 *                A metro renders expanded only when this equals $marketKey:
 *                city-* routes are market-scoped, so route('city-rockford','wi')
 *                throws. Other markets degrade to a card. The unglamorous real
 *                fix is cross-market city-* routes in four route tables; that is
 *                out of scope for this release.
 *   'hubLabel'   the "see everything" row for this metro.
 *   'hubAnchor'  fragment appended to the market's service-area route.
 *   'towns'      [$label, $routeKey] pairs in DISPLAY order: metro hub city
 *                first, then strict alphabetical. A first-time visitor hunting
 *                "Stoughton" in 27 items needs A-Z, not position memory.
 *                Destructuring MUST stay [$label, $routeKey] -- the variable
 *                names are inside a pinned regex.
 *   'featured'   route keys the homepage closing block shows. Madison's six are
 *                exactly the records with completedJobs >= 50 in
 *                config/location-content.php (Madison 588, Verona 151,
 *                Fitchburg 132, Middleton 93, Sun Prairie 83, Janesville 53 --
 *                the next town is Pardeeville at 39, the largest gap in the
 *                distribution). Milwaukee and Rockford carry completedJobs =>
 *                null on every record, so their featured sets are hand-placed
 *                (hub city plus alphabetical), stated here rather than dressed
 *                up as data.
 *
 * INVARIANT: the union of every 'towns' routeKey equals exactly the key set of
 * config/location-content.php, and each town's metro equals that record's own
 * 'metro' field. Enforced by tests/contracts/service-area-tree.test.cjs.
 * Provisioned-but-unwritten towns belong on the hub page, never in the menu.
 */
$twinsNavMetroTree = [
    'madison' => [
        'label' => 'Madison Metro',
        'market' => 'wi',
        'hubLabel' => 'All Madison-area towns',
        'hubAnchor' => '#madison-metro',
        'featured' => ['city-madison', 'city-verona', 'city-fitchburg', 'city-middleton', 'city-sun-prairie', 'city-janesville'],
        'towns' => [
            ['Madison', 'city-madison'],
            ['Baraboo', 'city-baraboo'],
            ['Belleville', 'city-belleville'],
            ['Cottage Grove', 'city-cottage-grove'],
            ['Cross Plains', 'city-cross-plains'],
            ['Deerfield', 'city-deerfield'],
            ['DeForest', 'city-deforest'],
            ['Edgerton', 'city-edgerton'],
            ['Evansville', 'city-evansville'],
            ['Fitchburg', 'city-fitchburg'],
            ['Fort Atkinson', 'city-fort-atkinson'],
            ['Janesville', 'city-janesville'],
            ['Marshall', 'city-marshall'],
            ['McFarland', 'city-mcfarland'],
            ['Middleton', 'city-middleton'],
            ['Milton', 'city-milton'],
            ['Monona', 'city-monona'],
            ['Mount Horeb', 'city-mount-horeb'],
            ['Oregon', 'city-oregon'],
            ['Pardeeville', 'city-pardeeville'],
            ['Portage', 'city-portage'],
            ['Prairie du Sac', 'city-prairie-du-sac'],
            ['Reedsburg', 'city-reedsburg'],
            ['Stoughton', 'city-stoughton'],
            ['Sun Prairie', 'city-sun-prairie'],
            ['Verona', 'city-verona'],
            ['Waunakee', 'city-waunakee'],
        ],
    ],
    'milwaukee' => [
        'label' => 'Milwaukee Metro',
        'market' => 'wi',
        'hubLabel' => 'All Milwaukee-area towns',
        'hubAnchor' => '#milwaukee-metro',
        'featured' => ['city-milwaukee', 'city-brookfield', 'city-greenfield', 'city-new-berlin', 'city-oak-creek', 'city-waukesha'],
        'towns' => [
            ['Milwaukee', 'city-milwaukee'],
            ['Brookfield', 'city-brookfield'],
            ['Greenfield', 'city-greenfield'],
            ['New Berlin', 'city-new-berlin'],
            ['Oak Creek', 'city-oak-creek'],
            ['Waukesha', 'city-waukesha'],
            ['Wauwatosa', 'city-wauwatosa'],
        ],
    ],
    'rockford' => [
        'label' => 'Northern Illinois',
        'market' => 'il-preview',
        'hubLabel' => 'All northern Illinois towns',
        'hubAnchor' => '#rockford-metro',
        'featured' => ['city-rockford'],
        'towns' => [
            ['Rockford', 'city-rockford'],
        ],
    ],
];

/** Shown for a market that is NOT the current one. No counts that can drift silently. */
$twinsNavMarketBlurbs = [
    'wi' => 'Madison, Milwaukee and the rest of south-central Wisconsin.',
    'il-preview' => 'Serving Rockford and nearby communities.',
];
$twinsNavMarketMenuLabels = [
    'wi' => 'Wisconsin',
    'il-preview' => 'Northern Illinois',
];

/* ---- derived ---- */

// Metros the current market can actually link into.
$twinsNavAreaMetros = [];
foreach ($twinsNavMetroTree as $twinsNavMetroKey => $twinsNavMetro) {
    if ($twinsNavMetro['market'] !== $marketKey) continue;
    $twinsNavMetro['key'] = $twinsNavMetroKey;
    $twinsNavMetro['townCount'] = count($twinsNavMetro['towns']);
    $twinsNavAreaMetros[] = $twinsNavMetro;
}

// Other selectable markets, reachable only at their front door.
$twinsNavAreaMarkets = [];
foreach ($experience->markets()->selectable($environment) as $twinsNavMarketKey => $twinsNavMarket) {
    if ($twinsNavMarketKey === 'main' || $twinsNavMarketKey === $marketKey) continue;
    $twinsNavAreaMarkets[] = [
        'key' => $twinsNavMarketKey,
        'label' => $twinsNavMarketMenuLabels[$twinsNavMarketKey] ?? $twinsNavMarket['label'],
        'blurb' => $twinsNavMarketBlurbs[$twinsNavMarketKey] ?? '',
    ];
}

// Panel-foot hub link, shown only when the panel has no metro columns (main).
//
// Gated on the markets whose route tables actually own a 'service-area' key.
// The route adapters are fail-closed by design -- route() throws on an unknown
// key rather than emitting a dead href -- so offering a hub a market does not
// have is a fatal, not a broken link. Retired Kentucky has neither a metro nor
// a hub route, and its archived subsite must keep composing its chrome, so it
// gets the market cards alone. Same shape as $twinsNavServiceAvailability
// above: a per-market statement of what its route table carries.
$twinsNavAreaHubMarkets = ['main', 'wi', 'il-preview'];
$twinsNavAreaHub = in_array($marketKey, $twinsNavAreaHubMarkets, true)
    ? ['All service areas', 'service-area']
    : null;

$resourceItems = [
    ['Reviews', 'reviews'],
    ['Financing', 'financing'],
    ['Offers', 'offers'],
    ['Frequently Asked Questions', 'faqs'],
    ['Blog', 'blog'],
];
if (in_array($marketKey, ['main', 'wi'], true)) {
    array_splice($resourceItems, 1, 0, [['Wisconsin Garage Door Cost Guide', 'cost-guide']]);
}

$aboutItems = [
    ['About Twins', 'about'],
    ['Our Team', 'team'],
    ['Careers', 'careers'],
    ['Contact Us', 'contact'],
];
