<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Tracker;

use Piwik\Plugins\EcommerceEnhanced\EventType;
use Piwik\Plugins\EcommerceEnhanced\MeasurableSettings;
use Piwik\Tracker\Cache;

/**
 * Site settings as the tracker reads them, from the tracker cache so a tracking request never queries the
 * settings table.
 */
class SiteConfig
{
    public const CACHE_KEY = 'EcommerceEnhanced';

    /**
     * @var array{value_includes_tax_and_shipping: bool, events_report_types: int[], non_interaction_types: int[], coupon_storage: string, item_custom_params: array<string, int>}
     */
    private $config;

    private function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function forSite(int $idSite): self
    {
        $attributes = Cache::getCacheWebsiteAttributes($idSite);
        if (isset($attributes[self::CACHE_KEY]) && is_array($attributes[self::CACHE_KEY])) {
            return new self($attributes[self::CACHE_KEY]);
        }

        return new self(self::buildCacheEntry(new MeasurableSettings($idSite)));
    }

    public static function buildCacheEntry(MeasurableSettings $settings): array
    {
        $toTypes = function ($names): array {
            return array_values(array_filter(array_map(function ($name) {
                return is_string($name) ? EventType::fromName($name) : null;
            }, (array) $names)));
        };

        return [
            'value_includes_tax_and_shipping' => (bool) $settings->valueIncludesTaxAndShipping->getValue(),
            'events_report_types' => $toTypes($settings->eventsReportTypes->getValue()),
            'non_interaction_types' => $toTypes($settings->nonInteractionTypes->getValue()),
            'coupon_storage' => $settings->storeCouponCodes->getValue() ? MeasurableSettings::COUPON_FULL : MeasurableSettings::COUPON_HASHED,
            'item_custom_params' => $settings->getItemCustomParamSlots(),
        ];
    }

    public function valueIncludesTaxAndShipping(): bool
    {
        return !empty($this->config['value_includes_tax_and_shipping']);
    }

    public function isInEventsReport(int $type): bool
    {
        return in_array($type, $this->config['events_report_types'] ?? [], true);
    }

    public function isNonInteraction(int $type): bool
    {
        return in_array($type, $this->config['non_interaction_types'] ?? [], true);
    }

    public function getCouponStorage(): string
    {
        return $this->config['coupon_storage'] ?? MeasurableSettings::COUPON_HASHED;
    }

    /**
     * @return array<string, int>
     */
    public function getItemCustomParamSlots(): array
    {
        return $this->config['item_custom_params'] ?? [];
    }
}
