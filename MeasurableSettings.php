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
    public const COUPON_NONE = 'none';

    public MeasurableSetting $valueIncludesTaxAndShipping;
    public MeasurableSetting $eventsReportTypes;
    public MeasurableSetting $nonInteractionTypes;
    public MeasurableSetting $couponStorage;
    public MeasurableSetting $itemCustomParams;

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

        $this->couponStorage = $this->makeSetting('coupon_storage', self::COUPON_FULL, FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('EcommerceEnhanced_SettingCouponStorage');
            $field->inlineHelp = Piwik::translate('EcommerceEnhanced_SettingCouponStorageHelp');
            $field->uiControl = FieldConfig::UI_CONTROL_SINGLE_SELECT;
            $field->availableValues = [
                self::COUPON_FULL => Piwik::translate('EcommerceEnhanced_SettingCouponStorageFull'),
                self::COUPON_HASHED => Piwik::translate('EcommerceEnhanced_SettingCouponStorageHashed'),
                self::COUPON_NONE => Piwik::translate('EcommerceEnhanced_SettingCouponStorageNone'),
            ];
            $field->validators[] = new \Piwik\Validators\WhitelistedValue([self::COUPON_FULL, self::COUPON_HASHED, self::COUPON_NONE]);
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

        SettingsPage::applyVisibility($this, (int) $this->idSite);
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
        foreach (array_values((array) $this->itemCustomParams->getValue()) as $index => $name) {
            if ($index < Payload::MAX_CUSTOM_PARAMS && is_string($name) && $name !== '') {
                $slots[$name] = $index + 1;
            }
        }

        return $slots;
    }
}
