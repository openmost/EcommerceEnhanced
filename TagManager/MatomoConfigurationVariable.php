<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\TagManager;

use Piwik\Piwik;
use Piwik\Plugins\TagManager\Context\WebContext;
use Piwik\Plugins\TagManager\Template\Variable\MatomoConfigurationVariable as CoreMatomoConfigurationVariable;
use Piwik\Settings\FieldConfig;

/**
 * The Tag Manager "Matomo Configuration" variable with an option to read the GA4 ecommerce dataLayer. It keeps the
 * id of the core variable, so existing containers keep working.
 *
 * Other plugins extend the same variable (eg ProfileGravatar), so it is never replaced: each plugin wraps the
 * variable it finds, the core one or one already extended, and adds its parameter and its JavaScript to it.
 */
class MatomoConfigurationVariable extends CoreMatomoConfigurationVariable
{
    public const PARAMETER = 'enableEcommerceEnhanced';

    /**
     * @var CoreMatomoConfigurationVariable
     */
    private $innerVariable;

    public function __construct(?CoreMatomoConfigurationVariable $innerVariable = null)
    {
        $this->innerVariable = $innerVariable ?: new CoreMatomoConfigurationVariable();
    }

    /**
     * Wraps the "Matomo Configuration" variable of the list, unless this plugin already extends it
     *
     * @param \Piwik\Plugins\TagManager\Template\Variable\BaseVariable[] $variables
     */
    public static function extendVariables(array &$variables): void
    {
        foreach ($variables as $index => $variable) {
            if ($variable instanceof CoreMatomoConfigurationVariable && !self::isExtendedBy($variable)) {
                $variables[$index] = new self($variable);
            }
        }
    }

    public function getInnerVariable(): CoreMatomoConfigurationVariable
    {
        return $this->innerVariable;
    }

    public function getParameters()
    {
        $parameters = $this->innerVariable->getParameters();

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

    /**
     * The template of the wrapped variable, whose config is then passed to the extension of this plugin
     * (MatomoConfigurationVariable.web.js, next to this file)
     */
    public function loadTemplate($context, $entity)
    {
        $innerTemplate = $this->innerVariable->loadTemplate(...func_get_args());
        if ($context !== WebContext::ID || empty($innerTemplate)) {
            return $innerTemplate;
        }

        $extension = parent::loadTemplate($context, $entity);
        if (empty($extension)) {
            return $innerTemplate;
        }

        return '(function () {'
            . "\nvar Inner = " . $innerTemplate . "\n;"
            . "\nvar extend = " . $extension . "\n;"
            . "\nreturn function (parameters, TagManager) {"
            . ' Inner.call(this, parameters, TagManager);'
            . ' var get = this.get;'
            . ' this.get = function () { var config = get.call(this); extend(config, parameters, TagManager); return config; };'
            . ' };'
            . "\n})()";
    }

    private static function isExtendedBy(CoreMatomoConfigurationVariable $variable): bool
    {
        while ($variable !== null) {
            if ($variable instanceof self) {
                return true;
            }
            $variable = method_exists($variable, 'getInnerVariable') ? $variable->getInnerVariable() : null;
        }

        return false;
    }
}
