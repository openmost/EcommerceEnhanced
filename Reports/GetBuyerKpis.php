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
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Evolution;
use Piwik\Plugins\CoreVisualizations\Visualizations\Sparklines;
use Piwik\Plugins\EcommerceEnhanced\Metrics;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

/**
 * The GA4 monetization overview, as sparklines on the Ecommerce overview.
 */
class GetBuyerKpis extends Base
{
    protected $metricNames = [
        Metrics::REVENUE,
        Metrics::ORDERS,
        Metrics::PURCHASERS,
        Metrics::FIRST_TIME_PURCHASERS,
        Metrics::VISITS,
        Metrics::UNIQUE_VISITORS,
        Metrics::CONVERTED_VISITS,
        Metrics::CART_VISITS,
    ];

    protected $ratioNames = [
        Metrics::ARPU,
        Metrics::ARPPU,
        Metrics::AVERAGE_ORDER_VALUE,
        Metrics::CONVERSION_RATE,
        Metrics::CART_ABANDONMENT_RATE,
    ];

    protected function init()
    {
        parent::init();

        $this->action = 'getBuyerKpis';
        $this->name = Piwik::translate('EcommerceEnhanced_BuyerKpis');
        $this->documentation = Piwik::translate('EcommerceEnhanced_BuyerKpisDocumentation');
        $this->subcategoryId = 'General_Overview';
        $this->order = 30;
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $widgetsList->addWidgetConfig(
            $factory->createWidget()
                ->forceViewDataTable(Sparklines::ID)
                ->setName('EcommerceEnhanced_BuyerKpis')
                ->setOrder(30)
        );

        $widgetsList->addWidgetConfig(
            $factory->createWidget()
                ->forceViewDataTable(Evolution::ID)
                ->setName('EcommerceEnhanced_BuyerKpisEvolution')
                ->setOrder(31)
        );
    }

    public function configureView(ViewDataTable $view)
    {
        if ($view->isViewDataTableId(Sparklines::ID)) {
            /** @var Sparklines $view */
            $view->config->addSparklineMetric([Metrics::REVENUE, Metrics::ORDERS], 10);
            $view->config->addSparklineMetric([Metrics::PURCHASERS, Metrics::FIRST_TIME_PURCHASERS], 20);
            $view->config->addSparklineMetric([Metrics::ARPU, Metrics::ARPPU], 30);
            $view->config->addSparklineMetric([Metrics::AVERAGE_ORDER_VALUE], 40);
            $view->config->addSparklineMetric([Metrics::CONVERSION_RATE], 50);
            $view->config->addSparklineMetric([Metrics::CART_ABANDONMENT_RATE], 60);

            foreach (Metrics::make(array_merge($this->metricNames, $this->ratioNames)) as $metric) {
                $view->config->addTranslation($metric->getName(), $metric->getTranslatedName());
            }

            return;
        }

        if ($view->isViewDataTableId(Evolution::ID)) {
            $view->config->columns_to_display = [Metrics::REVENUE, Metrics::PURCHASERS];
            return;
        }

        parent::configureView($view);
    }
}
