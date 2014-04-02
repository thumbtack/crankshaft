<?php

namespace Thumbtack\Crankshaft;

/**
 * Internal: An iterator that implements Crankshaft::Count().
 */
class SequenceIterator implements \SeekableIterator {
    protected $start;
    protected $step;

    private $value;
    private $position;

    public function __construct($start, $step) {
        $this->start = $start;
        $this->step = $step;
    }

    public function rewind() {
        $this->value = $this->start;
        $this->position = 0;
    }

    public function current() {
        return $this->value;
    }

    public function key() {
        return $this->position;
    }

    public function next() {
        $this->value += $this->step;
        $this->position++;
    }

    public function valid() {
        return true;
    }

    public function seek($position) {
        if (!is_integer($position) || $position < 0) {
            throw new \OutOfBoundsException('Cannot seek to ' . var_dump($position, true) . '.');
        }

        $old_position = $this->position;
        $old_value = $this->value;

        $this->position = $position;
        $this->value = $this->start + ($position * $this->step);

        if (!$this->valid()) {
            $this->position = $old_position;
            $this->value = $old_value;

            throw new \OutOfBoundsException(var_dump($position, true) . ' is out of bounds.');
        }
    }
}
