<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Columns;

use Piwik\Plugins\EcommerceEnhanced\Dimension\OrderConversionDimension;

class OrderPaymentType extends OrderConversionDimension
{
    protected $columnName = 'ee_payment_type';
    protected $columnType = 'VARCHAR(100) NULL';
    protected $segmentName = 'eeOrderPaymentType';
    protected $payloadKey = 'payment_type';
    protected $nameSingular = 'EcommerceEnhanced_OrderPaymentType';
    protected $acceptValues = 'Payment type, for example Credit Card, PayPal';
}
