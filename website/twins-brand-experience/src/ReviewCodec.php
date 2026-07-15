<?php
declare(strict_types=1);

namespace Twins\BrandExperience;

final class ReviewCodec
{
    private static function field(string $value): string
    {
        return strlen($value) . ':' . $value . "\n";
    }

    public static function stableId(array $record): string
    {
        if (isset($record['providerId']) && is_string($record['providerId']) && $record['providerId'] !== '') {
            return $record['providerId'];
        }
        $body = "twins-review-id-v1\n";
        foreach (['author', 'rating', 'publishedDate', 'text'] as $key) {
            $body .= self::field((string) $record[$key]);
        }
        return hash('sha256', $body);
    }

    public static function recordSha256(array $record): string
    {
        $body = "twins-review-v1\n";
        foreach (['stableId', 'author', 'rating', 'publishedDate', 'text', 'sourceRecordUrl'] as $key) {
            $body .= self::field((string) ($record[$key] ?? ''));
        }
        return hash('sha256', $body);
    }

    public static function verifyCollection(array $envelope, \DateTimeImmutable $now): array
    {
        $fixed = [
            'schemaVersion' => 1,
            'sourceUrl' => 'https://twinsgaragedoors.com/wi/reviews/',
            'multisitePath' => '/wi/',
            'pageId' => 2186,
            'collectionId' => 2178,
        ];
        foreach ($fixed as $key => $value) {
            if (($envelope[$key] ?? null) !== $value) throw new \UnexpectedValueException('Review provenance mismatch: ' . $key);
        }
        $businessReviewsUrl = $envelope['businessReviewsUrl'] ?? null;
        if (!is_string($businessReviewsUrl) || filter_var($businessReviewsUrl, FILTER_VALIDATE_URL) === false || parse_url($businessReviewsUrl, PHP_URL_SCHEME) !== 'https') throw new \UnexpectedValueException('Invalid business reviews URL.');
        if (!preg_match('/^[a-f0-9]{64}$/', (string) ($envelope['sourceResponseSha256'] ?? ''))) throw new \UnexpectedValueException('Invalid source hash.');
        if (!is_string($envelope['providerVersion'] ?? null) || $envelope['providerVersion'] === '') throw new \UnexpectedValueException('Missing provider version.');
        $captured = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', (string) ($envelope['capturedAt'] ?? ''), new \DateTimeZone('UTC'));
        if (!$captured || $captured->diff($now)->days > 90 || $captured > $now) throw new \UnexpectedValueException('Review capture is stale or future-dated.');
        if (!is_array($envelope['records'] ?? null) || count($envelope['records']) < 5) throw new \UnexpectedValueException('Insufficient verified reviews.');
        if (!is_int($envelope['recordCount'] ?? null) || $envelope['recordCount'] !== count($envelope['records'])) throw new \UnexpectedValueException('Review record count mismatch.');
        $ids = [];
        foreach ($envelope['records'] as $record) {
            $publishedDate = (string) ($record['publishedDate'] ?? '');
            $calendarDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $publishedDate, new \DateTimeZone('UTC'));
            if (!$calendarDate || $calendarDate->format('Y-m-d') !== $publishedDate) throw new \UnexpectedValueException('Review date is not an exact calendar date.');
            $rating = $record['rating'] ?? null;
            if (!is_int($rating) || $rating < 1 || $rating > 5) throw new \UnexpectedValueException('Invalid rating.');
            if (!is_string($record['author'] ?? null) || !is_string($record['text'] ?? null)) throw new \UnexpectedValueException('Invalid review text fields.');
            $sourceRecordUrl = $record['sourceRecordUrl'] ?? '';
            if (!is_string($sourceRecordUrl) || ($sourceRecordUrl !== '' && (filter_var($sourceRecordUrl, FILTER_VALIDATE_URL) === false || parse_url($sourceRecordUrl, PHP_URL_SCHEME) !== 'https'))) throw new \UnexpectedValueException('Invalid source record URL.');
            if (!is_string($record['stableId'] ?? null) || $record['stableId'] === '' || strlen($record['stableId']) > 256 || preg_match('/[\x00-\x1f\x7f]/', $record['stableId'])) throw new \UnexpectedValueException('Invalid stable ID.');
            if (($record['recordSha256'] ?? '') !== self::recordSha256($record)) throw new \UnexpectedValueException('Invalid record hash.');
            if (isset($ids[$record['stableId']])) throw new \UnexpectedValueException('Duplicate stable ID.');
            $ids[$record['stableId']] = true;
        }
        return $envelope;
    }
}
