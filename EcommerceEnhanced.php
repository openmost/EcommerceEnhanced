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
use Piwik\Plugins\EcommerceEnhanced\Dimension\PremiumItemDimension;
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
            'Widget.addWidgetConfigs' => 'addPremiumTeasers',
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'AssetManager.getJavaScriptFiles' => 'getJavaScriptFiles',
            'API.CustomDimensions.getAvailableScopes.end' => 'addItemDimensionScope',
            'API.CustomDimensions.getConfiguredCustomDimensions.end' => 'addItemDimensions',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
            'Template.beforeContent' => 'renderOpenmostCommunication',
            'Template.afterEventsReport' => 'renderOpenmostCommunicationAfterEvents',
            'Widget.filterWidgets' => 'addOpenmostCommunicationWidgets',
            'Dimension.addDimensions' => 'addPremiumItemDimensions',
            'Console.filterCommands' => 'replaceAddCustomDimensionCommand',
            'Template.jsGlobalVariables' => 'addJsGlobalVariables',
            'API.CustomReports.addCustomReport' => 'refusePremiumDimensionsInCustomReport',
            'API.CustomReports.updateCustomReport' => 'refusePremiumDimensionsInCustomReport',
        ];
    }

    public function install()
    {
        (new Schema())->install();
    }

    /**
     * Uninstalling keeps the data: the tables and the log_conversion columns stay, a later install reuses them.
     * They are only dropped by hand, see the README.
     */
    public function uninstall()
    {
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

    /**
     * While Premium is not activated, its item dimensions are listed marked "Premium" in the segment editor and in
     * Custom Reports.
     *
     * @param \Piwik\Columns\Dimension[] $instances
     */
    public function addPremiumItemDimensions(&$instances)
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');
        if (empty($idSite) || PremiumTeasers::isPremiumActivated()) {
            return;
        }

        foreach ((new MeasurableSettings($idSite))->getItemDimensions() as $dimension) {
            if ($dimension['active']) {
                $instances[] = new PremiumItemDimension($dimension['slot'], $dimension['name']);
            }
        }
    }

    /**
     * Whether the Item scope of the Custom Dimensions page is locked, Premium not being activated.
     *
     * @param string $out
     */
    public function addJsGlobalVariables(&$out)
    {
        $out .= 'piwik.ecommerceEnhancedItemDimensionsLocked = ' . (ItemDimensions::isAvailable() ? 'false' : 'true') . ";\n";
        $out .= 'piwik.ecommerceEnhancedPremiumUrl = ' . json_encode(PremiumTeasers::URL) . ";\n";
    }

    /**
     * customdimensions:add-custom-dimension also takes the "item" scope: our subclass replaces the command of
     * Custom Dimensions, and is dropped when Custom Dimensions is not there, as its Item scope page is not either.
     *
     * @param string[] $commands
     */
    public function replaceAddCustomDimensionCommand(&$commands)
    {
        $core = array_search('Piwik\\Plugins\\CustomDimensions\\Commands\\AddCustomDimension', $commands, true);
        $ours = array_search(Commands\AddCustomDimension::class, $commands, true);
        if ($ours === false) {
            return;
        }

        if ($core === false) {
            unset($commands[$ours]);
            return;
        }

        unset($commands[$core]);
    }

    /**
     * A custom report cannot be saved with a Premium item dimension while Premium is not activated.
     *
     * @param array<string, mixed> $parameters
     */
    public function refusePremiumDimensionsInCustomReport(&$parameters)
    {
        if (PremiumTeasers::isPremiumActivated()) {
            return;
        }

        foreach ((array) ($parameters['dimensionIds'] ?? []) as $dimensionId) {
            if (is_string($dimensionId) && strpos($dimensionId, PremiumItemDimension::ID_PREFIX) === 0) {
                throw new \Exception(Piwik::translate('EcommerceEnhanced_PremiumDimensionInCustomReport'));
            }
        }
    }

    /**
     * @param \Piwik\Widget\WidgetConfig[] $configs
     */
    public function addPremiumTeasers(&$configs)
    {
        if (!PremiumTeasers::isPremiumActivated()) {
            $configs = array_merge($configs, PremiumTeasers::getWidgetConfigs());
        }
    }

    /**
     * @param string[] $stylesheets
     */
    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = 'plugins/EcommerceEnhanced/stylesheets/premium.less';
    }

    /**
     * @param string[] $jsFiles
     */
    public function getJavaScriptFiles(&$jsFiles)
    {
        $jsFiles[] = 'plugins/EcommerceEnhanced/javascripts/itemDimensions.js';
    }

    /**
     * @param string[] $translationKeys
     */
    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = 'EcommerceEnhanced_Customers';
        $translationKeys[] = 'EcommerceEnhanced_SettingsPageTitle';
        $translationKeys[] = 'EcommerceEnhanced_SettingsPageIntro';
        $translationKeys[] = 'EcommerceEnhanced_SettingsSectionFree';
        $translationKeys[] = 'EcommerceEnhanced_SettingsSectionPremium';
        $translationKeys[] = 'EcommerceEnhanced_SettingsSaved';
        $translationKeys[] = 'EcommerceEnhanced_PremiumLinkText';
        $translationKeys[] = 'EcommerceEnhanced_PremiumText';
        $translationKeys[] = 'EcommerceEnhanced_ItemDimensionHowToTitle';
        $translationKeys[] = 'EcommerceEnhanced_FunnelsTitle';
        $translationKeys[] = 'EcommerceEnhanced_FunnelsIntro';
        $translationKeys[] = 'EcommerceEnhanced_FunnelExists';
        $translationKeys[] = 'EcommerceEnhanced_FunnelMissing';
        $translationKeys[] = 'EcommerceEnhanced_FunnelsCreate';
        $translationKeys[] = 'EcommerceEnhanced_FunnelsCreated';
        $translationKeys[] = 'EcommerceEnhanced_ItemDimensionCreateTitle';
        $translationKeys[] = 'EcommerceEnhanced_ItemDimensionHowTo';
        $translationKeys[] = 'EcommerceEnhanced_ItemDimensionHowToExample';
        $translationKeys[] = 'EcommerceEnhanced_ItemDimensionKey';
        $translationKeys[] = 'EcommerceEnhanced_ItemDimensionsLocked';
        $translationKeys[] = 'EcommerceEnhanced_PremiumLinkText';
        $translationKeys[] = 'EcommerceEnhanced_EventStepsTitle';
        $translationKeys[] = 'Events_EventCategory';
        $translationKeys[] = 'Events_EventAction';
        $translationKeys[] = 'Events_EventName';
        $translationKeys[] = 'EcommerceEnhanced_EventStepsTracked';
        $translationKeys[] = 'EcommerceEnhanced_EventStepsNonInteraction';
        $translationKeys[] = 'EcommerceEnhanced_SettingEventsReportTypesHelp';
        $translationKeys[] = 'EcommerceEnhanced_SettingNonInteractionTypesHelp';
        $translationKeys[] = 'EcommerceEnhanced_ItemDimensionKeyColumn';
        $translationKeys[] = 'EcommerceEnhanced_ItemDimensionKeyHelp';
        $translationKeys[] = 'EcommerceEnhanced_ItemDimensionNamePlaceholder';
        $translationKeys[] = 'EcommerceEnhanced_ItemDimensionDescriptionPlaceholder';
        $translationKeys[] = 'CustomDimensions_ScopeTitleItem';
        $translationKeys[] = 'CustomDimensions_ScopeDescriptionItem';
        $translationKeys[] = 'CustomDimensions_ScopeDescriptionItemMoreInfo';
    }

    public function onMeasurableSettingsUpdated($settings, $idSite)
    {
        if ($settings instanceof MeasurableSettings && !empty($idSite)) {
            Cache::deleteCacheWebsiteAttributes($idSite);
        }
    }

    public function renderOpenmostCommunication(&$out, $layout, $module = '', $action = '')
    {
        OpenmostCommunication::beforeContent($out, (string) $layout, (string) $module, (string) $action, $this->getPluginName());
    }

    public function addOpenmostCommunicationWidgets($list)
    {
        OpenmostCommunication::filterWidgets($list, $this->getPluginName());
    }

    public function renderOpenmostCommunicationAfterEvents(&$out, $dataTable = null)
    {
        OpenmostCommunication::afterEventsReport($out, $this->getPluginName());
    }

    public function addItemDimensionScope(&$scopes, $info)
    {
        ItemDimensions::addScope($scopes, $info);
    }

    public function addItemDimensions(&$dimensions, $info)
    {
        ItemDimensions::addDimensions($dimensions, $info);
    }

}
