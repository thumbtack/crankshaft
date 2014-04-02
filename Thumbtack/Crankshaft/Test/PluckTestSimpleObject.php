<?php

namespace Thumbtack\Crankshaft\Test;

class PluckTestSimpleObject {
    public function __construct(array $values) {
        foreach ($values as $key => $value) {
            $this->$key = $value;
        }
    }
}
