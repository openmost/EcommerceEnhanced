<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced;

use Piwik\Archive;
use Piwik\DataTable;
use Piwik\Piwik;

/**
 * Ecommerce reports built from the GA4 ecommerce data: products, brands, variants, item lists, promotions,
 * orders by coupon, payment type, shipping tier, affiliation, customer type and currency, and the shopping and
 * checkout behavior funnels.
 *
 * @method static \Piwik\Plugins\EcommerceEnhanced\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
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
