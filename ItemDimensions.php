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
use Piwik\Plugins\EcommerceEnhanced\Tracker\Payload;
use Piwik\Site;
use Piwik\Tracker\Cache;

/**
 * The custom item parameters (size, color, stock status...) are managed as a third "Item" scope of the Custom
 * Dimensions page. The scope is added to what the page reads, its form posts to EcommerceEnhanced.addItemDimension
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

    private const PERSONAL_KEY = '/email|phone|sha256|_hash$|hash_|first_?name|last_?name|street|postal|zip|address|birth|customer/i';

    public static function isItemDimensionId($idDimension): bool
    {
        return is_numeric($idDimension) && (int) $idDimension > self::ID_OFFSET && (int) $idDimension <= self::ID_OFFSET + Payload::MAX_CUSTOM_PARAMS;
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
            'numSlotsAvailable' => Payload::MAX_CUSTOM_PARAMS,
            'numSlotsUsed' => $used,
            'numSlotsLeft' => Payload::MAX_CUSTOM_PARAMS - $used,
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
                'description' => $dimension['description'],
                'index' => $dimension['slot'],
                'scope' => self::SCOPE,
                'active' => $dimension['active'],
                'extractions' => [],
                'case_sensitive' => true,
            ];
        }
    }

    public static function create(int $idSite, string $name, string $description, bool $active): int
    {
        Piwik::checkUserHasAdminAccess($idSite);

        $settings = SettingsPage::loadItemDimensions($idSite);
        $names = array_column($settings->getItemDimensions(), 'name');
        if (count($names) >= Payload::MAX_CUSTOM_PARAMS) {
            throw new \Exception(Piwik::translate('EcommerceEnhanced_SettingItemCustomParamsTooMany', [Payload::MAX_CUSTOM_PARAMS]));
        }

        $name = self::validateName($name, $names);
        $names[] = $name;
        $slot = count($names);

        self::save($idSite, $settings, $names, $slot, $description, $active);

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
     * Renaming keeps the slot, so the values recorded before stay with the dimension, as for a Custom Dimension.
     *
     * @param string|null $description null keeps the current one
     */
    public static function update(int $idSite, int $slot, string $name, $description, bool $active): void
    {
        Piwik::checkUserHasAdminAccess($idSite);

        $settings = SettingsPage::loadItemDimensions($idSite);
        $dimensions = $settings->getItemDimensions();
        $names = array_column($dimensions, 'name');
        if (!isset($names[$slot - 1])) {
            throw new \Exception(Piwik::translate('EcommerceEnhanced_ItemDimensionNotFound'));
        }

        $others = $names;
        unset($others[$slot - 1]);
        $names[$slot - 1] = self::validateName($name, array_values($others));
        $description = $description === null ? $dimensions[$slot - 1]['description'] : (string) $description;

        self::save($idSite, $settings, $names, $slot, $description, $active);
    }

    /**
     * @param string[] $names
     */
    private static function save(int $idSite, MeasurableSettings $settings, array $names, int $slot, string $description, bool $active): void
    {
        $meta = json_decode((string) $settings->itemCustomParamMeta->getValue(), true);
        $meta = is_array($meta) ? $meta : [];
        $meta[$slot] = ['description' => mb_substr(trim($description), 0, 255), 'active' => $active];

        $settings->itemCustomParams->setValue($names);
        $settings->itemCustomParamMeta->setValue(json_encode($meta));
        $settings->save();

        Cache::deleteCacheWebsiteAttributes($idSite);
    }

    /**
     * @param string[] $otherNames
     */
    private static function validateName(string $name, array $otherNames): string
    {
        $name = trim($name);
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,39}$/', $name)) {
            throw new \Exception(Piwik::translate('EcommerceEnhanced_SettingItemCustomParamsInvalid', [$name]));
        }

        // The tracker never reads such keys, see PERSONAL_KEY in tracker.js: the dimension would stay empty
        if (preg_match(self::PERSONAL_KEY, $name)) {
            throw new \Exception(Piwik::translate('EcommerceEnhanced_ItemDimensionPersonal', [$name]));
        }

        if (in_array($name, $otherNames, true)) {
            throw new \Exception(Piwik::translate('EcommerceEnhanced_ItemDimensionExists', [$name]));
        }

        return $name;
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
