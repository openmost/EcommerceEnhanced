<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Columns\Metrics;

use Piwik\Columns\Dimension;
use Piwik\DataTable\Row;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugin\ProcessedMetric;
use Piwik\Request;

/**
 * A value the API computes or reads as is (a funnel step compared to the first step, the tax of an order).
 * Declared as a processed metric only so Matomo formats it, the value is kept as computed.
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

    /**
     * @var string
     */
    private $type;

    public function __construct(string $name, string $translationKey, string $type = Dimension::TYPE_PERCENT)
    {
        $this->name = $name;
        $this->translationKey = $translationKey;
        $this->type = $type;
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

    public function getSemanticType(): ?string
    {
        return $this->type;
    }

    public function format($value, Formatter $formatter)
    {
        if ($this->type === Dimension::TYPE_MONEY) {
            return $formatter->getPrettyMoney($value, Request::fromRequest()->getIntegerParameter('idSite', 0));
        }

        if ($this->type === Dimension::TYPE_NUMBER) {
            return $formatter->getPrettyNumber($value, 2);
        }

        return $formatter->getPrettyPercentFromQuotient($value);
    }
}
