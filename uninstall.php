<?php
/**
 * Uninstall boundary.
 *
 * File 22 does not delete native content, companion-plugin records, secure
 * media, patient-consent evidence, or user data automatically.
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Intentionally non-destructive. A future explicit cleanup tool must require
// administrator authorization, a verified backup, a dry-run report, and a
// separate confirmation for File 22-owned temporary orchestration records.
