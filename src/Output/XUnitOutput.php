<?php namespace BambooHR\Guardrail\Output;

/**
 * Guardrail.  Copyright (c) 2016-2017, Jonathan Gardiner and BambooHR.
 * Apache 2.0 License
 */

use BambooHR\Guardrail\Config;
use N98\JUnitXml;
use Webmozart\Glob\Glob;

/**
 * Class XUnitOutput
 *
 * @package BambooHR\Guardrail\Output
 */
class XUnitOutput implements OutputInterface {

	/** @var Config  */
	private $config;

	/** @var JUnitXml\TestSuiteElement[] */
	protected $suites;

	/** @var JUnitXml\Document  */
	protected $doc;

	/**
	 * @var array
	 */
	private $files;

	/**
	 * @var bool
	 */
	protected $emitErrors;

	/**
	 * @var mixed
	 */
	private $emitList = [];

	/**
	 * @var array
	 */
	private $counts = [];

	protected int $totalErrors = 0;
	protected int $displayedErrors = 0;

	/**
	 * @var array
	 */
	private $silenced = [];

	/**
	 * XUnitOutput constructor.
	 *
	 * @param Config $config Instance of Config
	 * @guardrail-ignore Standard.Unknown.Property
	 */
	public function __construct(Config $config) {
		$this->doc = new JUnitXml\Document();
		$this->doc->formatOutput = true;
		$this->config = $config;
		$this->emitErrors = $config->getOutputLevel() == 1;
		$this->emitList = $config->getEmitList();

	}

	/**
	 * getClass
	 *
	 * @param string $className Class name
	 *
	 * @return JUnitXml\TestSuiteElement
	 */
	public function getClass($className) {
		if (!isset($this->suites[$className])) {
			$suite = $this->doc->addTestSuite();
			$suite->setName($className);
			$this->suites[$className] = $suite;
		}
		return $this->suites[$className];

	}

	/**
	 * incTests
	 *
	 * @return void
	 */
	public function incTests() {
		//$this->suite->addTestCase();
	}

	public function getErrorCounts() {
		return ["total"=>$this->totalErrors, "displayed"=>$this->displayedErrors];
	}

	/**
	 * getTypeCounts
	 *
	 * @return array
	 */
	public function getTypeCounts() {
		$count = [];
		$failures = $this->doc->getElementsByTagName("failure");
		foreach ($failures as $failure) {
			$type = $failure->getAttribute('type');
			$count[$type] = isset( $count[$type] ) ? $count[$type] + 1 : 1;
		}
		return $count;
	}

	/**
	 * emitPatternMatches
	 *
	 * @param string $name    The name
	 * @param string $pattern The pattern
	 *
	 * @return bool
	 */
	static public function emitPatternMatches($name, $pattern) {
		if (substr($pattern, -2) == '.*') {
			$start = substr($pattern, 0, -2);
			return (strpos($name, $start) === 0);
		} else {
			return $name == $pattern;
		}
	}

	/**
	 * shouldEmit
	 *
	 * @param string $fileName   The file name
	 * @param string $name       The name
	 * @param int    $lineNumber The line number the error occurred on.
	 *
	 * @return bool
	 */
	public function shouldEmit($fileName, $name, $lineNumber) {
		if (isset($this->silenced[$name]) && $this->silenced[$name] > 0) {
			return false;
		}
		foreach ($this->emitList as $entry) {
			if (
				is_array($entry)
			) {
				if (isset($entry['emit']) && !self::emitPatternMatches($name, $entry['emit'])) {
					continue;
				}
				if (isset($entry['glob']) && !Glob::match( "/" . $fileName, "/" . $entry['glob'])) {
					continue;
				}
				if (isset($entry['ignore']) && Glob::match("/" . $fileName, "/" . $entry['ignore'])) {
					continue;
				}
				if (
					isset($entry['when']) &&
					$entry['when'] == 'new' &&
					(
						!$this->config->getFilter() ||
						!$this->config->getFilter()->shouldEmit($fileName, $name, $lineNumber)
					)
				) {
					continue;
				}
				return true;
			} else if (is_string($entry) && self::emitPatternMatches($name, $entry)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * silenceType
	 *
	 * @param string $name The name
	 *
	 * @return void
	 */
	public function silenceType($name) {
		if (!isset($this->silenced[$name])) {
			$this->silenced[$name] = 1;
		} else {
			$this->silenced[$name]++;
		}
	}

	/**
	 * resumeType
	 *
	 * @param string $name The name
	 *
	 * @return void
	 */
	public function resumeType($name) {
		$this->silenced[$name]--;
	}

	/**
	 * emitError
	 *
	 * @param string $className  The class name
	 * @param string $fileName   The file name
	 * @param int    $lineNumber The line number
	 * @param string $name       The name
	 * @param string $message    The message
	 *
	 * @return void
	 */
	public function emitError($className, $fileName, $lineNumber, $name, $message="") {

		++$this->totalErrors;
		if (!$this->shouldEmit($fileName, $name, $lineNumber)) {
			return;
		}
		++$this->displayedErrors;

		$suite = $this->getClass($className);
		if (!isset($this->files[$className][$fileName])) {
			$case = $suite->addTestCase();
			$case->setName($fileName);
			$case->setClassname( $className );
			if (!isset($this->files[$className])) {
				$this->files[$className] = [];
			}
			$this->files[$className][$fileName] = $case;
		} else {
			$case = $this->files[$className][$fileName];
		}

		$message .= " on line " . $lineNumber;
		$case->addFailure($name . ":" . $message, "error");
		if ($this->emitErrors) {
			// echo "E";
		}
		if (!isset($this->counts[$name])) {
			$this->counts[$name] = 1;
		} else {
			++$this->counts[$name];
		}
		$this->outputExtraVerbose("ERROR: $fileName $lineNumber: $name: $message\n");
	}

	/**
	 * output
	 *
	 * @param string $verbose      The verbose output
	 * @param string $extraVerbose The extra verbose output
	 *
	 * @return void
	 */
	public function output($verbose, $extraVerbose) {
		if ($this->config->getOutputLevel() == 1) {
			echo $verbose;
			flush();
		} else if ($this->config->getOutputLevel() == 2) {
			echo $extraVerbose . "\n";
			flush();
		}
	}

	/**
	 * getCounts
	 *
	 * @return array
	 */
	public function getCounts() {
		return $this->counts;
	}

	/**
	 * outputVerbose
	 *
	 * @param string $string The output
	 *
	 * @return void
	 */
	public function outputVerbose($string) {
		if ($this->config->getOutputLevel() >= 1) {
			echo "\n".$string."\n";
			flush();
		}
	}

	/**
	 * outputExtraVerbose
	 *
	 * @param string $string The output
	 *
	 * @return void
	 */
	public function outputExtraVerbose($string) {
		if ($this->config->getOutputLevel() >= 2) {
			echo $string;
			flush();
		}
	}

	/**
	 * getErrorCount
	 *
	 * @return int
	 */
	public function getErrorCount() {
		$failures = $this->doc->getElementsByTagName("failure");
		return $failures->length;
	}

	/**
	 * renderResults
	 *
	 * @return void
	 */
	public function renderResults() {
		if ($this->config->getOutputFile()) {
			$this->doc->save($this->config->getOutputFile());
		} else {
			echo $this->doc->saveXml();
		}
		//print_r($this->getTypeCounts());
	}

	/**
	 * getErrorsByFile
	 *
	 * @return array
	 */
	public function getErrorsByFile() {
		$fileCount = [];
		$failures = $this->doc->getElementsByTagName("failure");
		for ($length = 0; $length < $failures->length; ++$length) {
			$item = $failures->item($length);
			$name = $item->parentNode->attributes->getNamedItem("name")->textContent;
			$fileCount[$name]++;
		}
		return $fileCount;
	}
}