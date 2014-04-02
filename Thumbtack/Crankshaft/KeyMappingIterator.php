<?php

namespace Thumbtack\Crankshaft;

/**
 * Internal: An iterator that implements map_keys().
 */
class KeyMappingIterator extends \IteratorIterator {
    private $map_fn;

    public function __construct($traversable, callable $map_fn) {
        parent::__construct($traversable);
        $this->map_fn = $map_fn;
    }

    public function key() {
        $iterator = $this->getInnerIterator();
        return call_user_func($this->map_fn, $iterator->key(), $iterator->current());
    }
}
