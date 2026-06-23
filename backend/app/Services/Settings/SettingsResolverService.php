<?php

namespace App\Services\Settings;

/**
 * Backward-compatible namespace bridge.
 *
 * WHY:
 * Some modules now reference resolver via `App\Services\Settings\...` while
 * the original implementation lives in `App\Services\SettingsResolverService`.
 * This bridge keeps both namespaces working without rewriting resolver logic.
 */
class SettingsResolverService extends \App\Services\SettingsResolverService
{
}

