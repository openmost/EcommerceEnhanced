<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Reports;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;
use Piwik\Plugins\CoreVisualizations\Visualizations\HtmlTable;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Bar;
use Piwik\Plugins\CoreVisualizations\Visualizations\JqplotGraph\Pie;
use Piwik\Plugins\EcommerceEnhanced\Metrics;
use Piwik\Site;

/**
 * A report of the Ecommerce category, available on sites with ecommerce enabled.
 */
abstract class Base extends Report
{
    /**
     * Translation key of the label column when the report has no dimension.
     *
     * @var string|null
     */
    protected $labelTranslationKey = null;

    /**
     * @var string[]
     */
    protected $metricNames = [];

    /**
     * @var string[]
     */
    protected $ratioNames = [];

    /**
     * Funnels keep their steps in order, they are never sorted.
     *
     * @var bool
     */
    protected $keepRowOrder = false;

    /**
     * Visualization shown first, a table unless a distribution reads better as a pie or bars.
     *
     * @var string|null
     */
    protected $defaultView = null;

    protected function init()
    {
        $this->module = 'EcommerceEnhanced';
        $this->categoryId = 'Goals_Ecommerce';
        $this->metrics = Metrics::make($this->metricNames);
        $this->processedMetrics = Metrics::make($this->ratioNames);
        $this->defaultSortColumn = $this->keepRowOrder ? '' : ($this->metricNames[0] ?? 'label');
    }

    public function getDefaultTypeViewDataTable()
    {
        return $this->defaultView ?? HtmlTable::ID;
    }

    public function isEnabled()
    {
        $idSite = Common::getRequestVar('idSite', false, 'int');

        return !empty($idSite) && (new Site($idSite))->isEcommerceEnabled();
    }

    public function configureReportMetadata(&$availableReports, $infos)
    {
        $idSite = $infos['idSite'] ?? null;
        if (!empty($idSite) && is_numeric($idSite) && (new Site((int) $idSite))->isEcommerceEnabled()) {
            $availableReports[] = $this->buildReportMetadata();
        }
    }

    public function configureView(ViewDataTable $view)
    {
        if ($this->labelTranslationKey !== null) {
            $view->config->addTranslation('label', Piwik::translate($this->labelTranslationKey));
        }

        $view->config->show_exclude_low_population = false;
        $view->config->show_table_all_columns = false;
        $view->config->columns_to_display = array_merge(['label'], $this->metricNames, $this->ratioNames);

        // A pie or bars plots one metric, the main one; the others stay available in the series picker
        if ($view->isViewDataTableId(Pie::ID) || $view->isViewDataTableId(Bar::ID)) {
            $view->config->columns_to_display = ['label', $this->metricNames[0]];
            $view->config->selectable_columns = $this->metricNames;
        }

        if ($this->keepRowOrder) {
            $view->config->enable_sort = false;
            $view->config->show_search = false;
            $view->config->show_limit_control = false;
            $view->config->show_pagination_control = false;
        } elseif ($view->isViewDataTableId(HtmlTable::ID)) {
            $view->config->show_totals_row = true;
        }

        // Stored money metrics are not processed metrics, only "all" formats them with the site currency
        if ($view->isViewDataTableId(HtmlTable::ID)) {
            $view->requestConfig->request_parameters_to_modify['format_metrics'] = 'all';
        }
    }
}
