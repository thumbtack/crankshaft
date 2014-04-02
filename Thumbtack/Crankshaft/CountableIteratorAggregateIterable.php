<?php

namespace Thumbtack\Crankshaft;

/**
 * Internal: An IteratorAggregateIterable that is also countable.
 */
class CountableIteratorAggregateIterable extends IteratorAggregateIterable implements \Countable {
    private $count;

    public function __construct($count, \IteratorAggregate $aggregate) {
        parent::__construct($aggregate);
        $this->count = $count;
    }

    public function count() {
        return $this->count;
    }
}
