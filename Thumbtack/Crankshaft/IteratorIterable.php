<?php

namespace Thumbtack\Crankshaft;

/**
 * Internal: Wraps an Iterator to make the Iterable methods available.
 */
class IteratorIterable extends Iterable {
    private $iterator;

    public function __construct(\Iterator $iterator) {
        $this->iterator = $iterator;
    }

    public function getIterator() {
        return $this->iterator;
    }
}
