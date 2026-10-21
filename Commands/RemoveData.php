<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\EcommerceEnhanced\Commands;

use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\EcommerceEnhanced\Dao\Schema;

/**
 * Uninstalling keeps the data (see EcommerceEnhanced::uninstall), this is the supported way to delete it: run it
 * before uninstalling, or to remove the data of some websites. The native ecommerce data is never touched.
 */
class RemoveData extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('ecommerceenhanced:remove-data');
        $this->setDescription('Deletes the data recorded by Ecommerce Enhanced, for every website or the given ones. The native ecommerce data is kept.');
        $this->addRequiredValueOption('idsite', null, 'Comma separated website ids, every website when omitted');
    }

    protected function doExecute(): int
    {
        $input = $this->getInput();
        $output = $this->getOutput();

        $idSites = null;
        if ($input->getOption('idsite') !== null) {
            $idSites = array_values(array_unique(array_map('intval', explode(',', (string) $input->getOption('idsite')))));
            if (in_array(0, $idSites, true)) {
                throw new \InvalidArgumentException('Option "idsite" must be a comma separated list of website ids');
            }
        }

        $output->writeln($idSites === null
            ? 'Deleting the Ecommerce Enhanced data of every website.'
            : sprintf('Deleting the Ecommerce Enhanced data of the websites %s.', implode(', ', $idSites)));
        $output->writeln('<info>This cannot be undone. Reports already archived are kept, and so are database backups.</info>');

        if (!$input->getOption('no-interaction') && !$this->askForConfirmation('<question>Are you sure you want to perform this action? (y/N)</question>', false)) {
            return self::FAILURE;
        }

        (new Schema())->deleteData($idSites);

        $this->writeSuccessMessage('The Ecommerce Enhanced data was deleted. To delete the tables and columns too, uninstall the plugin then drop them as described in the documentation.');

        return self::SUCCESS;
    }
}
