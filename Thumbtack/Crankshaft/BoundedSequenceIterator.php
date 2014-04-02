<?php

namespace Thumbtack\Crankshaft;

/**
 * Internal: An iterator that implements Crankshaft::Range().
 */
class BoundedSequenceIterator extends SequenceIterator implements \Countable {
    private $stop;
    private $impossible;

    public function __construct($start, $stop, $step) {
        parent::__construct($start, $step);
        $this->stop = $stop;
        $this->impossible = (
            ($this->stop > $this->start && $this->step < 0) ||
            ($this->stop < $this->start && $this->step > 0)
        );
    }

    public function valid() {
        if ($this->impossible) {
            return false;
        } else {
            return $this->stop >= $this->start
                ? $this->current() < $this->stop
                : $this->current() > $this->stop;
        }
    }

    public function count() {
        return intval(($this->stop - $this->start) / $this->step);
    }
}
