<?php

namespace Thumbtack\Crankshaft;

/**
 * Internal: An iterator that implements map().
 */
class MappingIterator extends \IteratorIterator {
    private $map_fn;

    public function __construct($traversable, $map_fn) {
        Crankshaft::AssertCallable($map_fn);

        parent::__construct($traversable);
        $this->map_fn = $map_fn;
    }

    public function current() {
        $iterator = $this->getInnerIterator();
        return call_user_func($this->map_fn, $iterator->current(), $iterator->key());
    }
}
