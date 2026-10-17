<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced;

use Piwik\Piwik;
use Piwik\Plugins\EcommerceEnhanced\Tracker\Payload;
use Piwik\Settings\FieldConfig;
use Piwik\Settings\Measurable\MeasurableSetting;

class MeasurableSettings extends \Piwik\Settings\Measurable\MeasurableSettings
{
    public const COUPON_FULL = 'full';
    public const COUPON_HASHED = 'hashed';

    public MeasurableSetting $valueIncludesTaxAndShipping;
    public MeasurableSetting $eventsReportTypes;
    public MeasurableSetting $nonInteractionTypes;
    public MeasurableSetting $storeCouponCodes;
    public MeasurableSetting $itemCustomParams;
    public MeasurableSetting $itemCustomParamMeta;

    /**
     * Settings edited as the "Item" scope of the Custom Dimensions page, not on the settings page.
     */
    public const ITEM_DIMENSION_SETTINGS = ['item_custom_params', 'item_custom_param_meta'];

    protected function init()
    {
        $this->valueIncludesTaxAndShipping = $this->makeSetting('value_includes_tax_and_shipping', false, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = Piwik::translate('EcommerceEnhanced_SettingValueIncludesTaxAndShipping');
            $field->inlineHelp = Piwik::translate('EcommerceEnhanced_SettingValueIncludesTaxAndShippingHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });

        $eventNames = EventType::getNamesWithActions();

        $this->eventsReportTypes = $this->makeSetting('events_report_types', array_keys($eventNames), FieldConfig::TYPE_ARRAY, function (FieldConfig $field) use ($eventNames) {
            $field->title = Piwik::translate('EcommerceEnhanced_SettingEventsReportTypes');
            $field->inlineHelp = Piwik::translate('EcommerceEnhanced_SettingEventsReportTypesHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
            $field->availableValues = $eventNames;
        });

        $defaultNonInteraction = array_map([EventType::class, 'getName'], EventType::NON_INTERACTION_BY_DEFAULT);
        $this->nonInteractionTypes = $this->makeSetting('non_interaction_types', $defaultNonInteraction, FieldConfig::TYPE_ARRAY, function (FieldConfig $field) use ($eventNames) {
            $field->title = Piwik::translate('EcommerceEnhanced_SettingNonInteractionTypes');
            $field->inlineHelp = Piwik::translate('EcommerceEnhanced_SettingNonInteractionTypesHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
            $field->availableValues = $eventNames;
        });

        // Single use and gift codes can identify a person or be spent: a hash is stored unless the site opts in
        $this->storeCouponCodes = $this->makeSetting('store_coupon_codes', false, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = Piwik::translate('EcommerceEnhanced_SettingStoreCouponCodes');
            $field->inlineHelp = Piwik::translate('EcommerceEnhanced_SettingStoreCouponCodesHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
        });

        $this->itemCustomParams = $this->makeSetting('item_custom_params', [], FieldConfig::TYPE_ARRAY, function (FieldConfig $field) {
            $field->title = Piwik::translate('EcommerceEnhanced_SettingItemCustomParams');
            $field->inlineHelp = Piwik::translate('EcommerceEnhanced_SettingItemCustomParamsHelp', [Payload::MAX_CUSTOM_PARAMS]);
            $field->uiControl = FieldConfig::UI_CONTROL_FIELD_ARRAY;
            $arrayField = new FieldConfig\ArrayField(Piwik::translate('EcommerceEnhanced_SettingItemCustomParamName'), FieldConfig::UI_CONTROL_TEXT);
            $field->uiControlAttributes['field'] = $arrayField->toArray();
            $field->transform = function ($value) {
                $names = [];
                foreach ((array) $value as $name) {
                    $name = is_string($name) ? trim($name) : '';
                    if ($name !== '' && !in_array($name, $names, true)) {
                        $names[] = $name;
                    }
                }

                return $names;
            };
            $field->validate = function ($value) {
                $names = array_filter(array_map('trim', (array) $value), 'strlen');
                if (count($names) > Payload::MAX_CUSTOM_PARAMS) {
                    throw new \Exception(Piwik::translate('EcommerceEnhanced_SettingItemCustomParamsTooMany', [Payload::MAX_CUSTOM_PARAMS]));
                }

                foreach ($names as $name) {
                    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,39}$/', $name)) {
                        throw new \Exception(Piwik::translate('EcommerceEnhanced_SettingItemCustomParamsInvalid', [$name]));
                    }
                }
            };
        });

        // slot => {description, active}, JSON so the names list keeps the format the tracker cache reads
        $this->itemCustomParamMeta = $this->makeSetting('item_custom_param_meta', '{}', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('EcommerceEnhanced_SettingItemCustomParams');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
        });

        SettingsPage::applyVisibility($this, (int) $this->idSite);
    }

    /**
     * Every custom item parameter, active or not, as shown in the "Item" scope of Custom Dimensions.
     *
     * @return array<int, array{slot: int, name: string, description: string, active: bool}>
     */
    public function getItemDimensions(): array
    {
        $meta = json_decode((string) $this->itemCustomParamMeta->getValue(), true);
        $meta = is_array($meta) ? $meta : [];

        $dimensions = [];
        foreach (array_values((array) $this->itemCustomParams->getValue()) as $index => $name) {
            if ($index >= Payload::MAX_CUSTOM_PARAMS || !is_string($name) || $name === '') {
                continue;
            }

            $slot = $index + 1;
            $dimensions[] = [
                'slot' => $slot,
                'name' => $name,
                'description' => (string) ($meta[$slot]['description'] ?? ''),
                'active' => (bool) ($meta[$slot]['active'] ?? true),
            ];
        }

        return $dimensions;
    }

    /**
     * Custom item parameter names mapped to their storage slot (1 based). The slot is the position in the list,
     * so names must only be appended: reordering would give stored values another meaning.
     *
     * @return array<string, int>
     */
    public function getItemCustomParamSlots(): array
    {
        $slots = [];
        foreach ($this->getItemDimensions() as $dimension) {
            if ($dimension['active']) {
                $slots[$dimension['name']] = $dimension['slot'];
            }
        }

        return $slots;
    }
}
