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
    ['Maintenance Plans', 'maintenance-plans'],
    ['Property Management', 'property-management'],
    ['Emergency Service', 'emergency-service'],
];
$twinsNavServiceAvailability = [
    'main' => ['services', 'repair', 'installation', 'spring-repair', 'opener-repair', 'cable-repair', 'weatherstripping', 'maintenance-plans', 'property-management', 'emergency-service'],
    'wi' => ['services', 'repair', 'installation', 'spring-repair', 'opener-repair', 'cable-repair', 'weatherstripping', 'property-management', 'emergency-service'],
    'ky' => ['services', 'repair', 'installation', 'spring-repair', 'opener-repair', 'cable-repair', 'maintenance-plans', 'emergency-service'],
    'il-preview' => ['services', 'repair', 'installation', 'opener-repair', 'emergency-service'],
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
        ['Oregon', 'city-oregon'],
        ['Prairie du Sac', 'city-prairie-du-sac'],
        ['Sun Prairie', 'city-sun-prairie'],
        ['Verona', 'city-verona'],
    ],
    'ky' => [
        ['Lexington', 'city-lexington'],
    ],
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
foreach ($experience->markets()->all($environment) as $twinsNavMarketKey => $twinsNavMarket) {
    if ($twinsNavMarketKey === 'main') continue;
    $serviceAreasCompact[] = [$twinsNavMarket['label'], $twinsNavMarketKey];
}
$marketCityLinks = $twinsNavCityLinks[$marketKey] ?? [];
$serviceAreas = array_merge($serviceAreasCompact, $marketCityLinks);

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
