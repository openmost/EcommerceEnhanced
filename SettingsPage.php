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
 * Websites in the administration, not in the website form: they are only writable while this page loads them.
 */
class SettingsPage
{
    public const PREMIUM_SETTINGS_CLASS = 'Piwik\Plugins\EcommerceEnhancedPremium\MeasurableSettings';

    /**
     * @var bool
     */
    private static $editing = false;

    /**
     * Called at the end of init() by each settings class: hidden from the website form, writable on this page.
     */
    public static function applyVisibility(BaseMeasurableSettings $settings, int $idSite): void
    {
        $writable = self::$editing && !empty($idSite) && Piwik::isUserHasAdminAccess($idSite);
        foreach ($settings->getSettingsWritableByCurrentUser() as $setting) {
            $setting->setIsWritableByCurrentUser($writable);
        }
    }

    /**
     * @return BaseMeasurableSettings[] plugin name => settings, writable for a website administrator
     */
    public static function load(int $idSite): array
    {
        self::$editing = true;
        try {
            $sections = ['EcommerceEnhanced' => new MeasurableSettings($idSite)];
            if (Manager::getInstance()->isPluginActivated(PremiumTeasers::PREMIUM_PLUGIN) && class_exists(self::PREMIUM_SETTINGS_CLASS)) {
                $class = self::PREMIUM_SETTINGS_CLASS;
                $sections[PremiumTeasers::PREMIUM_PLUGIN] = new $class($idSite);
            }
        } finally {
            self::$editing = false;
        }

        return $sections;
    }
}
