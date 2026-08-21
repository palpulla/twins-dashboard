<?php
declare(strict_types=1);

// Email-capture popup kill switch (POPUP_ENABLED). Flip the single define below
// to false to disable the popup sitewide in one line; a host may also define
// POPUP_ENABLED in wp-config.php before this file loads to switch it without a
// package deploy. Default is true on staging (Stage-1 spec,
// docs/superpowers/specs/2026-08-21-path-to-production-design.md).
if (!defined('POPUP_ENABLED')) {
    define('POPUP_ENABLED', true);
}

return ['enabled' => (bool) POPUP_ENABLED];
