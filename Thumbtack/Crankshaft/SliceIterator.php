<?php

namespace Thumbtack\Crankshaft;

/**
 * Internal: An iterator that implements slice().
 */
class SliceIterator extends \IteratorIterator implements \SeekableIterator {
    private $index_iterator;
    private $position;

    public function __construct(\SeekableIterator $index_iterator, $traversable) {
        parent::__construct($traversable);
        $this->index_iterator = $index_iterator;
        $this->position = null;
    }

    public function seek($index) {
        $this->index_iterator->seek($index);
        $this->update_position();
    }

    public function current() {
        return $this->getInnerIterator()->current();
    }

    public function key() {
        return $this->getInnerIterator()->key();
    }

    public function rewind() {
        $this->seek(0);
    }

    public function valid() {
        return $this->getInnerIterator()->valid() && $this->index_iterator->valid();
    }

    public function next() {
        $this->index_iterator->next();

        if ($this->index_iterator->valid()) {
            $this->update_position();
        }
    }

    private function update_position() {
        $desired = $this->index_iterator->current();
        $iterator = $this->getInnerIterator();

        if ($iterator instanceof \SeekableIterator) {
            try {
                $iterator->seek($desired);
            } catch (\OutOfBoundsException $e) {
                while ($iterator->valid()) {
                    $iterator->next();
                }
            }
        } else {
            if ($this->position === null || $desired < $this->position) {
                $iterator->rewind();
                $this->position = 0;
            }

            while ($iterator->valid() && $desired > $this->position) {
                $iterator->next();
                $this->position++;
            }
        }
    }
}
