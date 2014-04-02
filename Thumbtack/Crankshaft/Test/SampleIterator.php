<?php

namespace Thumbtack\Crankshaft\Test;

class SampleIterator implements \Iterator {
    private $key;
    private $count;

    public function rewind() {
        $this->key = 'a';
        $this->count = 0;
    }

    public function current() {
        return $this->count;
    }

    public function key() {
        return $this->key;
    }

    public function next() {
        $this->key++;
        $this->count++;
    }

    public function valid() {
        return $this->count < 5;
    }
}
