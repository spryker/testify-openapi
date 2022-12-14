<?php

/**
 * Copyright © 2019-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Glue\TestifyOpenApi\Helper\Statistic;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\OutputInterface;

class StatisticConsolePrinter
{
    /**
     * @param \Spryker\Glue\TestifyOpenApi\Helper\Statistic\Statistic $statistic
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return void
     */
    public function printReport(Statistic $statistic, OutputInterface $output): void
    {
        $output->writeln('');

        $this->printResultInfo($statistic, $output);

        foreach ($statistic->getStatistics() as $path => $methods) {
            $table = new Table($output);

            $table->setHeaders([
                [new TableCell(sprintf('<fg=green>%s</>', $path), ['colspan' => 3])],
                ['Method', 'Expected Response Code', 'Message'],
            ]);

            foreach ($methods as $method => $responseCodes) {
                foreach ($responseCodes as $responseCode => $results) {
                    if (!isset($results['failures']) && !isset($results['warnings'])) {
                        $table->addRow([strtoupper($method), $responseCode, 'Successful']);

                        continue;
                    }

                    if (isset($results['failures'])) {
                        foreach ($results['failures'] as $message) {
                            $table->addRow([strtoupper($method), $responseCode, $message]);
                        }
                    }

                    if (isset($results['warnings'])) {
                        $table->addRow(new TableSeparator());

                        foreach ($results['warnings'] as $message) {
                            $table->addRow([strtoupper($method), $responseCode, $message]);
                        }
                    }
                }
            }

            $table->render();
        }

        $output->writeln('');
        $totalNumberOfTests = $statistic->getTotalNumberOfTest();
        $totalNumberOfFailures = $statistic->getTotalNumberOfFailures();
        $totalNumberOfWarnings = $statistic->getTotalNumberOfWarnings();

        $successfulTests = $totalNumberOfTests - $totalNumberOfFailures;
        $output->writeln(sprintf('<fg=green>%s</> of %s were successfully executed', $successfulTests, $totalNumberOfTests));
        $output->writeln(sprintf('<fg=yellow>%s</> tests skipped', $totalNumberOfWarnings));

        if ($statistic->hasFailures() === true) {
            $output->writeln(sprintf('<fg=yellow>%s</> Tests failed', $totalNumberOfFailures));
        }
    }

    /**
     * @param \Spryker\Glue\TestifyOpenApi\Helper\Statistic\Statistic $statistic
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return void
     */
    protected function printResultInfo(Statistic $statistic, OutputInterface $output): void
    {
        if (!$statistic->hasFailures()) {
            $output->writeln('<fg=green>All tests pass</>');

            return;
        }

        $output->writeln('<error>Test(s) failed</error>');
    }
}
