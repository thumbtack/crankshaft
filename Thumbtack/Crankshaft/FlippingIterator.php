<?php

namespace Thumbtack\Crankshaft;

/**
 * Internal: Used to implement flip().
 */
class FlippingIterator extends \IteratorIterator {
    public function current() {
        return parent::key();
    }

    public function key() {
        return parent::current();
    }
}
