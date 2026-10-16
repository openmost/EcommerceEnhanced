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

class SearchTerm extends OwnTableDimension
{
    protected $dbTableName = Schema::TABLE_EVENT;
    protected $columnName = 'search_term';
    protected $segmentName = 'eeSearchTerm';
    protected $type = self::TYPE_TEXT;
    protected $nameSingular = 'EcommerceEnhanced_SearchTerm';
    protected $namePlural = 'EcommerceEnhanced_SearchTermPlural';
    protected $acceptValues = 'Search term of a search results page listing products';
}
