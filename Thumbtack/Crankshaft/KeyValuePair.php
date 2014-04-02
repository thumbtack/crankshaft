<?php

namespace Thumbtack\Crankshaft;

/**
 * Public: A standard way to represent a key/value pair.
 */
class KeyValuePair {
    public $key;
    public $value;

    public function __construct($key, $value) {
        $this->key = $key;
        $this->value = $value;
    }
}
