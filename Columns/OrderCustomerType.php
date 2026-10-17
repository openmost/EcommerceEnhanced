<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Columns;

use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugins\EcommerceEnhanced\Dimension\OrderConversionDimension;
use Piwik\Plugins\EcommerceEnhanced\Tracker\Payload;

/**
 * GA4 customer_type (or the Google Ads new_customer flag), as the shop knows it. It differs from the Matomo
 * returning visitor: a known customer can come back from a new device.
 */
class OrderCustomerType extends OrderConversionDimension
{
    protected $columnName = 'ee_customer_type';
    protected $columnType = 'TINYINT UNSIGNED NULL';
    protected $segmentName = 'eeOrderCustomerType';
    protected $payloadKey = 'customer_type';
    protected $nameSingular = 'EcommerceEnhanced_OrderCustomerType';
    protected $type = self::TYPE_ENUM;
    protected $acceptValues = 'new, returning, or empty for orders without customer type';

    public function __construct()
    {
        parent::__construct();

        $this->sqlFilterValue = function ($value) {
            if (is_numeric($value)) {
                return (int) $value;
            }

            return $value === 'returning' ? Payload::CUSTOMER_TYPE_RETURNING : Payload::CUSTOMER_TYPE_NEW;
        };
    }

    public function getEnumColumnValues()
    {
        return [
            Payload::CUSTOMER_TYPE_NEW => 'new',
            Payload::CUSTOMER_TYPE_RETURNING => 'returning',
        ];
    }

    public function formatValue($value, $idSite, Formatter $formatter)
    {
        if ((int) $value === Payload::CUSTOMER_TYPE_NEW) {
            return Piwik::translate('EcommerceEnhanced_CustomerTypeNew');
        }

        if ((int) $value === Payload::CUSTOMER_TYPE_RETURNING) {
            return Piwik::translate('EcommerceEnhanced_CustomerTypeReturning');
        }

        return $value;
    }
}
