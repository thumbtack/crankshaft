<?php

namespace Thumbtack\Crankshaft;

/**
 * Internal: An IteratorIterable that is also countable.
 */
class CountableIteratorIterable extends IteratorIterable implements \Countable {
    private $count;

    public function __construct($count, \Iterator $iterator) {
        parent::__construct($iterator);
        $this->count = $count;
    }

    public function count() {
        return $this->count;
    }
}
