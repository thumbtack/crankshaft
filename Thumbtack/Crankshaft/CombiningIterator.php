<?php

namespace Thumbtack\Crankshaft;

/**
 * Internal: Used to implement Crankshaft::Combine().
 */
class CombiningIterator implements \Iterator {
    private $key_iterator;
    private $value_iterator;

    public function __construct(\Iterator $key_iterator, \Iterator $value_iterator) {
        $this->key_iterator = $key_iterator;
        $this->value_iterator = $value_iterator;
    }

    public function rewind() {
        $this->key_iterator->rewind();
        $this->value_iterator->rewind();
    }

    public function current() {
        return $this->value_iterator->current();
    }

    public function key() {
        return $this->key_iterator->current();
    }

    public function valid() {
        return $this->key_iterator->valid() && $this->value_iterator->valid();
    }

    public function next() {
        $this->key_iterator->next();
        $this->value_iterator->next();
    }
}
