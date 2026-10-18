<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced;

use Piwik\API\Request;
use Piwik\Piwik;
use Piwik\Plugins\EcommerceEnhanced\Dao\Schema;
use Piwik\Site;
use Piwik\Tracker\Cache;

/**
 * The custom item parameters (size, color, stock status...) are managed as a third "Item" scope of the Custom
 * Dimensions page, a Premium feature: without Premium the scope and its dimensions are shown locked, they can be seen
 * but not created nor edited. Each one has a dataLayer key, set when it is created, and a name displayed in
 * the reports, edited as the name of any Custom Dimension. The scope is added to what the page reads, its form posts to EcommerceEnhanced.addItemDimension
 * and updateItemDimension (see javascripts/itemDimensions.js), saved to the website settings: Custom Dimensions
 * and the plugins listening to it never see an item dimension. Only the page sees the scope: the other readers of the configured
 * dimensions (report menus, MCP...) call the API internally and get the core dimensions only.
 */
class ItemDimensions
{
    public const SCOPE = 'item';

    /**
     * Item dimensions get ids far above the auto incremented Custom Dimension ids: offset + slot.
     */
    public const ID_OFFSET = 900000;

    public static function isAvailable(): bool
    {
        return PremiumTeasers::isPremiumActivated();
    }


    public static function isItemDimensionId($idDimension): bool
    {
        return is_numeric($idDimension) && (int) $idDimension > self::ID_OFFSET && (int) $idDimension <= self::ID_OFFSET + Schema::MAX_CUSTOM_SLOTS;
    }

    /**
     * @param array<int, array<string, mixed>> $scopes
     * @param array{parameters?: array<string, mixed>} $info
     */
    public static function addScope(&$scopes, $info): void
    {
        $idSite = (int) ($info['parameters']['idSite'] ?? 0);
        if (!self::isForManagePage('CustomDimensions.getAvailableScopes', $idSite) || !is_array($scopes)) {
            return;
        }

        $used = count((new MeasurableSettings($idSite))->getItemDimensions());
        $scopes[] = [
            'value' => self::SCOPE,
            'name' => Piwik::translate('EcommerceEnhanced_ItemScope'),
            'numSlotsAvailable' => Schema::getCustomSlots(),
            'numSlotsUsed' => $used,
            'numSlotsLeft' => max(0, Schema::getCustomSlots() - $used),
            'supportsExtractions' => false,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $dimensions
     * @param array{parameters?: array<string, mixed>} $info
     */
    public static function addDimensions(&$dimensions, $info): void
    {
        $idSite = (int) ($info['parameters']['idSite'] ?? 0);
        if (!self::isForManagePage('CustomDimensions.getConfiguredCustomDimensions', $idSite) || !is_array($dimensions)) {
            return;
        }

        foreach ((new MeasurableSettings($idSite))->getItemDimensions() as $dimension) {
            $dimensions[] = [
                'idcustomdimension' => self::ID_OFFSET + $dimension['slot'],
                'idsite' => $idSite,
                'name' => $dimension['name'],
                'item_parameter' => $dimension['parameter'],
                'description' => $dimension['description'],
                'index' => $dimension['slot'],
                'scope' => self::SCOPE,
                'active' => $dimension['active'],
                'extractions' => [],
                'case_sensitive' => true,
            ];
        }
    }

    /**
     * @param string $parameter the dataLayer key, the name when empty and a valid key, as before names were free
     */
    public static function create(int $idSite, string $name, string $parameter, string $description, bool $active): int
    {
        Piwik::checkUserHasAdminAccess($idSite);
        self::checkIsAvailable();

        $settings = SettingsPage::loadItemDimensions($idSite);
        $parameters = array_column($settings->getItemDimensions(), 'parameter');
        if (count($parameters) >= Schema::getCustomSlots()) {
            throw new \Exception(Piwik::translate('EcommerceEnhanced_SettingItemCustomParamsTooMany', [Schema::getCustomSlots()]));
        }

        $parameter = self::validateParameter(trim($parameter) === '' ? $name : $parameter, $parameters);
        $parameters[] = $parameter;
        $slot = count($parameters);

        self::save($idSite, $settings, $parameters, $slot, self::validateName($name), $description, $active);

        /**
         * Triggered when an item dimension is added, so reports built on it can archive the past periods: the
         * values were not recorded before, but a renamed or reactivated slot may have some.
         *
         * @param int $idSite
         * @param int $slot storage slot of the dimension, 1 to 10
         */
        Piwik::postEvent('EcommerceEnhanced.itemDimensionAdded', [$idSite, $slot]);

        return self::ID_OFFSET + $slot;
    }

    /**
     * Renaming changes the name displayed only, the dataLayer key stays unless a new one is given. Either way the
     * slot is kept, so the values recorded before stay with the dimension, as for a Custom Dimension.
     *
     * @param string|null $description null keeps the current one
     * @param string|null $parameter null or empty keeps the current dataLayer key
     */
    public static function update(int $idSite, int $slot, string $name, $description, bool $active, $parameter = null): void
    {
        Piwik::checkUserHasAdminAccess($idSite);
        self::checkIsAvailable();

        $settings = SettingsPage::loadItemDimensions($idSite);
        $dimensions = $settings->getItemDimensions();
        $parameters = array_column($dimensions, 'parameter');
        if (!isset($parameters[$slot - 1])) {
            throw new \Exception(Piwik::translate('EcommerceEnhanced_ItemDimensionNotFound'));
        }

        if ($parameter !== null && trim((string) $parameter) !== '') {
            $others = $parameters;
            unset($others[$slot - 1]);
            $parameters[$slot - 1] = self::validateParameter((string) $parameter, array_values($others));
        }

        $description = $description === null ? $dimensions[$slot - 1]['description'] : (string) $description;

        self::save($idSite, $settings, $parameters, $slot, self::validateName($name), $description, $active);
    }

    /**
     * @param string[] $parameters dataLayer keys by slot
     */
    private static function save(int $idSite, MeasurableSettings $settings, array $parameters, int $slot, string $name, string $description, bool $active): void
    {
        $meta = json_decode((string) $settings->itemCustomParamMeta->getValue(), true);
        $meta = is_array($meta) ? $meta : [];
        $meta[$slot] = ['label' => $name, 'description' => mb_substr(trim($description), 0, 255), 'active' => $active];

        $settings->itemCustomParams->setValue($parameters);
        $settings->itemCustomParamMeta->setValue(json_encode($meta));
        $settings->save();

        Cache::deleteCacheWebsiteAttributes($idSite);
    }

    private static function validateName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new \Exception(Piwik::translate('EcommerceEnhanced_ItemDimensionNameInvalid'));
        }

        return $name;
    }

    /**
     * @param string[] $otherParameters
     */
    private static function validateParameter(string $parameter, array $otherParameters): string
    {
        $parameter = trim($parameter);
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,39}$/', $parameter)) {
            throw new \Exception(Piwik::translate('EcommerceEnhanced_SettingItemCustomParamsInvalid', [$parameter]));
        }

        if (in_array($parameter, $otherParameters, true)) {
            throw new \Exception(Piwik::translate('EcommerceEnhanced_ItemDimensionExists', [$parameter]));
        }

        return $parameter;
    }

    private static function checkIsAvailable(): void
    {
        if (!self::isAvailable()) {
            throw new \Exception(Piwik::translate('EcommerceEnhanced_PremiumDimensionInCustomReport'));
        }
    }

    /**
     * The scope is only added to the Custom Dimensions page requests, for websites with ecommerce.
     */
    private static function isForManagePage(string $method, int $idSite): bool
    {
        return !empty($idSite)
            && Request::isCurrentApiRequestTheRootApiRequest()
            && Request::getRootApiRequestMethod() === $method
            && (new Site($idSite))->isEcommerceEnabled();
    }

}
