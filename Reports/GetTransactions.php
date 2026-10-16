<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Reports;

use Piwik\Piwik;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\EcommerceEnhanced\Metrics;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

/**
 * The GA4 Transactions report: every order of the period with its revenue split and GA4 details.
 */
class GetTransactions extends Base
{
    protected $labelTranslationKey = 'EcommerceEnhanced_TransactionId';

    protected $metricNames = [
        Metrics::REVENUE,
        Metrics::ITEMS,
    ];

    // Read as is from the orders, declared here so they are formatted as money
    protected $ratioNames = [
        Metrics::SUBTOTAL,
        Metrics::TAX,
        Metrics::SHIPPING,
        Metrics::DISCOUNT,
    ];

    /**
     * Text columns => translation key
     */
    private const DETAILS = [
        'date' => 'General_Date',
        'coupon' => 'EcommerceEnhanced_OrderCoupon',
        'payment_type' => 'EcommerceEnhanced_OrderPaymentType',
        'shipping_tier' => 'EcommerceEnhanced_OrderShippingTier',
        'customer_type' => 'EcommerceEnhanced_OrderCustomerType',
        'currency' => 'EcommerceEnhanced_OrderCurrency',
    ];

    protected function init()
    {
        parent::init();

        $this->action = 'getTransactions';
        $this->name = Piwik::translate('EcommerceEnhanced_Transactions');
        $this->documentation = Piwik::translate('EcommerceEnhanced_TransactionsDocumentation');
        $this->subcategoryId = 'EcommerceEnhanced_OrderDetails';
        $this->order = 0;
        $this->defaultSortColumn = 'date';
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $widgetsList->addWidgetConfig($factory->createWidget()->setIsWide());
    }

    public function configureView(ViewDataTable $view)
    {
        parent::configureView($view);

        $view->config->columns_to_display = array_merge(
            ['label', 'date', Metrics::REVENUE, Metrics::SUBTOTAL, Metrics::TAX, Metrics::SHIPPING, Metrics::DISCOUNT, Metrics::ITEMS],
            array_slice(array_keys(self::DETAILS), 1)
        );
        foreach (self::DETAILS as $column => $translationKey) {
            $view->config->addTranslation($column, Piwik::translate($translationKey));
        }

        $view->config->show_totals_row = false;
        $view->config->show_pie_chart = false;
        $view->config->show_bar_chart = false;
        $view->config->show_tag_cloud = false;
        $view->config->show_insights = false;
        $view->config->disable_row_evolution = true;
    }
}
