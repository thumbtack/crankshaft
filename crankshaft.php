<?php

require_once('lib/Crankshaft.php');

/**
 * Public: Wrap any array or traversable object in an object that extends Iterable.
 *
 * Examples
 *
 *   tt_iter([1, 2, 3, 4]) instanceof Crankshaft\Iterable
 *   // => true
 *
 * Returns an Iterable. If the underlying traversable object is countable, the returned
 *   Iterable will also be countable.
 * Throws an Crankshaft\NotTraversableError if the given argument is not traversable.
 */
function tt_iter($traversable) {
    if ($traversable instanceof \Iterable) {
        return $traversable;
    } else if (is_array($traversable)) {
        return new Crankshaft\ArrayIterable($traversable);
    } else if (is_object($traversable) && $traversable instanceof \Iterator) {
        return $traversable instanceof \Countable
            ? new Crankshaft\CountableIteratorIterable(count($traversable), $traversable)
            : new Crankshaft\IteratorIterable($traversable);
    } else if (is_object($traversable) && $traversable instanceof \IteratorAggregate) {
        return $traversable instanceof \Countable
            ? new Crankshaft\CountableIteratorAggregateIterable(
                count($traversable),
                $traversable
            )
            : new Crankshaft\IteratorAggregateIterable($traversable);
    } else {
        throw new Crankshaft\NotTraversableError(
            var_dump($traversable, true) . ' is not traversable.'
        );
    }
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
 *   tt_has_key(['a' => 1, 'b' => 2, 'c' => 3], 'b')
 *   // => true
 *
 *   tt_has_key(['a' => 1, 'b' => 2, 'c' => 3], 'd')
 *   // => false
 *
 *   tt_has_key(['a', 'b', 'c', 'd'], '1')
 *   // => true
 *
 * Returns true if the key was found in the container; false if otherwise.
 * Throws an Crankshaft\NotTraversableError if the given argument is not traversable.
 */
function tt_has_key($container, $key) {
    if (is_array($container)) {
        return array_key_exists($key, $container);
    } else if (is_object($container) && $container instanceof \ArrayAccess) {
        return $container->offsetExists($key);
    } else {
        return tt_iter($container)->has_key($key);
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
 *   tt_range(4)->to_array()
 *   // => [0, 1, 2, 3]
 *
 *   tt_range(1, 6)->to_array()
 *   // => [1, 2, 3, 4, 5]
 *
 *   tt_range(0, 7, 2)->to_array()
 *   // => [0, 2, 4, 6]
 *
 *   tt_range(5, -1, -1)->to_array()
 *   // => [5, 4, 3, 2, 1, 0]
 *
 * Returns an Iterable that will yield each value in the sequence.
 */
function tt_range($start, $stop=null, $step=1) {
    if (func_num_args() < 2) {
        $stop = $start;
        $start = 0;
    }

    return tt_iter(new Crankshaft\BoundedSequenceIterator($start, $stop, $step));
}

/**
 * Generate an infinite numeric sequence.
 *
 * $start - The number to start at (default: 0)
 * $step  - The separation between values (default: 1)
 *
 * Examples
 *
 *   tt_count()
 *   // => 0, 1, 2, 3, 4, 5, 6, ...
 *
 *   tt_count(12)
 *   // => 12, 13, 14, 15, 16, ...
 *
 *   tt_count(0, -1)
 *   // => 0, -1, -2, -3, -4, -5, -6, ...
 *
 * Returns an Iterable that will yield each value in the sequence.
 */
function tt_count($start=0, $step=1) {
    return tt_iter(new Crankshaft\SequenceIterator($start, $step));
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
 *   tt_chain([1, 2, 3], [4, 5], [6, 7])->to_array()
 *   # => [1, 2, 3, 4, 5, 6, 7]
 *
 * See also
 *
 *   Iterable->chain() - creates a chain from an Iterable of arrays or Traversable objects
 *
 * Returns an Iterable.
 */
function tt_chain(/* $traversables... */) {
    return tt_iter(func_get_args())->chain();
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
 *   tt_repeat('a', 5)->to_array()
 *   // => ['a', 'a', 'a', 'a', 'a']
 *
 *   tt_repeat('z')
 *   // => 'z', 'z', 'z', 'z', 'z', 'z', 'z', 'z', 'z', 'z', ...
 *
 * Returns an Iterable.
 */
function tt_repeat($value, $count=null) {
    $generator = $count === null
        ? new Crankshaft\RepeatValueGenerator($value)
        : new Crankshaft\BoundedRepeatValueGenerator($value, $count);

    return tt_iter($generator);
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
 *   tt_zip(
 *       [1, 2, 3, 4],
 *       ['a', 'b', 'c']
 *   )->to_array()
 *   // => [[1, 'a'], [2, 'b'], [3, 'c']]
 *
 * Returns an Iterable.
 */
function tt_zip(/* $traversables... */) {
    $iterators = array_map('tt_to_iterator', func_get_args());
    return tt_iter(new Crankshaft\ZipGenerator($iterators, false));
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
 *   tt_zip_longest(
 *       '-',
 *       [1, 2, 3, 4],
 *       ['a', 'b', 'c']
 *   )->to_array()
 *   // => [[1, 'a'], [2, 'b'], [3, 'c'], [4, '-']]
 *
 * Returns an Iterable.
 */
function tt_zip_longest($fill /* , $traversables... */) {
    $iterators = array_map('tt_to_iterator', array_slice(func_get_args(), 1));
    return tt_iter(new Crankshaft\ZipGenerator($iterators, true, $fill));
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
 *   tt_combine(['a', 'b', 'c'], [97, 98, 99])->to_array()
 *   // => ['a' => 97, 'b' => 98, 'c' => 99]
 *
 * Returns an Iterable.
 */
function tt_combine($keys, $values) {
    return tt_iter(
        new Crankshaft\CombiningIterator(
            tt_to_iterator($keys),
            tt_to_iterator($values)
        )
    );
}

/**
 * Internal: Create an Iterator for an array or traversable object, unless it is already an
 * iterator.
 *
 * $traversable - An array or an Iterator or IteratorAggregate object
 *
 * Returns an Iterator.
 * Throws an Crankshaft\NotTraversableError if the given argument is not traversable.
 */
function tt_to_iterator($traversable) {
    if (is_array($traversable)) {
        return new \ArrayIterator($traversable);
    } else if (is_object($traversable) && $traversable instanceof \Iterator) {
        return $traversable;
    } else if (is_object($traversable) && $traversable instanceof \IteratorAggregate) {
        return $traversable->getIterator();
    } else {
        throw new Crankshaft\NotTraversableError(
            tt_inspect($traversable) . ' must be an array, Iterator, or IteratorAggregate.'
        );
    }
}

function tt_set($items=null) {
    return new Crankshaft\Set($items);
}

function tt_to_bool($value) {
    return $value ? true : false;
}

function tt_cmp($a, $b) {
    if ($a > $b) {
        return 1;
    } elseif ($b > $a) {
        return -1;
    } else {
        return 0;
    }
}

function tt_identity($v) {
    return $v;
}

function tt_assert_callable($callable) {
    if (!is_callable($callable)) {
        throw new \InvalidArgumentException(
            'Expected callable but got non-callable ' . var_dump($callable, true) . '.'
        );
    }
}
