<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Columns;

use Piwik\Plugins\EcommerceEnhanced\Dimension\OrderConversionDimension;

class OrderCoupon extends OrderConversionDimension
{
    protected $columnName = 'ee_coupon';
    protected $columnType = 'VARCHAR(255) NULL';
    protected $segmentName = 'eeOrderCoupon';
    protected $payloadKey = 'coupon';
    protected $nameSingular = 'EcommerceEnhanced_OrderCoupon';
    protected $acceptValues = 'Coupon code, for example SUMMER10';
}
