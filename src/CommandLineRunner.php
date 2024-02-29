<?php namespace BambooHR\Guardrail;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Phases\IndexingPhase;
use BambooHR\Guardrail\Phases\AnalyzingPhase;
use BambooHR\Guardrail\Exceptions\InvalidConfigException;

/**
 * Class CommandLineRunner
 *
 * @package BambooHR\Guardrail
 */
class CommandLineRunner {

	/**
	 * usage
	 *
	 * @return void
	 */
	public function usage() {
		echo "
Usage: php guardrail.phar [-a] [-i] [-n #] [--format xunit|text] [-o output_file_name] [-p #/#] [--timings] config_file

where: -p #/#                 = Define the number of partitions and the current partition.
                                Use for multiple hosts. Example: -p 1/4
                                
       --format {format}      = Use \"xunit\" format or a more console friendly \"text\" format                          

       -n #                   = number of child process to run.
                                Use for multiple processes on a single host.

       -a                     = run the \"analyze\" operation

       -i                     = run the \"index\" operation.
                                Defaults to yes if using in memory index.       
        
       -j                     = prefer json index

       -m                     = prefer in memory index (only available when -n=1 and -p=1/1)

       -o output_file_name    = Output results to the specified filename

       -v                     = Increase verbosity level.  Can be used once or twice.

       -h  or --help          = Ignore all other options and show this page.
       
       -l  or --list          = Ignore all other options and list standard test names.

       --timings              = Output a summary of how long each check ran for.
";
	}

	/**
	 * run
	 *
	 * @param array $argv The list of args
	 *
	 * @return void
	 */
	public function run(array $argv) {

		set_time_limit(0);
		date_default_timezone_set("UTC");
		error_reporting(E_WARNING | E_ERROR);

		if (!extension_loaded("pcntl")) {
			echo "Guardrail requires the pcntl extension, which is not loaded.\n";
			exit(1);
		}



		try {
			$config = new Config($argv);
		} catch (InvalidConfigException $exception) {
			$this->usage();
			exit(1);
		}

		$output = match($config->getOutputFormat()) {
			'text'   => new \BambooHR\Guardrail\Output\ConsoleOutput($config),
			'counts' => new \BambooHR\Guardrail\Output\CountsOutput($config),
			'csv'    => new \BambooHR\Guardrail\Output\CsvOutput($config),
			default  => new \BambooHR\Guardrail\Output\XUnitOutput($config)
		};

		if ($config->shouldIndex()) {
			$output->outputExtraVerbose("Indexing\n");
			$indexer = new IndexingPhase($config);
			$indexer->run($config, $output);
			$output->outputExtraVerbose("\nDone\n\n");
			//$output->renderResults();
			exit(0);
		}

		if ($config->shouldAnalyze()) {
			$analyzer = new AnalyzingPhase($output);
			$output->outputExtraVerbose("Analyzing\n");

			$exitCode = $analyzer->run($config, $output);
			$output->outputVerbose("\n");
			$output->outputExtraVerbose("Done\n\n");
			$output->renderResults();

			if ($config->shouldOutputTimings()) {
				$timings = $analyzer->getTimingResults();
				$totalTime = array_sum( array_map(
					function($element) {
						return $element['time'];
					},
					$timings)
				);
				foreach ($analyzer->getTimingResults() as $class => $values) {
					$time = $values['time'];
					$count = $values['count'];
					printf("%-60s %4.1f s %4.1f%% %10s calls %5.2f ms/call \n", $class, $time, $time / $totalTime * 100, number_format($count, 0), $time / $count * 1000 );
				}

				printf("Total = %d:%04.1f CPU time\n", intval($totalTime / 60), $totalTime - floor($totalTime / 60) * 60);
			}
			exit($exitCode);
		}
	}
}
