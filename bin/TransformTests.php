<?php

/*
Token transform unit test system

Purpose:
 During the porting of Parsoid to PHP, we need a system to capture
 and replay Javascript Parsoid token handler behavior and performance
 so we can duplicate the functionality and verify adequate performance.

 The transformerTest.js program works in concert with Parsoid and special
 capabilities added to the TokenTransformationManager.js file which
 now has token transformer test generation capabilities that produce test
 files from existing wiki pages or any wikitext. The Parsoid generated tests
 contain the specific handler name chosen for generation and the pipeline
 that was associated with the transformation execution. The pipeline ID
 is used by transformTest.js to properly order the replaying of the
 transformers input and output sequencing for validation.

 Manually written tests are supported and use a slightly different format
 which more closely resembles parserTest.txt and allows the test writer
 to identify each test with a unique description and combine tests
 for different token handlers in the same file, though only one handlers
 code can be validated and performance timed.

Technical details:
 The test validator and handler runtime emulates the normal
 Parsoid token transform manager behavior and handles tests sequences that
 were generated by multiple pipelines and uses the pipeline ID to call
 the transformers in sorted execution order to deal with parsoids
 execution order not completing each pipelined sequence in order.
 The system utilizes the transformers initialization code to install handler
 functions in a generalized way and run the test without specific
 transformer bindings.

 To create a test from an existing wikitext page, run the following
 commands, for example:
 $ node bin/parse.js --genTest QuoteTransformer,quoteTestFile.txt
 --pageName 'skating' < /dev/null > /tmp/output

 For command line options and required parameters, type:
 $ node bin/transformerTest.js --help

 An example command line to validate and performance test the 'skating'
 wikipage created as a QuoteTransformer test:
 $ node bin/transformTests.js --log --QuoteTransformer --inputFile quoteTestFile.txt

 TokenStreamPatcher, BehaviorSwitchHandler and SanitizerHandler are
 implemented but may need further debugging and manual tests written.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Parsoid\Tests\MockEnv;
use Parsoid\Tokens\Token;
use Parsoid\Utils\PHPUtils;

$wgCachedState = false;
$wgCachedTestLines = '';
$wgCachedPipeLines = '';
$wgCachedPipeLinesLength = [];

// Mock environment for token transformers
class TransformTests {
	public $t;
	public $env;

	/**
	 * Constructor
	 *
	 * @param object $env
	 * @param object $options
	 */
	public function __construct( $env, $options ) {
		$this->env = $env;
		$this->pipelineId = 0;
		$this->options = $options;
		$this->tokenTime = 0;
	}

	/**
	 * Process test file
	 *
	 * @param object $transformer
	 * @param string $transformerName name of the transformer being tested
	 * @param object $commandLine
	 * @return number
	 */
	public function processTestFile( $transformer, $transformerName, $commandLine ) {
		global $wgCachedState;
		global $wgCachedTestLines;
		$numPasses = 0;
		$numFailures = 0;

		if ( isset( $commandLine['timingMode'] ) ) {
			if ( $wgCachedState === false ) {
				$wgCachedState = true;
				$testFile = file_get_contents( $commandLine['inputFile'] );
				$testLines = explode( "\n", $testFile );
				$wgCachedTestLines = $testLines;
			} else {
				$testLines = $wgCachedTestLines;
			}
		} else {
			$testFile = file_get_contents( $commandLine['inputFile'] );
			$testLines = explode( "\n", $testFile );
		}

		$countTestLines = count( $testLines );
		$testEnabled = true;
		$input = [];
		for ( $index = 0; $index < $countTestLines; $index++ ) {
			$line = $testLines[$index];
			if ( strlen( $line ) === 0 ) {
				continue;
			}
			switch ( $line[0] ) {
				case '#':	// comment line
				case ' ':	// blank character at start of line
				case '':		// empty line
					break;
				case ':':
					$testEnabled = preg_replace( '/^:\s*|\s*$/D', '', $line ) === $transformerName;
					break;
				case '!':	// start of test with name
					$testName = substr( $line, 2 );
					break;
				case '[':	// desired result json string for test result verification
					if ( !$testEnabled ) {
						break;
					}

					$result = $transformer->processTokensSync( $this->env, $input, [] );
					$stringResult = PHPUtils::jsonEncode( $result );
					# print "SR  : $stringResult\n";
					# print "LINE: $line\n";
					$line = preg_replace( '/{}/', '[]', $line );
					$stringResult = preg_replace( '/{}/', '[]', $stringResult );
					// remove false \\ from sequences like <\\/includeonly> and <includeonly \\/>
					// $stringResult = preg_replace( '/(\\\\)+\//', '/', $stringResult );
					if ( $stringResult === $line ) {
						$numPasses++;
						if ( empty( $commandLine['timingMode'] ) &&
							!empty( $commandLine['verbose'] )
						) {
							print $testName . " ==> passed\n";
						}
					} else {
						$numFailures++;
						print $testName . " ==> failed\n";
						print "line to debug => " . $line . "\n";
						print "result line ===> " . $stringResult . "\n";
					}
					$input = [];
					break;
				case '{':
				default:
					if ( !$testEnabled ) {
						break;
					}

					# print "PROCESSING $line\n";
					$input[] = Token::getToken( PHPUtils::jsonDecode( $line ) );
					break;
			}
		}

		return [ "passes" => $numPasses, "fails" => $numFailures ];
	}

	/**
	 * Create emulation of process pipelines
	 * Because tokens are processed in pipelines which can execute out of
	 * order, the unit test system creates an array of arrays to hold
	 * the pipeline ID which was used to process each token.
	 * The processWikitextFile function uses the pipeline IDs to ensure
	 * that all token processing for each pipeline occurs in order to completion.
	 *
	 * @param array $lines
	 * @return array
	 */
	private static function createPipelines( $lines ) {
		$numberOfTextLines = count( $lines );
		$maxPipelineID = 0;
		$LineToPipeMap = [];
		$LineToPipeMap = array_pad( $LineToPipeMap, $numberOfTextLines, 0 );
		for ( $i = 0; $i < $numberOfTextLines; ++$i ) {
			preg_match( '/(\d+)/', substr( $lines[$i], 0, 4 ), $matches );
			if ( count( $matches ) > 0 ) {
				$pipe = $matches[0];
				if ( $maxPipelineID < $pipe ) {
					$maxPipelineID = $pipe;
				}
			} else {
				$pipe = NAN;
			}
			$LineToPipeMap[$i] = $pipe;
		}
		$pipelines = [];
		$pipelines = array_pad( $pipelines, $maxPipelineID + 1, [] );
		for ( $i = 0; $i < $numberOfTextLines; ++$i ) {
			$pipe = $LineToPipeMap[$i];
			if ( !is_nan( $pipe ) ) {
				$pipelines[$pipe][] = $i;
			}
		}
		return $pipelines;
	}

	/**
	 * Process wiki test file
	 * Use the TokenTransformManager.js guts (extracted essential functionality)
	 * to dispatch each token to the registered token transform function
	 *
	 * @param object $transformer
	 * @param object $commandLine
	 * @return number
	 */
	public function processWikitextFile( $transformer, $commandLine ) {
		global $wgCachedState;
		global $wgCachedTestLines;
		global $wgCachedPipeLines;
		global $wgCachedPipeLinesLength;
		$numPasses = 0;
		$numFailures = 0;

		if ( isset( $commandLine['timingMode'] ) ) {
			if ( $wgCachedState === false ) {
				$wgCachedState = true;
				$testFile = file_get_contents( $commandLine['inputFile'] );
				$testLines = explode( "\n", $testFile );
				$pipeLines = self::createPipelines( $testLines );
				$numPipelines = count( $pipeLines );
				$wgCachedTestLines = $testLines;
				$wgCachedPipeLines = $pipeLines;
				$wgCachedPipeLinesLength = $numPipelines;
			} else {
				$testLines = $wgCachedTestLines;
				$pipeLines = $wgCachedPipeLines;
				$numPipelines = $wgCachedPipeLinesLength;
			}
		} else {
			$testFile = file_get_contents( $commandLine['inputFile'] );
			$testLines = explode( "\n", $testFile );
			$pipeLines = self::createPipelines( $testLines );
			$numPipelines = count( $pipeLines );
		}

		for ( $i = 0; $i < $numPipelines; $i++ ) {
			if ( !isset( $pipeLines[$i] ) ) {
				continue;
			}

			$this->pipelineId = $i;
			$p = $pipeLines[$i];
			$pLen = count( $p );
			$input = [];
			for ( $j = 0; $j < $pLen; $j++ ) {
				preg_match( '/^.*(IN|OUT)\s*\|\s*(.*)$/D', $testLines[$p[$j]], $matches );
				$isInput = $matches[1] === 'IN';
				$line = $matches[2];
				if ( $isInput ) {
					$input[] = Token::getToken( PHPUtils::jsonDecode( $line ) );
				} else {

					// Allow debugger breaking on a specific line in a test file
					if ( !empty( $commandLine['breakLine'] ) ) {
						$lineToBreakOn = intval( $commandLine['breakLine'] );
						$lineNumber = $p[$j] + 1;
						if ( $lineToBreakOn === $lineNumber ) {
							$tempValue = 0; // Set breakpoint here <=======
						}
					}

					$result = $transformer->processTokensSync( $this->env, $input, [] );

					// desired result json string for test result verification
					$stringResult = PHPUtils::jsonEncode( $result );
					$line = preg_replace( '/{}/', '[]', $line );
					$stringResult = preg_replace( '/{}/', '[]', $stringResult );
					// remove false \\ from sequences like <\\/includeonly> and <includeonly \\/>
					// $stringResult = preg_replace( '/(\\\\)+\//', '/', $stringResult );
					if ( $stringResult === $line ) {
						$numPasses++;
						if ( empty( $commandLine['timingMode'] ) &&
							!empty( $commandLine['verbose'] )
						) {
							print "line " . ( $p[$j] + 1 ) . " ==> passed\n";
						}
					} else {
						$numFailures++;
						print "line " . ( $p[$j] + 1 ) . " ==> failed\n";
						print "line to debug => " . $line . "\n";
						print "result line ===> " . $stringResult . "\n";
					}
					$input = [];
				}
			}
		}

		return [ "passes" => $numPasses, "fails" => $numFailures ];
	}

	/**
	 * Process unit test file
	 *
	 * @param object $tokenTransformer
	 * @param string $transformerName name of the transformer being tested
	 * @param object $commandLine
	 * @return number
	 */
	public function unitTest( $tokenTransformer, $transformerName, $commandLine ) {
		if ( !isset( $commandLine['timingMode'] ) ) {
			print "Starting stand alone unit test running file " .
				$commandLine['inputFile'] . "\n";
		}
		$results = $this->processTestFile( $tokenTransformer,
			$transformerName, $commandLine );
		if ( !isset( $commandLine['timingMode'] ) ) {
			print "Ending stand alone unit test running file " .
				$commandLine['inputFile'] . "\n";
		}
		return $results;
	}

	/**
	 * Process wiki text test file
	 *
	 * @param object $tokenTransformer
	 * @param object $commandLine
	 * @return number
	 */
	public function wikitextTest( $tokenTransformer, $commandLine ) {
		if ( !isset( $commandLine['timingMode'] ) ) {
			print "Starting stand alone wikitext test running file " .
				$commandLine['inputFile'] . "\n";
		}
		$results = $this->processWikitextFile(
			$tokenTransformer, $commandLine );
		if ( !isset( $commandLine['timingMode'] ) ) {
			print "Ending stand alone wikitext test running file " .
				$commandLine['inputFile'] . "\n";
		}
		return $results;
	}
}

/**
 * Select test type of unit test or wiki text test
 *
 * @param array $commandLine
 * @param object $manager
 * @param string $transformerName name of the transformer being tested
 * @param object $handler
 * @return number
 */
function wfSelectTestType( $commandLine, $manager, $transformerName, $handler ) {
	$i = 1;
	if ( isset( $commandLine['timingMode'] ) ) {
		if ( isset( $commandLine['iterationCount'] ) ) {
			$i = $commandLine['iterationCount'];
		} else {
			$i = 10000;  // defaults to 10000 iterations
		}
	}
	while ( $i-- ) {
		if ( isset( $commandLine['manual'] ) ) {
			$results = $manager->unitTest( $handler, $transformerName, $commandLine );
		} else {
			$results = $manager->wikitextTest( $handler, $commandLine );
		}
	}
	return $results;
}

/**
 * ProcessArguments handles a subset of javascript yargs like processing for command line
 * parameters setting object elements to the key name. If no value follows the key,
 * it is set to true, otherwise it is set to the value. The key can be followed by a
 * space then value, or an equals symbol then the value.
 *
 * @param number $argc
 * @param array $argv
 * @return array
 */
function wfProcessArguments( int $argc, array $argv ): array {
	$opts = [];
	$last = false;
	for ( $i = 1; $i < $argc; $i++ ) {
		$text = $argv[$i];
		if ( '--' === substr( $text, 0, 2 ) ) {
			$assignOffset = strpos( $text, '=', 3 );
			if ( $assignOffset === false ) {
				$key = substr( $text, 2 );
				$last = $key;
				$opts[$key] = true;
			} else {
				$value = substr( $text, $assignOffset + 1 );
				$key = substr( $text, 2, $assignOffset - 2 );
				$last = false;
				$opts[$key] = $value;
			}
		} elseif ( $last ) {
			$opts[$last] = $text;
			$last = false;
		} else {
			// There are no free args supported right now
			// So, keep things simple
			print "Unknown arg " . $argv[$i] . "\n";
			exit( 1 );
		}
	}
	return $opts;
}

/**
 * Run tests as specified by commmand line arguments
 *
 * @param number $argc
 * @param array $argv
 * @return number
 */
function wfRunTests( $argc, $argv ) {
	$opts = wfProcessArguments( $argc, $argv );

	if ( isset( $opts['help'] ) ) {
		print "must specify [--manual] [--log] [--breakLine 123] [--timingMode] [--verbose]" .
			" [--iterationCount=XXX] --TransformerName --inputFile /path/filename\n";
		exit( 1 );
	}

	if ( !isset( $opts['inputFile'] ) ) {
		print "must specify [--manual] [--log] --transformer NAME" .
			" --inputFile /path/filename\n";
		print "Run 'node bin/transformerTests.js --help' for more information\n";
		exit( 1 );
	}

	// look for the wikitext source file in the same path with a .wt file extension
	// and load that so transformers that reference the wikitext source have the actual text.
	$fileName = preg_replace( "/\.[^.]+$/D", "", $opts['inputFile'] ) . '.wt';
	if ( file_exists( $fileName ) ) {
		$testFileWt = file_get_contents( $fileName );
		$mockEnv = new MockEnv( array_merge( $opts, [ 'pageContent' => $testFileWt ] ) );
	} else {
		$mockEnv = new MockEnv( $opts );
	}

	$manager = new TransformTests( $mockEnv, [] );

	if ( isset( $opts['timingMode'] ) ) {
		print "Timing Mode enabled, no console output expected till test completes\n";
	}

	$startTime = PHPUtils::getStartHRTime();

	$transformer = $opts['transformer'] ?? '';
	$results = [];
	if ( $transformer === 'QuoteTransformer' ) {
		$qt = new Parsoid\Wt2Html\TT\QuoteTransformer( $manager, [] );
		$results = wfSelectTestType( $opts, $manager, "QuoteTransformer", $qt );
	} elseif ( $transformer === 'ParagraphWrapper' ) {
		$pw = new Parsoid\Wt2Html\TT\ParagraphWrapper( $manager, [] );
		$results = wfSelectTestType( $opts, $manager, "ParagraphWrapper", $pw );
	} elseif ( $transformer === 'PreHandler' ) {
		$pw = new Parsoid\Wt2Html\TT\PreHandler( $manager, [] );
		$results = wfSelectTestType( $opts, $manager, "PreHandler", $pw );
	} elseif ( $transformer === 'BehaviorSwitchHandler' ) {
		$pw = new Parsoid\Wt2Html\TT\BehaviorSwitchHandler( $manager, [] );
		$results = wfSelectTestType( $opts, $manager, "BehaviorSwitchHandler", $pw );
	} elseif ( $transformer === 'ListHandler' ) {
		$pw = new Parsoid\Wt2Html\TT\ListHandler( $manager, [] );
		$results = wfSelectTestType( $opts, $manager, "ListHandler", $pw );
	} elseif ( $transformer === 'NoInclude' ) {
		$pw = new Parsoid\Wt2Html\TT\NoInclude( $manager, [ 'isInclude' => false ] );
		$results = wfSelectTestType( $opts, $manager, 'NoInclude', $pw );
	} elseif ( $transformer === 'IncludeOnly' ) {
		$pw = new Parsoid\Wt2Html\TT\IncludeOnly( $manager, [ 'isInclude' => false ] );
		$results = wfSelectTestType( $opts, $manager, 'IncludeOnly', $pw );
	} elseif ( $transformer === 'OnlyInclude' ) {
		$pw = new Parsoid\Wt2Html\TT\OnlyInclude( $manager, [ 'isInclude' => false ] );
		$results = wfSelectTestType( $opts, $manager, 'OnlyInclude', $pw );
	} elseif ( $transformer === 'Sanitizer' ) {
		$pw = new Parsoid\Wt2Html\TT\Sanitizer( $manager, [ 'inTemplate' => false ] );
		$results = wfSelectTestType( $opts, $manager, "Sanitizer", $pw );
	} elseif ( $transformer === 'TokenStreamPatcher' ) {
		$pw = new Parsoid\Wt2Html\TT\TokenStreamPatcher( $manager, [ 'inTemplate' => false ] );
		$pw->resetState( [ 'toplevel' => true ] );
		$results = wfSelectTestType( $opts, $manager, "TokenStreamPatcher", $pw );
	}
	/*
	} else if ($opts->TokenStreamPatcher) {
		var tsp = new TokenStreamPatcher(manager, {});
		wfSelectTestType(argv, manager, tsp);
	} */ else {
		print 'No valid TransformerName was specified\n';
		exit( 1 );
}

	$totalTime = PHPUtils::getHRTimeDifferential( $startTime );
	print 'Total transformer execution time = ' . $totalTime . " milliseconds\n";
	print 'Total time processing tokens     = ' . round( $manager->tokenTime, 3 ) .
		" milliseconds\n";
	print "----------------------\n";
	print 'Total passes   : ' . $results['passes'] . "\n";
	print 'Total failures : ' . $results['fails'] . "\n";
	print "----------------------\n";
	if ( $results['fails'] > 0 ) {
		print 'Total failures: ' . $results['fails'];
		exit( 1 );
	}
}

wfRunTests( $argc, $argv );
