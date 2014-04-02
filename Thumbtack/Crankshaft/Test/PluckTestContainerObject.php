<?php

namespace Thumbtack\Crankshaft\Test;

use Thumbtack\Crankshaft;

class PluckTestContainerObject implements Crankshaft\PropertyContainer {
    public $key;
    private $values;

    public function __construct(array $values) {
        $this->values = $values;
    }

    public function get($key) {
        return $this->values[$key];
    }
}
