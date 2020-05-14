<?php

require_once dirname(__DIR__) . '/vendor/phing/phing/classes/phing/Task.php';

class MyDiffTask extends Task
{
    /**
     * @var string
     */
    private $previousVersionSHA;

    /**
     * @var
     */
    private $currentVersionSHA;

    /**
     * @var array
     */
    private $startsWith = [];

    /**
     * @var array of pathes that should be excluded from the resulting archive
     */
    private $excludes = [];

    /**
     * Initialize the task
     */
    public function init()
    {
        require_once __DIR__ . '/MyTaskCommon.php';
        $this->startsWith = MyTaskCommon::$startsWith;
        $this->excludes = MyTaskCommon::$excludes;

        return true;
    }

    /**
     * Setter for the attribute "previousVersionSHA"
     *
     * @param  string  $version
     */
    public function setPreviousVersionSHA($version)
    {
        $this->previousVersionSHA = $version;
    }

    /**
     * Setter for the attribute "currentVersionSHA"
     *
     * @param  string  $version
     */
    public function setCurrentVersionSHA($version)
    {
        $this->currentVersionSHA = $version;
    }

    /**
     * Return if a given path should be included in diff result
     *
     * @param  string  $path
     * @return bool   true if the $path should be included in diff result
     */
    private function shouldInclude($path)
    {
        foreach ($this->startsWith as $needle) {
            if (strpos($path, $needle) === 0) {
                return false;
            }
        }

        foreach ($this->excludes as $needle2) {
            if (strpos($path, $needle2) > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create the 'changed-files' and 'removed-files' files
     */
    public function main()
    {
        $currentDir = getcwd();
        chdir(__DIR__ . '/../../../');
        exec('git config diff.renameLimit 999999');

        // Create the 'changed-files' file
        exec(sprintf('git diff --name-only --diff-filter=ACMR %s %s', $this->previousVersionSHA, $this->currentVersionSHA), $lines);
        $changedFiles = array_filter($lines, [$this, 'shouldInclude']);
        @file_put_contents('./public_html/docs/changed-files', implode("\n", $changedFiles) . "\n");

        // Create the 'removed-files' file
        unset($lines);
        exec(sprintf('git diff --name-only --diff-filter=R %s %s', $this->previousVersionSHA, $this->currentVersionSHA), $lines);
        $removedFiles = array_filter($lines, [$this, 'shouldInclude']);
        @file_put_contents('./public_html/docs/removed-files', implode("\n", $removedFiles) . "\n");

        exec('git config --unset diff.renameLimit');

        if ($currentDir !== false) {
            chdir($currentDir);
        }
    }
}
