<?php

namespace Thumbtack\Crankshaft;

/**
 * Public: Wraps an iterator and gives each element sequential integer keys starting from 0.
 */
class SequentialKeyIterator implements \OuterIterator {
    private $iterator;
    private $position;

    public function __construct(\Iterator $iterator) {
        $this->iterator = $iterator;
    }

    public function rewind() {
        $this->iterator->rewind();
        $this->position = 0;
    }

    public function current() {
        return $this->iterator->current();
    }

    public function key() {
        return $this->position;
    }

    public function valid() {
        return $this->iterator->valid();
    }

    public function next() {
        $this->iterator->next();
        $this->position++;
    }

    public function getInnerIterator() {
        return $this->iterator;
    }
}
