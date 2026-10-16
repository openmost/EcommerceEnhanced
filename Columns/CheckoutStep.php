<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Columns;

use Piwik\Plugins\EcommerceEnhanced\Dao\Schema;
use Piwik\Plugins\EcommerceEnhanced\Dimension\OwnTableDimension;

class CheckoutStep extends OwnTableDimension
{
    protected $dbTableName = Schema::TABLE_EVENT;
    protected $columnName = 'checkout_step';
    protected $segmentName = 'eeCheckoutStep';
    protected $type = self::TYPE_NUMBER;
    protected $nameSingular = 'EcommerceEnhanced_CheckoutStep';
    protected $acceptValues = 'Checkout step reached: 1 begin checkout, 2 shipping info, 3 payment info';
}
