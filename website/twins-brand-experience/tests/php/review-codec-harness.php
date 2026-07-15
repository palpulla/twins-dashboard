<?php
declare(strict_types=1);

if (count($argv) !== 5) {
    fwrite(STDERR, "usage: review-codec-harness.php <bootstrap> <fixture> <scenario> <now>\n");
    exit(1);
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    require $argv[1];
    $json = file_get_contents($argv[2]);
    if ($json === false) throw new RuntimeException('fixture could not be read');
    $envelope = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    $now = new DateTimeImmutable($argv[4], new DateTimeZone('UTC'));

    try {
        $verified = Twins\BrandExperience\ReviewCodec::verifyCollection($envelope, $now);
        if ($argv[3] !== 'valid') throw new RuntimeException('invalid review fixture was accepted');
        if ($verified !== $envelope) throw new RuntimeException('verified envelope was altered');
        $first = $verified['records'][0];
        if (Twins\BrandExperience\ReviewCodec::recordSha256($first) !== $first['recordSha256']) {
            throw new RuntimeException('record hash vector drifted');
        }
        $providerVector = ['providerId' => 'provider-fixed-id'];
        if (Twins\BrandExperience\ReviewCodec::stableId($providerVector) !== 'provider-fixed-id') {
            throw new RuntimeException('provider stable ID was not preserved');
        }
        $fallbackVector = [
            'author' => 'José Example',
            'rating' => 5,
            'publishedDate' => '2026-06-30',
            'text' => 'Exact UTF-8 — review text.',
        ];
        if (Twins\BrandExperience\ReviewCodec::stableId($fallbackVector) !== 'b657807000b2e01841f84d9a31dc11a9c5bfed2c7d71d63e695ed339a8749609') {
            throw new RuntimeException('fallback stable ID vector drifted');
        }
        echo 'review-codec-ok';
    } catch (UnexpectedValueException $expected) {
        if ($argv[3] === 'valid') throw $expected;
        echo 'review-codec-rejected';
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
