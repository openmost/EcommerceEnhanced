<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced;

use Piwik\Piwik;
use Piwik\Plugin\Manager;
use Piwik\Settings\Measurable\MeasurableSettings as BaseMeasurableSettings;

/**
 * The website settings of Ecommerce Enhanced, and of Premium when activated, are edited on their own page under
 * Websites in the administration, the custom item parameters as the "Item" scope of Custom Dimensions. None of them
 * appears in the website form: a setting is only writable while one of these two pages loads it.
 */
class SettingsPage
{
    public const PREMIUM_SETTINGS_CLASS = 'Piwik\Plugins\EcommerceEnhancedPremium\MeasurableSettings';

    private const MODE_PAGE = 'page';
    private const MODE_ITEM_DIMENSIONS = 'item_dimensions';

    /**
     * @var string|null
     */
    private static $mode = null;

    /**
     * Called at the end of init() by each settings class.
     */
    public static function applyVisibility(BaseMeasurableSettings $settings, int $idSite): void
    {
        $isAdmin = self::$mode !== null && !empty($idSite) && Piwik::isUserHasAdminAccess($idSite);
        foreach ($settings->getSettingsWritableByCurrentUser() as $setting) {
            $isItemDimension = in_array($setting->getName(), MeasurableSettings::ITEM_DIMENSION_SETTINGS, true);
            $setting->setIsWritableByCurrentUser($isAdmin && self::$mode === ($isItemDimension ? self::MODE_ITEM_DIMENSIONS : self::MODE_PAGE));
        }
    }

    /**
     * @return BaseMeasurableSettings[] plugin name => settings, writable for a website administrator
     */
    public static function load(int $idSite): array
    {
        return self::withMode(self::MODE_PAGE, function () use ($idSite) {
            $sections = ['EcommerceEnhanced' => new MeasurableSettings($idSite)];
            if (Manager::getInstance()->isPluginActivated(PremiumTeasers::PREMIUM_PLUGIN) && class_exists(self::PREMIUM_SETTINGS_CLASS)) {
                $class = self::PREMIUM_SETTINGS_CLASS;
                $sections[PremiumTeasers::PREMIUM_PLUGIN] = new $class($idSite);
            }

            return $sections;
        });
    }

    /**
     * The settings with only the custom item parameters writable, for the "Item" scope of Custom Dimensions.
     */
    public static function loadItemDimensions(int $idSite): MeasurableSettings
    {
        return self::withMode(self::MODE_ITEM_DIMENSIONS, function () use ($idSite) {
            return new MeasurableSettings($idSite);
        });
    }

    /**
     * @return mixed
     */
    private static function withMode(string $mode, callable $build)
    {
        self::$mode = $mode;
        try {
            return $build();
        } finally {
            self::$mode = null;
        }
    }
}
