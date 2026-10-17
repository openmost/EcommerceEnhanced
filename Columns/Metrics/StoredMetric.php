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
use Piwik\Metrics\Formatter;
use Piwik\Piwik;
use Piwik\Plugin\Metric;
use Piwik\Request;

/**
 * A metric read as is from the archive, formatted by its type.
 */
class StoredMetric extends Metric
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

    /**
     * @var int|null
     */
    private $idSite;

    public function __construct(string $name, string $translationKey, string $type)
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

        if ($this->type === Dimension::TYPE_PERCENT) {
            return $formatter->getPrettyPercentFromQuotient($value);
        }

        return $formatter->getPrettyNumber($value);
    }

    public function beforeFormat($report, DataTable $table)
    {
        $this->idSite = DataTableFactory::getSiteIdFromMetadata($table);

        return $this->type !== Dimension::TYPE_MONEY || !empty($this->idSite);
    }
}
