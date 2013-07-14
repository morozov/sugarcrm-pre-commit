<?php
/**
 * Wrapper report for PHP_CodeSniffer.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Sergei Morozov <morozov@tut.by>
 * @copyright 2012 Sergei Morozov
 * @license   http://mit-license.org/ MIT Licence
 * @link      http://github.com/morozov/sugarcrm-pre-commit
 */

/**
 * @see PHP_CodeSniffer_Report
 */
require_once 'PHP/CodeSniffer/Report.php';

/**
 * Wrapper report for PHP_CodeSniffer.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Sergei Morozov <morozov@tut.by>
 * @copyright 2012 Sergei Morozov
 * @license   http://mit-license.org/ MIT Licence
 * @link      http://github.com/morozov/sugarcrm-pre-commit
 */
class PHP_CodeSniffer_Reports_Xml implements PHP_CodeSniffer_Report
{
    /**
     * The directory where source files reside.
     *
     * @var string
     */
    protected $srcDir;

    /**
     * The directory where temporary files reside.
     *
     * @var string
     */
    protected $tmpDir;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->srcDir = $this->getDir('PHPCS_SRC_DIR');
        $this->tmpDir = $this->getDir('PHPCS_TMP_DIR');
    }

    /**
     * Retrieves the directory path specified by environment variable.
     *
     * @param string $varName Environment variable name
     *
     * @return string
     * @throws PHP_CodeSniffer_Exception
     */
    protected function getDir($varName)
    {
        if (!isset($_SERVER[$varName])) {
            throw new PHP_CodeSniffer_Exception(
                $varName . ' environment variable is not set'
            );
        }

        $dir = realpath($_SERVER[$varName]);

        if (false === $dir || !is_dir($dir)) {
            throw new PHP_CodeSniffer_Exception(
                $varName . ' is not a directory'
            );
        }

        return $dir;
    }

    /**
     * Generate a partial report for a single processed file.
     *
     * Function should return TRUE if it printed or stored data about the file
     * and FALSE if it ignored the file. Returning TRUE indicates that the file and
     * its data should be counted in the grand totals.
     *
     * @param array   $report      Prepared report data.
     * @param boolean $showSources Show sources?
     * @param int     $width       Maximum allowed line width.
     *
     * @return boolean
     */
    public function generateFileReport(
        $report,
        $showSources=false,
        $width=80
    ) {
        $diff = $this->getStagedDiff();
        $changes = $this->getChanges($diff);

        $report = $this->filterReport($report, $changes);

        $full = new PHP_CodeSniffer_Reports_Full();
        return $full->generateFileReport($report, $showSources, $width);
    }

    /**
     * Prints all errors and warnings for each file processed.
     *
     * @param string  $cachedData    Any partial report data that was returned from
     *                               generateFileReport during the run.
     * @param int     $totalFiles    Total number of files processed during the run.
     * @param int     $totalErrors   Total number of errors found during the run.
     * @param int     $totalWarnings Total number of warnings found during the run.
     * @param boolean $showSources   Show sources?
     * @param int     $width         Maximum allowed line width.
     * @param boolean $toScreen      Is the report being printed to screen?
     *
     * @return void
     */
    public function generate(
        $cachedData,
        $totalFiles,
        $totalErrors,
        $totalWarnings,
        $showSources=false,
        $width=80,
        $toScreen=true
    ) {
        echo $cachedData;

        if ($toScreen === true
            && PHP_CODESNIFFER_INTERACTIVE === false
            && class_exists('PHP_Timer', false) === true
        ) {
            echo PHP_Timer::resourceUsage().PHP_EOL.PHP_EOL;
        }

    }//end generate()

    /**
     * Returns staged content diff
     *
     * @return string
     * @throws PHP_CodeSniffer_Exception
     */
    protected function getStagedDiff()
    {
        chdir($this->srcDir);

        ob_start();
        passthru(
            'git diff --staged --diff-filter=ACM', $return_var
        );
        $contents = ob_get_clean();
        if (0 !== $return_var) {
            throw new PHP_CodeSniffer_Exception(
                'Unable to get staged diff'
            );
        }
        return $contents;
    }

    /**
     * Parses diff and returns array containing affected paths and line numbers
     *
     * @param string $lines Diff output
     *
     * @return array
     */
    protected function getChanges($lines)
    {
        $lines = preg_split("/((\r?\n)|(\r\n?))/", $lines);
        $changes = array();
        $number = 0;
        $path = null;
        foreach ($lines as $line) {
            if (preg_match('~^\+\+\+\s(.*)~', $line, $matches)) {
                $path = substr($matches[1], strpos($matches[1], '/') + 1);
            } elseif (preg_match(
                '~^@@ -[0-9]+,[0-9]+? \+([0-9]+),[0-9]+? @@.*$~',
                $line,
                $matches
            )) {
                $number = (int) $matches[1];
            } elseif (preg_match('~^\+(.*)~', $line, $matches)) {
                $changes[$path][] = $number;
                $number++;
            } elseif (preg_match('~^[^-]+(.*)~', $line, $matches)) {
                $number++;
            }
        }
        return $changes;
    }

    /**
     * Filters report producing another one containing only changed lines
     *
     * @param array $report  Original report
     * @param array $changes Staged changes
     *
     * @return array
     */
    protected function filterReport(array $report, array $changes)
    {
        $filename = $report['filename'];
        if (strpos($filename, $this->tmpDir . DIRECTORY_SEPARATOR) === 0) {
            $relative = substr($filename, strlen($this->tmpDir) + 1);
            if (isset($changes[$relative])) {
                $report['filename'] = $relative;
                $report['messages'] = array_intersect_key(
                    $report['messages'],
                    array_flip($changes[$relative])
                );
            }
        }

        return $this->repairReport($report);
    }

    /**
     * Generates report from file data
     *
     * @param array $report File data
     *
     * @return array
     */
    protected function repairReport(array $report)
    {
        $repaired = array_merge($report, array(
            'errors'   => 0,
            'warnings' => 0,
        ));

        foreach ($report['messages'] as $columns) {
            foreach ($columns as $messages) {
                foreach ($messages as $message) {
                    switch($message['type']) {
                        case 'ERROR';
                            $key = 'errors';
                            break;
                        case 'WARNING';
                            $key = 'warnings';
                            break;
                        default;
                            $key = null;
                            continue;
                    }
                    $repaired[$key]++;
                }
            }
        }

        return $repaired;
    }
}
