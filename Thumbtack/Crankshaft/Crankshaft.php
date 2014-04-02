<?php

namespace Thumbtack\Crankshaft;

class Crankshaft {
    static public function AssertCallable($callable) {
        if (!is_callable($callable)) {
            throw new \InvalidArgumentException(
                'Expected callable but got non-callable ' . var_dump($callable, true) . '.'
            );
        }
    }

    /**
     * Make an iterable that yields elements from the first iterable until it is exhausted, then
     * from the second iterable, and so on until they are all exhausted. Used for treating
     * consecutive sequences as a single sequence. Keys from the underlying traversables are necessarily
     * **not** preserved.
     *
     * $traversables - Any number of arrays or Traversable objects.
     *
     * Examples
     *
     *   Crankshaft::Chain([1, 2, 3], [4, 5], [6, 7])->to_array()
     *   # => [1, 2, 3, 4, 5, 6, 7]
     *
     * See also
     *
     *   Iterable->chain() - creates a chain from an Iterable of arrays or Traversable objects
     *
     * Returns an Iterable.
     */
    public static function Chain(/* $traversables... */) {
        return static::Iter(func_get_args())->chain();
    }

    public static function Compare($a, $b) {
        if ($a > $b) {
            return 1;
        } elseif ($b > $a) {
            return -1;
        } else {
            return 0;
        }
    }

    /**
     * Make an iterable that will take its keys from one traversable and its values from another.
     *
     * If the two traversables are different lengths, the returned iterable will stop when the shorter
     * of the two stops.
     *
     * $keys   - An array or a Traversable object.
     * $values - An array or a Traversable object.
     *
     * Examples
     *
     *   Crankshaft::Combine(['a', 'b', 'c'], [97, 98, 99])->to_array()
     *   // => ['a' => 97, 'b' => 98, 'c' => 99]
     *
     * Returns an Iterable.
     */
    public static function Combine($keys, $values) {
        return static::Iter(
            new CombiningIterator(
                static::ToIterator($keys),
                static::ToIterator($values)
            )
        );
    }

    /**
     * Generate an infinite numeric sequence.
     *
     * $start - The number to start at (default: 0)
     * $step  - The separation between values (default: 1)
     *
     * Examples
     *
     *   Crankshaft::Count()
     *   // => 0, 1, 2, 3, 4, 5, 6, ...
     *
     *   Crankshaft::Count(12)
     *   // => 12, 13, 14, 15, 16, ...
     *
     *   Crankshaft::Count(0, -1)
     *   // => 0, -1, -2, -3, -4, -5, -6, ...
     *
     * Returns an Iterable that will yield each value in the sequence.
     */
    public static function Count($start=0, $step=1) {
        return static::Iter(new SequenceIterator($start, $step));
    }

    /**
     * Public: Test if a container holds a value for a particular key.
     *
     * Key comparisons are made using **loose** equality (`==`), in order to match the behavior of
     * array_key_exists.
     *
     * $container - An array, ArrayAccess object, or Traversable object.
     * $key       - A scalar which may be a key in the container.
     *
     * Examples
     *
     *   Crankshaft::HasKey(['a' => 1, 'b' => 2, 'c' => 3], 'b')
     *   // => true
     *
     *   Crankshaft::HasKey(['a' => 1, 'b' => 2, 'c' => 3], 'd')
     *   // => false
     *
     *   Crankshaft::HasKey(['a', 'b', 'c', 'd'], '1')
     *   // => true
     *
     * Returns true if the key was found in the container; false if otherwise.
     * Throws an NotTraversableError if the given argument is not traversable.
     */
    public static function HasKey($container, $key) {
        if (is_array($container)) {
            return array_key_exists($key, $container);
        } else if (is_object($container) && $container instanceof \ArrayAccess) {
            return $container->offsetExists($key);
        } else {
            return static::Iter($container)->has_key($key);
        }
    }

    public static function Identity($v) {
        return $v;
    }

    /**
     * Public: Wrap any array or traversable object in an object that extends Iterable.
     *
     * Examples
     *
     *   Crankshaft::Iter([1, 2, 3, 4]) instanceof Iterable
     *   // => true
     *
     * Returns an Iterable. If the underlying traversable object is countable, the returned
     *   Iterable will also be countable.
     * Throws an NotTraversableError if the given argument is not traversable.
     */
    public static function Iter($traversable) {
        if ($traversable instanceof \Iterable) {
            return $traversable;
        } else if (is_array($traversable)) {
            return new ArrayIterable($traversable);
        } else if (is_object($traversable) && $traversable instanceof \Iterator) {
            return $traversable instanceof \Countable
                ? new CountableIteratorIterable(count($traversable), $traversable)
                : new IteratorIterable($traversable);
        } else if (is_object($traversable) && $traversable instanceof \IteratorAggregate) {
            return $traversable instanceof \Countable
                ? new CountableIteratorAggregateIterable(
                    count($traversable),
                    $traversable
                )
                : new IteratorAggregateIterable($traversable);
        } else {
            throw new NotTraversableError(
                var_dump($traversable, true) . ' is not traversable.'
            );
        }
    }

    /**
     * Generate a bounded numeric sequence.
     *
     * $start - The number to start at (default: 0)
     * $stop  - The number that the sequence will stop before (but will not include).
     * $step  - The separation between values (default: 1)
     *
     * Examples
     *
     *   Crankshaft::Range(4)->to_array()
     *   // => [0, 1, 2, 3]
     *
     *   Crankshaft::Range(1, 6)->to_array()
     *   // => [1, 2, 3, 4, 5]
     *
     *   Crankshaft::Range(0, 7, 2)->to_array()
     *   // => [0, 2, 4, 6]
     *
     *   Crankshaft::Range(5, -1, -1)->to_array()
     *   // => [5, 4, 3, 2, 1, 0]
     *
     * Returns an Iterable that will yield each value in the sequence.
     */
    public static function Range($start, $stop=null, $step=1) {
        if (func_num_args() < 2) {
            $stop = $start;
            $start = 0;
        }

        return static::Iter(new BoundedSequenceIterator($start, $stop, $step));
    }

    /**
     * Make an iterable that will repeatedly yield the given single value, either infinitely or up to
     * `$count` times.
     *
     * $value - The value to repeat in the returned iterable.
     * $count - The integer maximum number of times to repeat the value (default: infinitely).
     *
     * Examples
     *
     *   Crankshaft::Repeat('a', 5)->to_array()
     *   // => ['a', 'a', 'a', 'a', 'a']
     *
     *   Crankshaft::Repeat('z')
     *   // => 'z', 'z', 'z', 'z', 'z', 'z', 'z', 'z', 'z', 'z', ...
     *
     * Returns an Iterable.
     */
    public static function Repeat($value, $count=null) {
        $generator = $count === null
            ? new RepeatValueGenerator($value)
            : new BoundedRepeatValueGenerator($value, $count);

        return static::Iter($generator);
    }

    public static function Set($items=null) {
        return new Set($items);
    }

    public static function ToBool($value) {
        return $value ? true : false;
    }

    /**
     * Internal: Create an Iterator for an array or traversable object, unless it is already an
     * iterator.
     *
     * $traversable - An array or an Iterator or IteratorAggregate object
     *
     * Returns an Iterator.
     * Throws an NotTraversableError if the given argument is not traversable.
     */
    function ToIterator($traversable) {
        if (is_array($traversable)) {
            return new \ArrayIterator($traversable);
        } else if (is_object($traversable) && $traversable instanceof \Iterator) {
            return $traversable;
        } else if (is_object($traversable) && $traversable instanceof \IteratorAggregate) {
            return $traversable->getIterator();
        } else {
            throw new NotTraversableError(
                var_dump($traversable, true) . ' must be an array, Iterator, or IteratorAggregate.'
            );
        }
    }

    /**
     * Make an iterable that iterates over several other traversables in parallel, yielding an array
     * at each step that contains the current values from the underlying traversables.
     *
     * The returned iterable will end when the shortest underlying iterable ends, and additional values
     * from any underlying traversables that were longer will not be yielded.
     *
     * $traversables - Any number of arrays or Traversable objects.
     *
     * Examples
     *
     *   Crankshaft::Zip(
     *       [1, 2, 3, 4],
     *       ['a', 'b', 'c']
     *   )->to_array()
     *   // => [[1, 'a'], [2, 'b'], [3, 'c']]
     *
     * Returns an Iterable.
     */
    public static function Zip(/* $traversables... */) {
        $iterators = array_map('Thumbtack\Crankshaft\Crankshaft::ToIterator', func_get_args());
        return static::Iter(new ZipGenerator($iterators, false));
    }

    /**
     * Make an iterable that iterates over several other traversables in parallel, yielding an array
     * at each step that contains the current values from the underlying traversables.
     *
     * The returned iterable will not end until the longest underlying iterable ends. Traversables that
     * end earlier than that will have their spot(s) in the yielded arrays replaced with `$fill`.
     *
     * $fill      - The value to yield in the position of any underlying traversables that have reached
     *              their end.
     * $traversables - Any number of arrays or Traversable objects.
     *
     * Examples
     *
     *   Crankshaft::ZipLongest(
     *       '-',
     *       [1, 2, 3, 4],
     *       ['a', 'b', 'c']
     *   )->to_array()
     *   // => [[1, 'a'], [2, 'b'], [3, 'c'], [4, '-']]
     *
     * Returns an Iterable.
     */
    public static function ZipLongest($fill /* , $traversables... */) {
        $iterators = array_map(
            'Thumbtack\Crankshaft\Crankshaft::ToIterator',
            array_slice(func_get_args(), 1)
        );
        return static::Iter(new ZipGenerator($iterators, true, $fill));
    }
}
