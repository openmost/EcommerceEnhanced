<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Commands;

use Piwik\Plugins\CustomDimensions\Commands\AddCustomDimension as CoreAddCustomDimension;
use Piwik\Plugins\EcommerceEnhanced\Dao\Schema;
use Piwik\Plugins\EcommerceEnhanced\ItemDimensions;
use Piwik\Tracker\Cache;

/**
 * customdimensions:add-custom-dimension, with the "item" scope of Ecommerce Enhanced: each Item dimension is a
 * column of the item tables, added the same way as a visit or action dimension. It replaces the command of Custom
 * Dimensions (see EcommerceEnhanced::replaceAddCustomDimensionCommand), the visit and action scopes run unchanged.
 */
class AddCustomDimension extends CoreAddCustomDimension
{
    protected function configure()
    {
        parent::configure();

        $this->setHelp($this->getHelp() . "./console customdimensions:add-custom-dimension --scope=item --count=5
=> Will add 5 new Item dimensions (Ecommerce Enhanced).
");
    }

    protected function doExecute(): int
    {
        if ($this->getInput()->getOption('scope') !== ItemDimensions::SCOPE) {
            return parent::doExecute();
        }

        $input = $this->getInput();
        $output = $this->getOutput();
        $count = $input->getOption('count');
        if (!is_numeric($count) || (int) $count <= 0) {
            throw new \InvalidArgumentException('Option "count" must be a number, at least one');
        }

        $output->writeln(sprintf('Adding %d Custom Dimension(s) in scope %s.', (int) $count, ItemDimensions::SCOPE));
        $output->writeln('<info>This causes schema changes in the database and may take a very long time.</info>');

        if (!$input->getOption('no-interaction') && !$this->askForConfirmation('<question>Are you sure you want to perform this action? (y/N)</question>', false)) {
            return self::FAILURE;
        }

        $available = (new Schema())->addCustomSlots((int) $count);
        Cache::clearCacheGeneral();

        $this->writeSuccessMessage(sprintf('Your Matomo is now configured for up to %d Custom Dimensions in scope %s.', $available, ItemDimensions::SCOPE));

        return self::SUCCESS;
    }
}
