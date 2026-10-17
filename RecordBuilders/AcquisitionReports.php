<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\RecordBuilders;

use Piwik\ArchiveProcessor;
use Piwik\ArchiveProcessor\Record;
use Piwik\Common;
use Piwik\DataTable;
use Piwik\Plugin\Manager;
use Piwik\Plugins\EcommerceEnhanced\Metrics;
use Piwik\Plugins\EcommerceEnhanced\Records;
use Piwik\Tracker\GoalManager;

/**
 * Visits, orders and revenue by GA4 style source / medium: google / organic, example.com / referral,
 * newsletter / email. Visits use the referrer of the visit, orders the referrer Matomo attributes the order to,
 * as the native Sales reports do.
 */
class AcquisitionReports extends Base
{
    public function getRecordMetadata(ArchiveProcessor $archiveProcessor): array
    {
        return [
            Record::make(Record::TYPE_BLOB, Records::SOURCE_MEDIUM)
                ->setMaxRowsInTable(Records::MAX_ROWS)
                ->setColumnToSortByBeforeTruncation(Metrics::VISITS),
        ];
    }

    protected function aggregate(ArchiveProcessor $archiveProcessor): array
    {
        $logAggregator = $archiveProcessor->getLogAggregator();
        $table = new DataTable();

        $this->addRows($table, $logAggregator, 'log_visit', self::getSourceMediumSql('log_visit'), [
            Metrics::VISITS => 'COUNT(*)',
        ], '1 = 1', [], 'visit_last_action_time');

        $this->addRows($table, $logAggregator, 'log_conversion', self::getSourceMediumSql('log_conversion'), [
            Metrics::CONVERTED_VISITS => 'COUNT(DISTINCT log_conversion.idvisit)',
            Metrics::ORDERS => 'COUNT(*)',
            Metrics::REVENUE => 'ROUND(SUM(log_conversion.revenue), 2)',
        ], 'log_conversion.idgoal = ' . GoalManager::IDGOAL_ORDER);

        return [Records::SOURCE_MEDIUM => $table];
    }

    /**
     * SQL label "source / medium". Campaign source and medium come from MarketingCampaignsReporting when it is
     * installed; without it Matomo only keeps the campaign name, used as the source. Premium reuses it.
     */
    public static function getSourceMediumSql(string $logTable): string
    {
        $hasCampaignColumns = Manager::getInstance()->isPluginActivated('MarketingCampaignsReporting');
        $campaignSource = $hasCampaignColumns ? "COALESCE(NULLIF(LOWER({t}.campaign_source), ''), LOWER({t}.referer_name))" : 'LOWER({t}.referer_name)';
        $campaignMedium = $hasCampaignColumns ? "COALESCE(NULLIF(LOWER({t}.campaign_medium), ''), 'campaign')" : "'campaign'";

        $sql = "CONCAT(
            CASE {t}.referer_type
                WHEN " . Common::REFERRER_TYPE_DIRECT_ENTRY . " THEN '(direct)'
                WHEN " . Common::REFERRER_TYPE_CAMPAIGN . " THEN " . $campaignSource . "
                ELSE COALESCE(LOWER({t}.referer_name), '(not set)')
            END,
            ' / ',
            CASE {t}.referer_type
                WHEN " . Common::REFERRER_TYPE_DIRECT_ENTRY . " THEN '(none)'
                WHEN " . Common::REFERRER_TYPE_SEARCH_ENGINE . " THEN 'organic'
                WHEN " . Common::REFERRER_TYPE_WEBSITE . " THEN 'referral'
                WHEN " . Common::REFERRER_TYPE_CAMPAIGN . " THEN " . $campaignMedium . "
                WHEN " . Common::REFERRER_TYPE_SOCIAL_NETWORK . " THEN 'social'
                WHEN " . Common::REFERRER_TYPE_AI_ASSISTANT . " THEN 'ai'
                ELSE '(not set)'
            END
        )";

        return str_replace('{t}', $logTable, $sql);
    }
}
