<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Columns\Metrics;

use Piwik\Archive\DataTableFactory;
use Piwik\Columns\Dimension;
use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugin\ProcessedMetric;
use Piwik\Request;

/**
 * A metric divided by another one, computed at display time so it stays right for any period.
 */
class Ratio extends ProcessedMetric
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
    private $numerator;

    /**
     * @var string
     */
    private $denominator;

    /**
     * @var string
     */
    private $type;

    /**
     * @var int|null
     */
    private $idSite;

    public function __construct(string $name, string $translationKey, string $numerator, string $denominator, string $type)
    {
        $this->name = $name;
        $this->translationKey = $translationKey;
        $this->numerator = $numerator;
        $this->denominator = $denominator;
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
        $denominator = $this->getMetric($row, $this->denominator);

        // Unique visitors are not processed for years and ranges by default: no value rather than a wrong zero
        if ($denominator === false || $denominator === null || $denominator === '') {
            return false;
        }

        $precision = $this->type === Dimension::TYPE_PERCENT ? 4 : 2;

        return Piwik::getQuotientSafe($this->getMetric($row, $this->numerator), $denominator, $precision);
    }

    public function getDependentMetrics()
    {
        return [$this->numerator, $this->denominator];
    }

    public function getSemanticType(): ?string
    {
        return $this->type;
    }

    public function format($value, Formatter $formatter)
    {
        if ($this->type === Dimension::TYPE_MONEY) {
            // Sparklines format without calling beforeFormat(), the site then comes from the request
            return $formatter->getPrettyMoney($value, $this->idSite ?: Request::fromRequest()->getIntegerParameter('idSite', 0));
        }

        if ($this->type === Dimension::TYPE_NUMBER) {
            return $formatter->getPrettyNumber($value, 2);
        }

        return $formatter->getPrettyPercentFromQuotient($value);
    }

    public function beforeFormat($report, DataTable $table)
    {
        $this->idSite = DataTableFactory::getSiteIdFromMetadata($table);

        return $this->type !== Dimension::TYPE_MONEY || !empty($this->idSite);
    }
}
