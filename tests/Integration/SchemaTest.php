<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\tests\Integration;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\EcommerceEnhanced\Dao\Schema;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group EcommerceEnhanced
 * @group Integration
 */
class SchemaTest extends IntegrationTestCase
{
    public function test_addCustomSlots_addsAColumnToEachItemTable(): void
    {
        $this->assertSame(Schema::DEFAULT_CUSTOM_SLOTS, Schema::getCustomSlots());

        $this->assertSame(Schema::DEFAULT_CUSTOM_SLOTS + 2, (new Schema())->addCustomSlots(2));
        $this->assertSame(Schema::DEFAULT_CUSTOM_SLOTS + 2, Schema::getCustomSlots());

        foreach ([Schema::TABLE_ORDER_ITEM, Schema::TABLE_EVENT_ITEM] as $table) {
            $columns = array_column(Db::fetchAll('SHOW COLUMNS FROM ' . Common::prefixTable($table)), 'Field');
            $this->assertContains('custom' . (Schema::DEFAULT_CUSTOM_SLOTS + 2), $columns, $table);
        }
    }

    public function test_addCustomSlots_refusesMoreThanTheTrackerReads(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Schema())->addCustomSlots(Schema::MAX_CUSTOM_SLOTS);
    }
}
