<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Notification;
use Piwik\Piwik;
use Piwik\Plugins\EcommerceEnhanced\Dao\Schema;
use Piwik\Plugins\EcommerceEnhanced\Tracker\SiteConfig;
use Piwik\Tracker\Cache;

class EcommerceEnhanced extends \Piwik\Plugin
{
    public function registerEvents()
    {
        return [
            'Db.getTablesInstalled' => 'getTablesInstalled',
            'Db.getActionReferenceColumnsByTable' => 'addActionReferenceColumnsByTable',
            'Tracker.Cache.getSiteAttributes' => 'addSiteAttributes',
            'MeasurableSettings.updated' => 'onMeasurableSettingsUpdated',
            'TagManager.filterVariables' => 'replaceMatomoConfigurationVariable',
        ];
    }

    public function install()
    {
        (new Schema())->install();
    }

    public function uninstall()
    {
        (new Schema())->uninstall();
    }

    /**
     * While the plugin is deactivated its tables are no longer known to Matomo, so GDPR deletion and the raw
     * data purge skip them. Deactivating is allowed, the administrator is told what it means.
     */
    public function deactivate()
    {
        $notification = new Notification(Piwik::translate('EcommerceEnhanced_DeactivatedWarning'));
        $notification->context = Notification::CONTEXT_WARNING;
        $notification->type = Notification::TYPE_PERSISTENT;
        $notification->flags = Notification::FLAG_CLEAR;
        Notification\Manager::notify('EcommerceEnhanced_Deactivated', $notification);
    }

    /**
     * @param string[] $allTablesInstalled
     */
    public function getTablesInstalled(&$allTablesInstalled)
    {
        foreach (Schema::getTables() as $table) {
            $allTablesInstalled[] = Common::prefixTable($table);
        }
    }

    /**
     * Without this, the purge of unused log_action rows would delete SKUs, names and categories only referenced
     * by the plugin tables.
     *
     * @param array<string, string[]> $result
     */
    public function addActionReferenceColumnsByTable(&$result)
    {
        $result[Schema::TABLE_ORDER_ITEM] = Schema::ITEM_ACTION_COLUMNS;
        $result[Schema::TABLE_EVENT_ITEM] = Schema::ITEM_ACTION_COLUMNS;
    }

    public function addSiteAttributes(&$content, $idSite)
    {
        $content[SiteConfig::CACHE_KEY] = SiteConfig::buildCacheEntry(new MeasurableSettings((int) $idSite));
    }

    /**
     * Adds the "read the GA4 ecommerce dataLayer" option to the Matomo Configuration variable of Tag Manager.
     *
     * @param \Piwik\Plugins\TagManager\Template\Variable\BaseVariable[] $variables
     */
    public function replaceMatomoConfigurationVariable(&$variables)
    {
        foreach ($variables as $index => $variable) {
            if ($variable->getId() === TagManager\MatomoConfigurationVariable::ID && !$variable instanceof TagManager\MatomoConfigurationVariable) {
                $variables[$index] = StaticContainer::get(TagManager\MatomoConfigurationVariable::class);
            }
        }
    }

    public function onMeasurableSettingsUpdated($settings, $idSite)
    {
        if ($settings instanceof MeasurableSettings && !empty($idSite)) {
            Cache::deleteCacheWebsiteAttributes($idSite);
        }
    }
}
