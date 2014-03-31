<?php

require_once('IterationUtils.php');
require_once('global_functions.php');
require_once('simpletest/autorun.php');

class PluckTestSimpleObject {
    public function __construct(array $values) {
        foreach ($values as $key => $value) {
            $this->$key = $value;
        }
    }
}

class PluckTestContainerObject implements IterationUtils\PropertyContainer {
    public $key;
    private $values;

    public function __construct(array $values) {
        $this->values = $values;
    }

    public function get($key) {
        return $this->values[$key];
    }
}

class PluckableProperties {
    private $data;

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function __get($name) {
        return $this->data[$name];
    }

    public function __isset($name) {
        return isset($this->data[$name]);
    }
}

class IterableTest extends UnitTestCase {
    public function test_each() {
        $input = ['a' => 1, 'c' => 2, 'e' => 3, 'g' => 4];
        $observed = [];

        tt_iter($input)->each(function($value, $key) use (&$observed) {
            $observed[$key] = $value;
        });

        $this->assertSame($input, $observed);
    }

    public function test_map() {
        $expected = [
            'a' => 0,
            'b' => 1,
            'c' => 4,
            'd' => 9,
            'e' => 16,
        ];
        $seen_keys = [];

        $map_result = tt_iter($this->sample_iterator())
            ->map(function($value, $key) use (&$seen_keys) {
                $seen_keys[] = $key;
                return $value * $value;
            });
        $this->assertSame($expected, $map_result->to_array());
        $this->assertSame(array_keys($expected), $seen_keys);
    }

    public function test_map_keys() {
        $expected = [
            'A' => 0,
            'B' => 1,
            'C' => 2,
            'D' => 3,
            'E' => 4,
        ];

        $seen_values = [];

        $map_result = tt_iter($this->sample_iterator())
            ->map_keys(function($key, $value) use (&$seen_values) {
                $seen_values[] = $value;
                return strtoupper($key);
            });
        $this->assertSame($expected, $map_result->to_array());
        $this->assertSame(array_values($expected), $seen_values);
    }

    public function test_bare_filter() {
        $object = new stdClass;
        $input = [1, 0, true, '', false, 'hi', null, [1, 2], [], $object];
        $expected = [0 => 1, 2 => true, 5 => 'hi', 7 => [1, 2], 9 => $object];
        $actual = tt_iter($input)->filter()->to_array();

        $this->assertSame($expected, $actual);
    }

    public function test_filter_with_callback() {
        $input = range(0, 9);
        $expected = [0 => 0, 2 => 2, 4 => 4, 6 => 6, 8 => 8];
        $actual = tt_iter($input)
            ->filter(function($value) { return $value % 2 == 0; })
            ->to_array();

        $this->assertSame($expected, $actual);
    }

    public function test_bare_reject() {
        $object = new stdClass;
        $input = [1, 0, true, '', false, 'hi', null, [1, 2], [], $object];
        $expected = [1 => 0, 3 => '', 4 => false, 6 => null, 8 => []];
        $actual = tt_iter($input)->reject()->to_array();

        $this->assertSame($expected, $actual);
    }

    public function test_reject_with_callback() {
        $input = range(0, 9);
        $expected = [1 => 1, 3 => 3, 5 => 5, 7 => 7, 9 => 9];
        $actual = tt_iter($input)
            ->reject(function($value) { return $value % 2 == 0; })
            ->to_array();

        $this->assertSame($expected, $actual);
    }

    public function test_select() {
        $this->assert_values_same(
            [
                ['x' => 2, 'y' => 'bar'],
                ['x' => 2, 'y' => 'baz'],
            ],
            tt_iter(
                [
                    ['x' => 1, 'y' => 'foo'],
                    ['x' => 2, 'y' => 'bar'],
                    ['x' => 2, 'y' => 'baz'],
                ]
            )->select(['x' => 2])
        );

        $this->assert_values_same(
            [],
            tt_iter(
                [
                    ['x' => 1, 'y' => 'foo'],
                    ['x' => 2, 'y' => 'bar'],
                    ['x' => 3, 'y' => 'baz'],
                ]
            )->select(['x' => 4])
        );

        $this->assert_values_same(
            [],
            tt_iter(
                [
                    ['x' => 1, 'y' => 'foo'],
                    ['x' => 2, 'y' => 'bar'],
                    ['x' => 3, 'y' => 'baz'],
                ]
            )->select(['x' => '2'])
        );
    }

    public function test_reduce_arrays() {
        $numbers = [-1, 0, 1, 3, 10];
        $sample_iterator = $this->sample_iterator();

        $add = function($memo, $value) { return $memo + $value; };
        $multiply = function($memo, $value) { return $memo * $value; };

        $this->assertEqual(
            array_sum($numbers),
            tt_iter($numbers)->reduce($add)
        );

        $this->assertEqual(
            array_sum(iterator_to_array($sample_iterator)),
            tt_iter($sample_iterator)->reduce($add)
        );

        $this->assertEqual(
            array_product($numbers),
            tt_iter($numbers)->reduce($multiply)
        );

        $this->assertEqual(
            3,
            tt_iter([])->reduce($multiply, 3)
        );

        try {
            tt_iter([])->reduce($add);
        } catch (IterationUtils\EmptyIterableError $e) {
            $this->pass();
            return;
        }

        $this->fail('should have thrown EmptyIterableError');
    }

    public function test_reduce_sets() {
        $set = tt_set(['x', 'k', 'c', 'd']);
        $one_letter_set = tt_set(['x']);

        $min = function($memo, $value) { return $value < $memo ? $value : $memo; };

        $this->assertEqual(
            'c',
            $set->reduce($min)
        );

        $this->assertEqual(
            'x',
            $one_letter_set->reduce($min)
        );

        $this->assertSame(
            null,
            tt_set()->reduce($min, null)
        );

        try {
            tt_set()->reduce($min);
        } catch (IterationUtils\EmptyIterableError $e) {
            $this->pass();
            return;
        }

        $this->fail('should have thrown EmptyIterableError');
    }

    public function test_slice() {
        $input = ['a', 'b', 'c', 'd', 'e', 'f', 'g'];
        $iter = tt_iter($input);

        $this->assertSame($input, $iter->slice(0)->to_array());
        $this->assertSame(count($input), count($iter->slice(0)));

        $this->assert_values_same(['b', 'c', 'd', 'e', 'f', 'g'], $iter->slice(1));
        $this->assert_values_same(['b'], $iter->slice(1, 2));
        $this->assert_values_same(['c', 'd', 'e'], $iter->slice(2, 5));
        $this->assertSame(3, count($iter->slice(2, 5)));

        $this->assert_values_same(['g'], $iter->slice(-1));
        $this->assert_values_same(['e', 'f', 'g'], $iter->slice(-3));
        $this->assert_values_same(['d', 'e'], $iter->slice(-4, -2));
        $this->assert_values_same(['a', 'b', 'c', 'd', 'e', 'f'], $iter->slice(0, -1));
        $this->assertSame(6, count($iter->slice(0, -1)));
    }

    public function test_first() {
        $input = [2, 7, 5];
        $iter = tt_iter($input);
        $this->assertEqual($input[0], $iter->first());
        $iter = tt_iter([]);
        $this->assertEqual(null, $iter->first());
    }

    public function test_last() {
        $input = [2, 7, 5];
        $iter = tt_iter($input);
        $this->assertEqual($input[count($input) - 1], $iter->last());
        $iter = tt_iter([]);
        $this->assertEqual(null, $iter->last());
    }

    public function test_bare_any() {
        $this->assertTrue(tt_iter([0, 1, 2])->any());
        $this->assertTrue(tt_iter([false, true])->any());
        $this->assertTrue(tt_iter(["hello"])->any());

        $this->assertFalse(tt_iter([])->any()); // matches Python
        $this->assertFalse(tt_iter([false, null])->any());
    }

    public function test_any_with_callback() {
        $this->assertTrue(
            tt_iter([1, 2, 3, 4, 5])->any(function($value) {
                return $value == 3;
            })
        );

        $this->assertFalse(
            tt_iter([1, 2, 3, 4, 5])->any(function($value) {
                return $value > 8;
            })
        );
    }

    public function test_bare_all() {
        $this->assertTrue(tt_iter([])->all()); // matches Python
        $this->assertTrue(tt_iter([1, 'hi', true, [3, 4]])->all());

        $this->assertFalse(tt_iter([1, 2, 3, 0, 5])->all());
    }

    public function test_all_with_callback() {
        $this->assertTrue(
            tt_iter([1, 2, 3, 4, 5])->all(function($value) {
                return $value > 0;
            })
        );

        $this->assertFalse(
            tt_iter([1, 2, 3, 4, 5])->all(function($value) {
                return $value == 3;
            })
        );
    }

    public function test_find() {
        $this->assertSame(
            null,
            tt_iter([1, 2, 3])->find(function($value) {
                return $value > 3;
            })
        );
        $this->assertSame(
            0,
            tt_iter([1, 2, 3])->find(
                function($value) { return $value > 3; },
                0
            )
        );
        $this->assertSame(
            3,
            tt_iter([1, 2, 3, 4])->find(function($value) {
                return $value > 2;
            })
        );
        $this->assertSame(
            3,
            tt_iter($this->sample_iterator())->find(function($value) {
                return $value > 2;
            })
        );
    }

    public function test_partition() {
        $this->assertSame(
            [],
            tt_iter([])->partition('tt_identity')->to_array()
        );
        $this->assertSame(
            [1 => [1], 2 => [2], 3 => [3]],
            tt_iter([1, 2, 3])->partition('tt_identity')->to_array()
        );

        list($partitions, $null_group) = tt_iter([1, 2, 3])->partition('tt_identity', true);
        $this->assertSame([1 => [1], 2 => [2], 3 => [3]], $partitions->to_array());
        $this->assertSame([], $null_group->to_array());


        $this->assertSame(
            [1 => [1], 2 => [2], 3 => [3]],
            tt_iter([1, 2, 3, null])->partition('tt_identity')->to_array()
        );

        list($partitions, $null_group)
            = tt_iter([1, null, 2, 3, null])->partition('tt_identity', true);
        $this->assertSame([1 => [1], 2 => [2], 3 => [3]], $partitions->to_array());
        $this->assertSame([null, null], $null_group->to_array());

        $this->assertSame(
            [1 => [1], 2 => [2], 3 => [3]],
            tt_iter([1, null, 2, 3, null])->partition('tt_identity')->to_array()
        );
        $this->assertSame(
            [1 => [1, 4], 2 => [2, 5], 0 => [3, 6]],
            tt_iter([1, 2, 3, 4, 5, 6])
                ->partition(function($value, $key) {
                    return $value % 3;
                })
                ->to_array()
        );
    }

    public function test_partition_by() {
        $this->assert_values_same([], tt_iter([])->partition_by('field'));

        $input_values = [
            ['a' => 1, 'b' => 2],
            ['a' => 1, 'b' => 3],
            ['a' => 2],
            ['b' => 1, 'a' => 2],
            ['b' => 4],
        ];

        $this->assert_values_same(
            [
                1 => [['a' => 1, 'b' => 2], ['a' => 1, 'b' => 3]],
                2 => [['a' => 2], ['b' => 1, 'a' => 2]],
            ],
            tt_iter($input_values)->partition_by('a')
        );

        $input_values = [['a' => 1], ['b' => 1], ['a' => 1, 'b' => 2], ['b' => 3]];
        list($groups, $null_group) = tt_iter($input_values)->partition_by('a', true);
        $this->assert_values_same([1 => [['a' => 1], ['a' => 1, 'b' => 2]]], $groups);
        $this->assert_values_same([['b' => 1], ['b' => 3]], $null_group);
    }

    public function test_unique() {
        $this->assertSame(
            [],
            tt_iter([])->unique()->to_array()
        );

        $this->assertSame(
            [1, 2, 3],
            tt_iter([1, 2, 3])->unique()->to_array()
        );

        $this->assertSame(
            [1, 2, 3],
            tt_iter([1, 2, 3, 1, 2, 3])->unique()->to_array()
        );

        $this->assertSame(
            [0 => 'a', 2 => 'b', 4 => 'c'],
            tt_iter(['a', 'a', 'b', 'b', 'c', 'c'])->unique()->to_array()
        );

        $this->assertSame(
            [0 => 1, 2 => 3, 5 => 6],
            tt_range(1, 9)
                ->unique(function ($value, $key) {
                    return intval($value / 3);
                })
                ->to_array()
        );

        $this->assertSame(
            [0 => null, 1 => 1, 4 => 2, 6 => 3],
            tt_iter([null, 1, 1, null, 2, null, 3, 2])->unique()->to_array()
        );
    }

    public function test_key_of() {
        $search = tt_iter(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);

        $this->assertSame(null, $search->key_of(0));
        $this->assertSame('x', $search->key_of(0, 'x'));
        $this->assertSame('b', $search->key_of(2));
    }

    public function test_index_of() {
        $search = tt_iter([4, 8, 2, 4, 6, -3]);

        $this->assertSame(-1, $search->index_of(12));
        $this->assertSame(0, $search->index_of(4));
        $this->assertSame(4, $search->index_of(6));
    }

    public function test_has_key() {
        $array = [1 => 'a', 2 => 'b', 3 => 'c'];
        $this->assertTrue(tt_iter($array)->has_key(1));
        $this->assertFalse(tt_iter($array)->has_key(4));
        $this->assertFalse(tt_iter($array)->has_key('b'));

        $sample_iterator = $this->sample_iterator();
        $this->assertTrue(tt_iter($sample_iterator)->has_key('d'));
        $this->assertFalse(tt_iter($sample_iterator)->has_key('f'));
    }

    public function test_contains() {
        $array = [4, '5', true];

        $this->assertTrue(tt_iter($array)->contains(4));
        $this->assertTrue(tt_iter($array)->contains('5'));
        $this->assertTrue(tt_iter($array)->contains(true));

        $this->assertFalse(tt_iter($array)->contains('4'));
        $this->assertFalse(tt_iter($array)->contains(5));
        $this->assertFalse(tt_iter($array)->contains(1));

        $set = tt_set([1, 3, 5, 7]);
        $this->assertTrue($set->contains(3));
        $this->assertFalse($set->contains(4));
        $this->assertFalse($set->contains('5'));

        $this->assertTrue(tt_iter($this->sample_iterator())->contains(2));
        $this->assertFalse(tt_iter($this->sample_iterator())->contains(8));
    }

    public function test_pluck() {
        $this->assert_values_same(
            ['foo', 'bar', 'baz'],
            tt_iter(
                [
                    ['x' => 1, 'y' => 'foo'],
                    ['x' => 2, 'y' => 'bar'],
                    ['x' => 3, 'y' => 'baz'],
                ]
            )->pluck('y')
        );

        $this->assert_values_same(
            [0, 1, null, 2],
            tt_iter([
                new PluckTestSimpleObject(['key' => 0]),
                new PluckTestSimpleObject(['key' => 1]),
                new PluckTestSimpleObject(['key' => null]),
                new PluckTestSimpleObject(['key' => 2]),
            ])->pluck('key')
        );

        $this->assert_values_same(
            ['foo', 'bar', 'baz'],
            tt_iter(
                [
                    new PluckTestSimpleObject(['x' => 1, 'a' => 'foo']),
                    new PluckTestSimpleObject(['x' => 2, 'a' => 'bar']),
                    new PluckTestSimpleObject(['x' => 3, 'a' => 'baz']),
                ]
            )->pluck('a')
        );

        $this->assert_values_same(
            ['foo', 'bar', 'baz'],
            tt_iter(
                [
                    new PluckTestContainerObject(['x' => 1, 'z' => 'foo']),
                    new PluckTestContainerObject(['x' => 2, 'z' => 'bar']),
                    new PluckTestContainerObject(['x' => 3, 'z' => 'baz']),
                ]
            )->pluck('z')
        );

        // PluckTestContainerObject has a real public property named `key`. Even though its
        // value is null, plucking 'key' should not result in a call to `get('key')`.
        $this->assert_values_same(
            [null, null],
            tt_iter(
                [
                    new PluckTestContainerObject(['key' => 1]),
                    new PluckTestContainerObject(['key' => 2]),
                ]
            )->pluck('key')
        );

        $this->assert_values_same(
            ['foo', 'bar', 'baz'],
            tt_iter(
                [
                    new PluckableProperties(['x' => 1, 'y' => 'foo']),
                    new PluckableProperties(['x' => 2, 'y' => 'bar']),
                    new PluckableProperties(['x' => 3, 'y' => 'baz']),
                ]
            )->pluck('y')
        );
    }

    public function test_keys_values() {
        $test = ['a' => 1, 'e' => -1, 'c' => 5, 'g' => 19];
        $iterable = tt_iter($test);

        $this->assertEqual(array_keys($test), $iterable->keys()->to_array());
        $this->assertEqual(array_values($test), $iterable->values()->to_array());
    }

    public function test_chain() {
        $chain = tt_iter([[4, 5, 6], [1, 2, 3], [7, 8]])->chain();

        // the for loop tests rewindability
        for ($i = 0; $i < 2; $i++) {
            $this->assertSame(
                [4, 5, 6, 1, 2, 3, 7, 8],
                $chain->to_array()
            );
        }

        // test chain containing empty iterables
        $this->assertSame(
            [4, 5, 6, 7, 9],
            tt_iter([[], [4, 5, 6], [], [], [7], [9], []])
                ->chain()
                ->to_array()
        );

        $this->assertSame(
            [],
            tt_iter([[], []])->chain()->to_array()
        );
    }

    public function test_flip() {
        $input = ['a' => 1, 'e' => -1, 'c' => 5, 'g' => 19, 'q' => 6];

        $this->assertSame(
            array_flip($input),
            tt_iter($input)->flip()->to_array()
        );

        $this->assertSame(
            ['a', 'b', 'c', 'd', 'e'],
            tt_iter($this->sample_iterator())->flip()->to_array()
        );

        $dupes = ['a' => 1, 'b' => 2, 'c' => 1, 'd' => 2, 'e' => 3];
        $expected = [
            [1, 'a'],
            [2, 'b'],
            [1, 'c'],
            [2, 'd'],
            [3, 'e']
        ];
        $seen = [];
        foreach (tt_iter($dupes)->flip() as $key => $value) {
            $seen[] = [$key, $value];
        }
        $this->assertEqual($expected, $seen);

        $this->assertEqual(
            [1 => 'c', 2 => 'd', 3 => 'e'],
            tt_iter($dupes)->flip()->to_array()
        );

        $values = tt_iter($dupes)->flip()->to_set()->to_array();
        sort($values);
        $this->assertEqual(
            ['a', 'b', 'c', 'd', 'e'],
            $values
        );
    }

    public function test_cycle() {
        $repeat = tt_iter([1, 2, 3])->cycle();
        $this->assertSame(
            [1, 2, 3, 1, 2, 3, 1, 2],
            $repeat->slice(0, 8)->to_array()
        );

        $this->assertFalse(tt_iter([])->cycle()->getIterator()->valid());
    }

    public function test_sort() {
        $this->assert_values_same([], tt_iter([])->sort());
        $this->assert_values_same([1, 2, 3], tt_iter([1, 2, 3])->sort());
        $this->assert_values_same([1, 2, 2, 3], tt_iter([2, 3, 2, 1])->sort());

        $string_length = tt_iter(['aaa', 'a', 'aa', 'aaaa'])->sort(function ($a, $b) {
            return strlen($a) - strlen($b);
        });
        $this->assert_values_same(['a', 'aa', 'aaa', 'aaaa'], $string_length);
    }

    public function test_sort_by_properites() {
        $null_test = tt_iter([])->sort_by_properties(['a' => 1, 'b' => -1]);
        $this->assert_values_same([], $null_test);

        $forward_sort = tt_iter([
            ['a' => 2, 'b' => 3],
            ['a' => 1, 'b' => 2],
            ['a' => 3, 'b' => 1],
        ])->sort_by_properties(['a' => 1]);
        $this->assert_values_same([
            ['a' => 1, 'b' => 2],
            ['a' => 2, 'b' => 3],
            ['a' => 3, 'b' => 1],
        ], $forward_sort);

        $reverse_sort = tt_iter([
            ['a' => 2, 'b' => 3],
            ['a' => 1, 'b' => 2],
            ['a' => 3, 'b' => 1],
        ])->sort_by_properties(['a' => -1]);
        $this->assert_values_same([
            ['a' => 3, 'b' => 1],
            ['a' => 2, 'b' => 3],
            ['a' => 1, 'b' => 2],
        ], $reverse_sort);

        $multi_field_sort = tt_iter([
            ['a' => 2, 'b' => 3],
            ['a' => 1, 'b' => 2],
            ['a' => 1, 'b' => 1],
        ])->sort_by_properties(['a' => 1, 'b' => -1]);
        $this->assert_values_same([
            ['a' => 1, 'b' => 2],
            ['a' => 1, 'b' => 1],
            ['a' => 2, 'b' => 3],
        ], $multi_field_sort);

        $missing_values = tt_iter([
            ['a' => 1, 'b' => 1],
            ['a' => 0, 'b' => 3],
            ['b' => 4],
        ])->sort_by_properties(['a' => 1]);
        $this->assert_values_same([
            ['b' => 4],
            ['a' => 0, 'b' => 3],
            ['a' => 1, 'b' => 1],
        ], $missing_values);
    }

    public function test_min() {
        $this->assertSame(0, tt_iter($this->sample_iterator())->min());
        $this->assertSame(2, tt_iter([2])->min());

        $sample_structs = [
            ['x' => 1, 'y' => 2, 'z' => 1],
            ['x' => 3, 'y' => 1, 'z' => 2],
            ['x' => -1, 'y' => 5, 'z' => 1],
        ];
        $iter = tt_iter($sample_structs);

        $this->assertSame(
            $sample_structs[2],
            $iter->min(function($s) { return $s['x']; })
        );
        $this->assertSame(
            $sample_structs[1],
            $iter->min(function($s) { return $s['y']; })
        );
        $this->assertSame(
            $sample_structs[0], // two structs have the same 'z', but the first should be chosen
            $iter->min(function($s) { return $s['z']; })
        );

        try {
            tt_iter([])->min();
        } catch (IterationUtils\EmptyIterableError $e) {
            $this->pass();
            return;
        }

        $this->fail('min() should have thrown EmptyIterableError');
    }

    public function test_max() {
        $this->assertSame(4, tt_iter($this->sample_iterator())->max());
        $this->assertSame(2, tt_iter([2])->max());

        $sample_structs = [
            ['x' => 1, 'y' => 2, 'z' => 2],
            ['x' => 3, 'y' => 1, 'z' => 1],
            ['x' => -1, 'y' => 5, 'z' => 2],
        ];
        $iter = tt_iter($sample_structs);

        $this->assertSame(
            $sample_structs[1],
            $iter->max(function($s) { return $s['x']; })
        );
        $this->assertSame(
            $sample_structs[2],
            $iter->max(function($s) { return $s['y']; })
        );
        $this->assertSame(
            $sample_structs[0], // two structs have the same 'z', but the first should be chosen
            $iter->max(function($s) { return $s['z']; })
        );

        try {
            tt_iter([])->max();
        } catch (IterationUtils\EmptyIterableError $e) {
            $this->pass();
            return;
        }

        $this->fail('max() should have thrown EmptyIterableError');
    }

    public function test_sum() {
        $this->assertSame(0, tt_iter([])->sum());
        $this->assertSame(0.0, tt_iter([])->sum(0.0));
        $this->assertSame(6, tt_iter([3, 1, 2])->sum());
        $this->assertSame(6.0, tt_iter([3, 1, 2])->sum(0.0));
    }

    public function test_join() {
        $letters = ['a', 'b', 'c', 'd'];
        $this->assertSame('a, b, c, d', tt_iter($letters)->join(', '));
        $this->assertSame('abcd', tt_iter($letters)->join(''));
        $this->assertSame('1234', tt_range(1, 5)->join(''));
    }

    public function test_count() {
        $this->assertSame(6, tt_iter([1, 2, 3, 4, 5, 6])->count());
        $this->assertSame(5, tt_iter($this->sample_iterator())->count());
    }

    public function test_reverse() {
        $input = [0, 1, 2, 3, 4, 5];

        $this->assertSame(count($input), count(tt_iter($input)->reverse()));
        $this->assert_values_same(
            [5, 4, 3, 2, 1, 0],
            tt_iter($input)->reverse()
        );
    }

    public function test_suffle() {
        $this->assert_values_same([], tt_iter([])->shuffle());
        $this->assert_values_same([9], tt_iter([9])->shuffle());

        // Let grab a list of 12 elements, the number of
        // its unique permutations is d = 12! ≈ 5*10^8.
        $list = tt_iter([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]);

        // If we do n = 100 random draws from a set of all
        // permutations, the probability of a collision
        // p(n, d) ≈ 1 - exp(-n(n-1)/2d) ≈ 0.00001 [1]
        //
        // [1]: Search for the generalized birthday problem.
        $n = 100;
        $draws = tt_set();
        for ($i = 0; $i < $n; ++$i) {
            $draw = $list->shuffle()->to_array();
            $draws->add(implode(',', $draw));
        }
        $this->assertEqual($n, $draws->count());
    }

    private function sample_iterator() {
        return new SampleIterator();
    }

    private function assert_values_same($expected, $actual) {
        if ($actual instanceof IterationUtils\Iterable) {
            $actual = $actual->to_array();
        }

        $this->assertSame(array_values($expected), array_values($actual));
    }
}

class SampleIterator implements \Iterator {
    private $key;
    private $count;

    public function rewind() {
        $this->key = 'a';
        $this->count = 0;
    }

    public function current() {
        return $this->count;
    }

    public function key() {
        return $this->key;
    }

    public function next() {
        $this->key++;
        $this->count++;
    }

    public function valid() {
        return $this->count < 5;
    }
}
