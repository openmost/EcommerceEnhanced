<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\TagManager;

use Piwik\Piwik;
use Piwik\Plugins\TagManager\Template\Variable\MatomoConfigurationVariable as CoreMatomoConfigurationVariable;
use Piwik\Settings\FieldConfig;

/**
 * The Tag Manager "Matomo Configuration" variable with an option to read the GA4 ecommerce dataLayer. It keeps the
 * id of the core variable, so existing containers keep working, and replaces it through TagManager.filterVariables.
 * Its JavaScript template is the core one plus the option, it lives next to this file.
 */
class MatomoConfigurationVariable extends CoreMatomoConfigurationVariable
{
    public const PARAMETER = 'enableEcommerceEnhanced';

    public function getParameters()
    {
        $parameters = parent::getParameters();

        $option = $this->makeSetting(self::PARAMETER, false, FieldConfig::TYPE_BOOL, function (FieldConfig $field) {
            $field->title = Piwik::translate('EcommerceEnhanced_TagManagerOptionTitle');
            $field->description = Piwik::translate('EcommerceEnhanced_TagManagerOptionDescription');
        });

        $position = count($parameters);
        foreach ($parameters as $index => $parameter) {
            if ($parameter->getName() === 'enableLinkTracking') {
                $position = $index + 1;
            }
        }

        array_splice($parameters, $position, 0, [$option]);

        return $parameters;
    }
}
