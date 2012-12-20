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
     * Prints errors and warnings found only in lines modified in commit.
     *
     * Errors and warnings are displayed together, grouped by file.
     *
     * @param array   $report      Prepared report.
     * @param boolean $showSources Show sources?
     * @param int     $width       Maximum allowed lne width.
     * @param boolean $toScreen    Is the report being printed to screen?
     *
     * @return string
     */
    public function generate(
        $report,
        $showSources=false,
        $width=80,
        $toScreen=true
    ) {
        $diff = $this->getStagedDiff();
        $changes = $this->getChanges($diff);

        $report = $this->filterReport($report, $changes);

        $full = new PHP_CodeSniffer_Reports_Full();
        return $full->generate($report, $showSources, $width, $toScreen);
    }

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
        $files = array();
        foreach ($changes as $path => $lines) {
            $tmpPath = $this->tmpDir . DIRECTORY_SEPARATOR . $path;

            if (isset($report['files'][$tmpPath])) {
                $files[$path]['messages'] = array_intersect_key(
                    $report['files'][$tmpPath]['messages'],
                    array_flip($lines)
                );
            }
        }

        return $this->getReport($files);
    }

    /**
     * Generates report from file data
     *
     * @param array $files File data
     *
     * @return array
     */
    protected function getReport(array $files)
    {
        $totals = array(
            'warnings' => 0,
            'errors'   => 0,
        );

        foreach ($files as $path => $file) {
            $files[$path] = array_merge($file, $totals);
            foreach ($file['messages'] as $columns) {
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
                        $files[$path][$key]++;
                        $totals[$key]++;
                    }
                }
            }
        }

        return array(
            'totals' => $totals,
            'files'  => $files,
        );
    }
}
