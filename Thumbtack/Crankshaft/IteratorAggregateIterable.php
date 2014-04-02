<?php

namespace Thumbtack\Crankshaft;

/**
 * Internal: Wraps an IteratorAggregate to make the Iterable methods available.
 */
class IteratorAggregateIterable extends Iterable {
    private $aggregate;

    public function __construct(\IteratorAggregate $aggregate) {
        $this->aggregate = $aggregate;
    }

    public function getIterator() {
        return $this->aggregate->getIterator();
    }
}
