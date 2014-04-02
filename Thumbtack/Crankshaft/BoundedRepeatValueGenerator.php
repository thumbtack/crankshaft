<?php

namespace Thumbtack\Crankshaft;

/**
 * Internal: Used to implement repeat().
 */
class BoundedRepeatValueGenerator extends RepeatValueGenerator implements \Countable {
    private $position;
    private $count;

    public function __construct($value, $count) {
        parent::__construct($value);
        $this->count = $count;
    }

    public function count() {
        return $this->count;
    }

    protected function setup() {
        parent::setup();

        $this->position = 0;
    }

    protected function advance() {
        if ($this->position < $this->count) {
            $this->position++;
            return parent::advance();
        } else {
            throw new GeneratorExhausted();
        }
    }
}
