<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Columns;

use Piwik\Plugins\EcommerceEnhanced\Dimension\OrderConversionDimension;

class OrderShippingTier extends OrderConversionDimension
{
    protected $columnName = 'ee_shipping_tier';
    protected $columnType = 'VARCHAR(100) NULL';
    protected $segmentName = 'eeOrderShippingTier';
    protected $payloadKey = 'shipping_tier';
    protected $nameSingular = 'EcommerceEnhanced_OrderShippingTier';
    protected $acceptValues = 'Shipping tier, for example Ground, Next day';
}
