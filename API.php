<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced;

use Piwik\Archive;
use Piwik\Archive\DataTableFactory;
use Piwik\DataTable;
use Piwik\Date;
use Piwik\Db;
use Piwik\Period;
use Piwik\Piwik;
use Piwik\Plugins\CorePluginsAdmin\SettingsMetadata;
use Piwik\Segment;
use Piwik\Site;
use Piwik\Tracker\Cache;
use Piwik\Tracker\GoalManager;

/**
 * Ecommerce reports built from the GA4 ecommerce data: products, brands, variants, item lists, promotions,
 * orders by coupon, payment type, shipping tier, affiliation, customer type and currency, and the shopping and
 * checkout behavior funnels.
 *
 * @method static \Piwik\Plugins\EcommerceEnhanced\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    public const MAX_TRANSACTIONS = 500;

    /**
     * Products with their views, adds to cart, purchases and revenue.
     *
     * @return DataTable|DataTable\Map
     */
    public function getProducts($idSite, $period, $date, $segment = false)
    {
        return $this->getRecord(Records::PRODUCTS, $idSite, $period, $date, $segment);
    }

    /**
     * Brands with their views, adds to cart, purchases and revenue.
     *
     * @return DataTable|DataTable\Map
     */
    public function getBrands($idSite, $period, $date, $segment = false)
    {
        return $this->getRecord(Records::BRANDS, $idSite, $period, $date, $segment);
    }

    /**
     * Product variants with their views, adds to cart, purchases and revenue.
     *
     * @return DataTable|DataTable\Map
     */
    public function getVariants($idSite, $period, $date, $segment = false)
    {
        return $this->getRecord(Records::VARIANTS, $idSite, $period, $date, $segment);
    }

    /**
     * Product categories, every level, with their views, adds to cart, purchases and revenue.
     *
     * @return DataTable|DataTable\Map
     */
    public function getCategories($idSite, $period, $date, $segment = false)
    {
        return $this->getRecord(Records::CATEGORIES, $idSite, $period, $date, $segment);
    }

    /**
     * Product search terms with their searches, visits, products listed and visits that ordered.
     *
     * @return DataTable|DataTable\Map
     */
    public function getSearchTerms($idSite, $period, $date, $segment = false)
    {
        return $this->getRecord(Records::SEARCH_TERMS, $idSite, $period, $date, $segment);
    }

    /**
     * The orders of the period, most recent first, with their revenue split and their GA4 details. Read from the
     * orders themselves, like the Ecommerce log, at most 500 orders.
     *
     * @return DataTable
     */
    public function getTransactions($idSite, $period, $date, $segment = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        // A list of orders is row level data: hidden with the visitor log when an administrator disabled it
        if (!\Piwik\Plugins\Live\Live::isVisitorLogEnabled((int) $idSite)) {
            throw new \Exception(Piwik::translate('EcommerceEnhanced_TransactionsDisabled'));
        }

        $idSite = (int) $idSite;
        $timezone = Site::getTimezoneFor($idSite);
        $range = Period\Factory::build($period, $date, $timezone);
        $start = Date::factory($range->getDateStart()->toString() . ' 00:00:00', $timezone);
        $end = Date::factory($range->getDateEnd()->toString() . ' 23:59:59', $timezone);

        $select = 'log_conversion.idorder, log_conversion.server_time, log_conversion.revenue, log_conversion.revenue_subtotal,
            log_conversion.revenue_tax, log_conversion.revenue_shipping, log_conversion.revenue_discount, log_conversion.items,
            log_conversion.ee_coupon, log_conversion.ee_payment_type, log_conversion.ee_shipping_tier,
            log_conversion.ee_customer_type, log_conversion.ee_currency';
        $where = 'log_conversion.idsite = ? AND log_conversion.idgoal = ? AND log_conversion.server_time >= ? AND log_conversion.server_time <= ?';
        $bind = [$idSite, GoalManager::IDGOAL_ORDER, $start->getDatetime(), $end->getDatetime()];

        $query = (new Segment((string) $segment, [$idSite], $start, $end))
            ->getSelectQuery($select, 'log_conversion', $where, $bind, 'log_conversion.server_time DESC', false, self::MAX_TRANSACTIONS);

        $customerTypes = [
            Tracker\Payload::CUSTOMER_TYPE_NEW => Piwik::translate('EcommerceEnhanced_CustomerTypeNew'),
            Tracker\Payload::CUSTOMER_TYPE_RETURNING => Piwik::translate('EcommerceEnhanced_CustomerTypeReturning'),
        ];

        $table = new DataTable();
        // As an archived table does, so money is formatted with the site currency
        $table->setMetadata(DataTableFactory::TABLE_METADATA_SITE_INDEX, new Site($idSite));
        $table->setMetadata(DataTableFactory::TABLE_METADATA_PERIOD_INDEX, $range);
        foreach (Db::fetchAll($query['sql'], $query['bind']) as $order) {
            $table->addRowFromSimpleArray([
                'label' => $order['idorder'],
                'date' => Date::factory($order['server_time'])->setTimezone($timezone)->getDatetime(),
                Metrics::REVENUE => (float) $order['revenue'],
                Metrics::SUBTOTAL => (float) $order['revenue_subtotal'],
                Metrics::TAX => (float) $order['revenue_tax'],
                Metrics::SHIPPING => (float) $order['revenue_shipping'],
                Metrics::DISCOUNT => (float) $order['revenue_discount'],
                Metrics::ITEMS => (int) $order['items'],
                'coupon' => (string) $order['ee_coupon'],
                'payment_type' => (string) $order['ee_payment_type'],
                'shipping_tier' => (string) $order['ee_shipping_tier'],
                'customer_type' => $customerTypes[(int) $order['ee_customer_type']] ?? '',
                'currency' => (string) $order['ee_currency'],
            ]);
        }

        return $table;
    }

    /**
     * Item lists with their views, clicks, and the items added to cart and bought from them.
     *
     * @return DataTable|DataTable\Map
     */
    public function getItemLists($idSite, $period, $date, $segment = false)
    {
        return $this->getRecord(Records::ITEM_LISTS, $idSite, $period, $date, $segment);
    }

    /**
     * Promotions with their views, clicks, and the items added to cart and bought from them.
     *
     * @return DataTable|DataTable\Map
     */
    public function getPromotions($idSite, $period, $date, $segment = false)
    {
        return $this->getRecord(Records::PROMOTIONS, $idSite, $period, $date, $segment);
    }

    /**
     * Orders and revenue by coupon code.
     *
     * @return DataTable|DataTable\Map
     */
    public function getCoupons($idSite, $period, $date, $segment = false)
    {
        return $this->getRecord(Records::COUPONS, $idSite, $period, $date, $segment);
    }

    /**
     * Orders and revenue by payment type.
     *
     * @return DataTable|DataTable\Map
     */
    public function getPaymentTypes($idSite, $period, $date, $segment = false)
    {
        return $this->getRecord(Records::PAYMENT_TYPES, $idSite, $period, $date, $segment);
    }

    /**
     * Orders and revenue by shipping tier.
     *
     * @return DataTable|DataTable\Map
     */
    public function getShippingTiers($idSite, $period, $date, $segment = false)
    {
        return $this->getRecord(Records::SHIPPING_TIERS, $idSite, $period, $date, $segment);
    }

    /**
     * Orders and revenue by affiliation (store or marketplace).
     *
     * @return DataTable|DataTable\Map
     */
    public function getAffiliations($idSite, $period, $date, $segment = false)
    {
        return $this->getRecord(Records::AFFILIATIONS, $idSite, $period, $date, $segment);
    }

    /**
     * Orders and revenue of new and returning customers, as declared by the shop.
     *
     * @return DataTable|DataTable\Map
     */
    public function getCustomerTypes($idSite, $period, $date, $segment = false)
    {
        $table = $this->getRecord(Records::CUSTOMER_TYPES, $idSite, $period, $date, $segment);
        $table->filter('ColumnCallbackReplace', ['label', function ($label) {
            if ($label === 'new') {
                return Piwik::translate('EcommerceEnhanced_CustomerTypeNew');
            }

            return $label === 'returning' ? Piwik::translate('EcommerceEnhanced_CustomerTypeReturning') : $label;
        }]);

        return $table;
    }

    /**
     * Orders and revenue by order currency.
     *
     * @return DataTable|DataTable\Map
     */
    public function getCurrencies($idSite, $period, $date, $segment = false)
    {
        return $this->getRecord(Records::CURRENCIES, $idSite, $period, $date, $segment);
    }

    /**
     * GA4 monetization overview: orders, revenue, purchasers, first time purchasers, average revenue per user and per
     * paying user, ecommerce conversion rate and cart abandonment rate.
     *
     * @return DataTable|DataTable\Map
     */
    public function getBuyerKpis($idSite, $period, $date, $segment = false)
    {
        Piwik::checkUserHasViewAccess($idSite);

        $names = array_merge(array_keys(Records::BUYER_METRICS), [Metrics::VISITS, Metrics::UNIQUE_VISITORS]);
        $table = Archive::build($idSite, $period, $date, $segment)->getDataTableFromNumeric($names);

        $table->filter(function (DataTable $table) {
            foreach ($table->getRows() as $row) {
                foreach (Records::BUYER_METRICS as $record => $metric) {
                    $row->renameColumn($record, $metric);
                }

                if ((int) $row->getColumn(Metrics::UNIQUE_VISITORS) === 0 && (int) $row->getColumn(Metrics::VISITS) > 0) {
                    $row->deleteColumn(Metrics::UNIQUE_VISITORS);
                }

                $cartVisits = (int) $row->getColumn(Metrics::CART_VISITS);
                $convertedVisits = (int) $row->getColumn(Metrics::CONVERTED_VISITS);
                $row->setColumn(Metrics::CART_ABANDONMENT_RATE, $cartVisits === 0 ? 0 : round(1 - $convertedVisits / $cartVisits, 4));
            }
        });

        return $table;
    }

    /**
     * Visits, orders and revenue by GA4 style source / medium.
     *
     * @return DataTable|DataTable\Map
     */
    public function getSourceMedium($idSite, $period, $date, $segment = false)
    {
        return $this->getRecord(Records::SOURCE_MEDIUM, $idSite, $period, $date, $segment);
    }

    /**
     * Visits that viewed a product, added to cart, began checkout and bought.
     *
     * @return DataTable|DataTable\Map
     */
    public function getShoppingBehavior($idSite, $period, $date, $segment = false)
    {
        return $this->getFunnel(Records::SHOPPING_BEHAVIOR, RecordBuilders\FunnelReports::SHOPPING_STEPS, $idSite, $period, $date, $segment);
    }

    /**
     * Visits that began checkout, added shipping info, added payment info and bought.
     *
     * @return DataTable|DataTable\Map
     */
    public function getCheckoutBehavior($idSite, $period, $date, $segment = false)
    {
        return $this->getFunnel(Records::CHECKOUT_BEHAVIOR, RecordBuilders\FunnelReports::CHECKOUT_STEPS, $idSite, $period, $date, $segment);
    }

    /**
     * Website settings of Ecommerce Enhanced, and of Premium when activated, in the format of the Matomo settings
     * components. They are edited on the Ecommerce Enhanced page under Websites, not in the website form.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSiteSettings($idSite)
    {
        Piwik::checkUserHasAdminAccess($idSite);

        return (new SettingsMetadata())->formatSettings(SettingsPage::load((int) $idSite), (int) $idSite);
    }

    /**
     * Saves the website settings, every value is validated before anything is saved.
     *
     * @param array<string, array<int, array{name: string, value: mixed}>> $settingValues plugin name => settings
     */
    public function updateSiteSettings($idSite, $settingValues)
    {
        Piwik::checkUserHasAdminAccess($idSite);

        $idSite = (int) $idSite;
        $sections = SettingsPage::load($idSite);
        (new SettingsMetadata())->setPluginSettings($sections, is_array($settingValues) ? $settingValues : [], $idSite);
        foreach ($sections as $settings) {
            $settings->save();
        }

        Cache::deleteCacheWebsiteAttributes($idSite);

        /**
         * Triggered after the website settings of Ecommerce Enhanced and Premium are saved on their page.
         *
         * @param int $idSite
         */
        Piwik::postEvent('EcommerceEnhanced.siteSettingsUpdated', [$idSite]);

        return true;
    }

    /**
     * Adds a custom item parameter, the "Item" scope of the Custom Dimensions page. Its name is the parameter key
     * in the items of the dataLayer.
     *
     * @return int id of the item dimension in the Custom Dimensions page
     */
    public function addItemDimension($idSite, $name, $description = '', $active = '1')
    {
        return ItemDimensions::create((int) $idSite, (string) $name, (string) $description, self::toBool($active));
    }

    /**
     * Renames, describes, activates or deactivates a custom item parameter. Renaming keeps the values recorded
     * before, deactivating stops recording new ones.
     */
    public function updateItemDimension($idSite, $idDimension, $name, $description = null, $active = '1')
    {
        if (!ItemDimensions::isItemDimensionId($idDimension)) {
            throw new \Exception(Piwik::translate('EcommerceEnhanced_ItemDimensionNotFound'));
        }

        ItemDimensions::update((int) $idSite, (int) $idDimension - ItemDimensions::ID_OFFSET, (string) $name, $description, self::toBool($active));

        return true;
    }

    /**
     * @param mixed $value
     */
    private static function toBool($value): bool
    {
        return !in_array($value, [false, 0, '0', 'false', ''], true);
    }

    /**
     * @return DataTable|DataTable\Map
     */
    private function getRecord(string $record, $idSite, $period, $date, $segment)
    {
        Piwik::checkUserHasViewAccess($idSite);

        return Archive::createDataTableFromArchive($record, $idSite, $period, $date, $segment);
    }

    /**
     * Puts the steps in funnel order and adds to each one its share of the first step and the share of visits
     * lost before the next step.
     *
     * @param string[] $steps
     * @return DataTable|DataTable\Map
     */
    private function getFunnel(string $record, array $steps, $idSite, $period, $date, $segment)
    {
        $table = $this->getRecord($record, $idSite, $period, $date, $segment);

        $table->filter(function (DataTable $table) use ($steps) {
            $visitsByStep = [];
            foreach ($table->getRows() as $row) {
                $visitsByStep[(string) $row->getColumn('label')] = (int) $row->getColumn(Metrics::VISITS);
            }

            $rows = [];
            $first = $visitsByStep[$steps[0]] ?? 0;
            foreach ($steps as $index => $step) {
                $visits = $visitsByStep[$step] ?? 0;
                $next = isset($steps[$index + 1]) ? ($visitsByStep[$steps[$index + 1]] ?? 0) : null;

                $rows[] = new DataTable\Row([DataTable\Row::COLUMNS => [
                    'label' => $this->getStepName($step),
                    Metrics::VISITS => $visits,
                    Metrics::STEP_RATE => Piwik::getQuotientSafe($visits, $first, 4),
                    Metrics::ABANDONMENT_RATE => $next === null || $visits === 0 ? 0 : round(max(0, $visits - $next) / $visits, 4),
                ]]);
            }

            $table->setRows($rows);
        });

        return $table;
    }

    private function getStepName(string $step): string
    {
        $key = 'EcommerceEnhanced_Step_' . $step;
        $name = Piwik::translate($key);

        return $name === $key ? $step : $name;
    }
}
