<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Dimension;

use Piwik\Plugin\Dimension\ConversionDimension;
use Piwik\Plugins\EcommerceEnhanced\Tracker\RequestProcessor;
use Piwik\Tracker\GoalManager;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;

/**
 * An order level GA4 field stored next to the revenue in log_conversion, so "revenue by X" never counts an order
 * twice. Columns are NULLable without default: adding them is a metadata only change on recent MySQL/MariaDB.
 */
abstract class OrderConversionDimension extends ConversionDimension
{
    protected $category = 'Goals_Ecommerce';
    protected $type = self::TYPE_TEXT;

    /**
     * Key of the normalised payload event (see Tracker\Payload).
     *
     * @var string
     */
    protected $payloadKey;

    /**
     * Whether the value is also kept on the cart row (idgoal -1).
     *
     * @var bool
     */
    protected $onCart = false;

    public function __construct()
    {
        if ($this->type === self::TYPE_TEXT) {
            $this->suggestedValuesCallback = SuggestedValues::fromColumn('log_conversion', $this->columnName);
        }
    }

    /**
     * Uninstalling the plugin keeps its data: the column stays in log_conversion, a later install reuses it.
     */
    public function uninstall()
    {
    }

    public function onEcommerceOrderConversion(Request $request, Visitor $visitor, $action, GoalManager $goalManager)
    {
        return $this->getValue($request);
    }

    public function onEcommerceCartUpdateConversion(Request $request, Visitor $visitor, $action, GoalManager $goalManager)
    {
        return $this->onCart ? $this->getValue($request) : false;
    }

    /**
     * False leaves the column untouched, NULL would erase a value set by an earlier request.
     *
     * @return mixed
     */
    private function getValue(Request $request)
    {
        $event = RequestProcessor::getOrderEvent($request);
        if ($event === null || !isset($event[$this->payloadKey])) {
            return false;
        }

        return $event[$this->payloadKey];
    }
}
