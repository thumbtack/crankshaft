<?php

namespace Thumbtack\Crankshaft\Test;

class PluckableProperties {
    private $data;

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function __get($name) {
        return $this->data[$name];
    }

    public function __isset($name) {
        return isset($this->data[$name]);
    }
}
