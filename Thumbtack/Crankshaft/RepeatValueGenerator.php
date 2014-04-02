<?php

namespace Thumbtack\Crankshaft;

/**
 * Internal: Used to implement repeat().
 */
class RepeatValueGenerator extends Generator {
    private $value;

    public function __construct($value) {
        $this->value = $value;
    }

    protected function setup() {
        // Nothing to set up.
    }

    protected function advance() {
        return $this->value;
    }
}
