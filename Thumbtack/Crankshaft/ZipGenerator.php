<?php

namespace Thumbtack\Crankshaft;

/**
 * Internal: Used to implement zip() and zip_longest().
 */
class ZipGenerator extends Generator {
    private $iterators;
    private $longest;
    private $fill;

    public function __construct(array $iterators, $longest, $fill=null) {
        $this->iterators = $iterators;
        $this->longest = $longest;
        $this->fill = $fill;
    }

    protected function setup() {
        foreach ($this->iterators as $iterator) {
            $iterator->rewind();
        }
    }

    protected function advance() {
        $invalid = 0;
        $values = [];

        foreach ($this->iterators as $iterator) {
            if ($iterator->valid()) {
                $values[] = $iterator->current();
                $iterator->next();
            } else {
                $invalid++;
                $values[] = $this->fill;
            }
        }

        $done = $this->longest ? $invalid == count($this->iterators) : $invalid > 0;
        if ($done) {
            throw new GeneratorExhausted();
        } else {
            return $values;
        }
    }
}
