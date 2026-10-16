<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Dimension;

use Piwik\Columns\Dimension;

/**
 * A column of one of the plugin log tables, exposed as a segment and to Custom Reports. The tables join on
 * idvisit, so a segment selects the visits having at least one matching row.
 */
abstract class OwnTableDimension extends Dimension
{
    protected $category = 'Goals_Ecommerce';
    protected $type = self::TYPE_TEXT;

    public function __construct()
    {
        if ($this->type === self::TYPE_TEXT) {
            $this->suggestedValuesCallback = SuggestedValues::fromColumn($this->dbTableName, $this->columnName);
        }
    }
}
