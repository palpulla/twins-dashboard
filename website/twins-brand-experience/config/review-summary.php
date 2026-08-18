<?php
declare(strict_types=1);

/**
 * Google Business Profile aggregate summary for display and structured data.
 *
 * Live source: public.places_profile_summary on the Twins dashboard project,
 * refreshed daily by the sync-places-reviews cron (07:35), which already calls
 * the Places API with a field mask of reviews,rating,userRatingCount.
 *
 * The values below are the last hand-verified snapshot against the public
 * listing (place ChIJ6WuQE9VSBogRgy76ORRGfHs) and remain the fallback whenever
 * the read fails, times out, or returns something implausible. The site must
 * never show an invented rating, so a failed fetch degrades to this rather
 * than to zero or a blank.
 */

$twinsReviewSummaryFallback = [
    'ratingValue' => 4.9,
    'reviewCountFloor' => 699,
    'displayCount' => '699',
    'checkedAt' => '2026-07-20',
    'source' => 'snapshot',
];

if (!function_exists('get_transient') || !function_exists('wp_remote_get')) {
    return $twinsReviewSummaryFallback;
}

$cached = get_transient('twins_places_profile_summary');
if (is_array($cached) && isset($cached['ratingValue'], $cached['displayCount'])) {
    return $cached;
}

$endpoint = 'https://jwrpjuqaynownxaoeayi.supabase.co/rest/v1/places_profile_summary'
    . '?select=rating,user_rating_count,checked_at'
    . '&place_id=eq.ChIJ6WuQE9VSBogRgy76ORRGfHs';

$response = wp_remote_get($endpoint, [
    'timeout' => 4,
    'headers' => [
        // Publishable key. The row is public-read by policy and the figures are
        // already public on the Google listing.
        'apikey' => 'sb_publishable_22ascfuq2ALa8u5taJ0OZA_U_SpDewm',
        'Accept' => 'application/json',
    ],
]);

$summary = $twinsReviewSummaryFallback;

if (!is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200) {
    $rows = json_decode((string) wp_remote_retrieve_body($response), true);
    $row = is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : null;

    if ($row !== null && isset($row['rating'], $row['user_rating_count'])) {
        $rating = (float) $row['rating'];
        $count = (int) $row['user_rating_count'];

        // Guard against a bad upstream write putting a nonsense figure on the
        // homepage. Anything outside these bounds keeps the snapshot.
        if ($rating > 0.0 && $rating <= 5.0 && $count > 0) {
            $summary = [
                'ratingValue' => round($rating, 1),
                'reviewCountFloor' => $count,
                'displayCount' => (string) $count,
                'checkedAt' => isset($row['checked_at']) ? substr((string) $row['checked_at'], 0, 10) : $twinsReviewSummaryFallback['checkedAt'],
                'source' => 'live',
            ];
        }
    }
}

// Cache either outcome. A failed fetch backs off for an hour rather than
// hitting the endpoint on every page view.
set_transient(
    'twins_places_profile_summary',
    $summary,
    $summary['source'] === 'live' ? DAY_IN_SECONDS : HOUR_IN_SECONDS
);

return $summary;
