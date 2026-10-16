<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Columns;

use Piwik\Plugins\EcommerceEnhanced\Dimension\OrderConversionDimension;

class OrderAffiliation extends OrderConversionDimension
{
    protected $columnName = 'ee_affiliation';
    protected $columnType = 'VARCHAR(100) NULL';
    protected $segmentName = 'eeOrderAffiliation';
    protected $payloadKey = 'affiliation';
    protected $nameSingular = 'EcommerceEnhanced_OrderAffiliation';
    protected $acceptValues = 'Store or marketplace name';
}
