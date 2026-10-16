<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Columns;

use Piwik\Plugins\EcommerceEnhanced\Dimension\OrderConversionDimension;

class OrderCurrency extends OrderConversionDimension
{
    protected $columnName = 'ee_currency';
    protected $columnType = 'CHAR(3) NULL';
    protected $segmentName = 'eeOrderCurrency';
    protected $payloadKey = 'currency';
    protected $nameSingular = 'EcommerceEnhanced_OrderCurrency';
    protected $acceptValues = 'ISO 4217 currency code, for example EUR, USD';
    protected $onCart = true;
}
