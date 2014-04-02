<?php

namespace Thumbtack\Crankshaft;

/**
 * Internal: Used to implement cycle().
 */
class CyclingIterator implements \OuterIterator {
    private $iterator;
    private $count;

    public function __construct(\Iterator $iterator) {
        $this->iterator = $iterator;
    }

    public function rewind() {
        $this->iterator->rewind();
        $this->count = 0;
    }

    public function key() {
        return $this->count;
    }

    public function current() {
        return $this->iterator->current();
    }

    public function valid() {
        return $this->iterator->valid();
    }

    public function next() {
        $this->iterator->next();
        $this->count++;

        if (!$this->iterator->valid()) {
            $this->iterator->rewind();
        }
    }

    public function getInnerIterator() {
        return $this->iterator;
    }
}
