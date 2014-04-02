<?php

namespace Thumbtack\Crankshaft;

/**
 * Internal: An iterator that implements chain().
 */
class ChainingIterator implements \Iterator {
    private $chain;
    private $current_iterator;
    private $count;

    /**
     * Create a chaining iterator.
     *
     * $chain - An iterator of arrays or Traversable objects.
     */
    public function __construct(\Iterator $chain) {
        $this->chain = $chain;
    }

    public function rewind() {
        $this->chain->rewind();
        $this->count = 0;

        $this->update_current_iterator();
        while ($this->current_iterator && !$this->current_iterator->valid()) {
            $this->chain->next();
            $this->update_current_iterator();
        }
    }

    public function valid() {
        return $this->current_iterator !== null;
    }

    public function key() {
        return $this->count;
    }

    public function current() {
        return $this->valid() ? $this->current_iterator->current() : null;
    }

    public function next() {
        $this->current_iterator->next();
        $this->count++;

        while ($this->current_iterator && !$this->current_iterator->valid()) {
            $this->chain->next();
            $this->update_current_iterator();
        }
    }

    private function update_current_iterator() {
        $this->current_iterator = $this->chain->valid()
            ? Crankshaft::ToIterator($this->chain->current())
            : null;

        if ($this->current_iterator !== null) {
            $this->current_iterator->rewind();
        }
    }
}
