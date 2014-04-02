<?php

namespace Thumbtack\Crankshaft;

/**
 * Internal: An iterator that implements filter().
 */
class FilteringIterator extends \FilterIterator {
    private $filter_fn;

    public function __construct(\Iterator $iterator, $filter_fn) {
        Crankshaft::AssertCallable($filter_fn);

        parent::__construct($iterator);
        $this->filter_fn = $filter_fn;
    }

    public function accept() {
        $iterator = $this->getInnerIterator();
        return call_user_func($this->filter_fn, $iterator->current(), $iterator->key());
    }
}
