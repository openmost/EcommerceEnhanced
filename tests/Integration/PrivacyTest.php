<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\tests\Integration;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Db;
use Piwik\Plugins\CustomJsTracker\TrackerUpdater;
use Piwik\Plugins\EcommerceEnhanced\Dao\Schema;
use Piwik\Plugins\EcommerceEnhanced\ItemDimensions;
use Piwik\Plugins\EcommerceEnhanced\SettingsPage;
use Piwik\Plugins\PrivacyManager\Model\DataSubjects;

/**
 * @group EcommerceEnhanced
 * @group Integration
 */
class PrivacyTest extends ShopTestCase
{
    protected function configureSite(): void
    {
        $settings = SettingsPage::loadItemDimensions($this->idSite);
        $settings->itemCustomParams->setValue(['size', 'item_cost']);
        $settings->itemCustomParamMeta->setValue(json_encode([2 => ['label' => 'Cost', 'description' => '', 'active' => false]]));
        $settings->save();
    }

    public function test_jsTracker_listsTheActiveItemDimensionKeysOfEachWebsite(): void
    {
        $content = '';
        ItemDimensions::addToJsTracker($content);

        $this->assertStringContainsString('window.matomoEcommerceEnhancedItemDimensions={"' . $this->idSite . '":["size"]};', $content);
    }

    public function test_jsTracker_generatedByMatomo_includesTheItemDimensionKeys(): void
    {
        $content = (new TrackerUpdater())->getUpdatedTrackerFileContent();

        $this->assertStringContainsString('window.matomoEcommerceEnhancedItemDimensions={"' . $this->idSite . '":["size"]};', $content);
    }

    public function test_gdprDeletion_removesTheVisitDataFromThePluginTables(): void
    {
        $idVisits = array_column(Db::fetchAll('SELECT idvisit FROM ' . Common::prefixTable('log_visit') . ' WHERE idsite = ?', [$this->idSite]), 'idvisit');
        $this->assertNotEmpty($this->countPluginRows());

        StaticContainer::get(DataSubjects::class)->deleteDataSubjects(array_map(function ($idVisit) {
            return ['idsite' => $this->idSite, 'idvisit' => $idVisit];
        }, $idVisits));

        $this->assertSame([], $this->countPluginRows());
    }

    public function test_deleteData_ofOtherWebsites_keepsThisOne(): void
    {
        $before = $this->countPluginRows();

        (new Schema())->deleteData([$this->idSite + 1]);

        $this->assertSame($before, $this->countPluginRows());
    }

    public function test_deleteData_keepsTheNativeEcommerceData(): void
    {
        $conversions = Common::prefixTable('log_conversion');
        $orders = Db::fetchOne("SELECT COUNT(*) FROM `$conversions` WHERE idsite = ?", [$this->idSite]);

        (new Schema())->deleteData(null);

        $this->assertSame([], $this->countPluginRows());
        $this->assertSame($orders, Db::fetchOne("SELECT COUNT(*) FROM `$conversions` WHERE idsite = ?", [$this->idSite]));
    }

    /**
     * @return array<string, int> rows of the website per table or column, the empty ones left out
     */
    private function countPluginRows(): array
    {
        $counts = [];
        foreach (Schema::getTables() as $table) {
            $counts[$table] = (int) Db::fetchOne('SELECT COUNT(*) FROM ' . Common::prefixTable($table) . ' WHERE idsite = ?', [$this->idSite]);
        }

        foreach (Schema::CONVERSION_COLUMNS as $column) {
            $counts[$column] = (int) Db::fetchOne('SELECT COUNT(*) FROM ' . Common::prefixTable('log_conversion') . " WHERE idsite = ? AND `$column` IS NOT NULL", [$this->idSite]);
        }

        return array_filter($counts);
    }
}
