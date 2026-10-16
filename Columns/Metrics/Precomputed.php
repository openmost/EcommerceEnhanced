<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Columns\Metrics;

use Piwik\DataTable\Row;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugin\ProcessedMetric;

/**
 * A percentage the API computes across rows (a funnel step compared to the first step). Declared as a processed
 * metric only so Matomo formats it, the value is kept as computed.
 */
class Precomputed extends ProcessedMetric
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $translationKey;

    public function __construct(string $name, string $translationKey)
    {
        $this->name = $name;
        $this->translationKey = $translationKey;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getTranslatedName()
    {
        return Piwik::translate($this->translationKey);
    }

    public function getDocumentation()
    {
        $key = $this->translationKey . 'Documentation';
        $documentation = Piwik::translate($key);

        return $documentation === $key ? '' : $documentation;
    }

    public function compute(Row $row)
    {
        return $this->getMetric($row, $this->name);
    }

    public function getDependentMetrics()
    {
        return [];
    }

    public function format($value, Formatter $formatter)
    {
        return $formatter->getPrettyPercentFromQuotient($value);
    }
}
