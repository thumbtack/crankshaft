<?php

require_once('Thumbtack/Crankshaft/register_globals.php');
require_once('vendor/autoload.php');

use Thumbtack\Crankshaft;

require_once('vendor/simpletest/simpletest/autorun.php');

class AllTests extends TestSuite {
    public function __construct() {
        parent::__construct('All tests');

        $pattern = '*Test.php';
        $test_directory = dirname(__FILE__) . '/Thumbtack/Crankshaft/Test';

        $dir_iterator = new \DirectoryIterator($test_directory);
        foreach ($dir_iterator as $info) {
            if (fnmatch($pattern, $info->getFilename())) {
                $this->addFile($info->getPathname());
            }
        }
    }
}
