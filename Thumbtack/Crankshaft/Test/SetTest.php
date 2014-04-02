<?php

namespace Thumbtack\Crankshaft\Test;

require_once(dirname(__FILE__) . '/../register_globals.php');

use Thumbtack\Crankshaft;

class SetTest extends \UnitTestCase {
    private function get_sorted_values_from_set($set) {
        $values = [];
        foreach ($set as $value) {
            $values[] = $value;
        }
        sort($values);
        return $values;
    }

    function testEquals() {
        $this->assertTrue(cs_set(range(0, 100))->equals(cs_set(range(0, 100))));
        $this->assertTrue(cs_set()->equals(cs_set()));
        $this->assertFalse(cs_set([1])->equals(cs_set()));
        $this->assertFalse(cs_set(range(0, 100))->equals(cs_set(range(0, 101))));
    }

    function testIsSubset() {
        $this->assertTrue(cs_set(range(0, 100))->is_subset(cs_set(range(0, 100))));
        $this->assertTrue(cs_set(range(0, 10))->is_subset(cs_set(range(0, 100))));
        $this->assertTrue(cs_set([])->is_subset(cs_set(range(0, 100))));

        $this->assertFalse(cs_set(range(0, 10))->is_subset(cs_set(range(1, 100))));
        $this->assertFalse(cs_set(range(0, 10))->is_subset(cs_set([])));
        $this->assertFalse(cs_set(range(0, 10))->is_subset(cs_set(range(0, 9))));
    }

    function testIsSuperset() {
        $this->assertTrue(cs_set(range(0, 100))->is_superset(cs_set(range(0, 100))));
        $this->assertTrue(cs_set(range(0, 10))->is_superset(cs_set([])));
        $this->assertTrue(cs_set(range(0, 10))->is_superset(cs_set(range(0, 9))));

        $this->assertFalse(cs_set(range(0, 10))->is_superset(cs_set(range(0, 100))));
        $this->assertFalse(cs_set(range(0, 10))->is_superset(cs_set(range(1, 100))));
        $this->assertFalse(cs_set([])->is_superset(cs_set(range(0, 100))));
    }

    function testIntersectionAndUnion() {
        $test_cases = [
            [
                'a' => range(0, 3),
                'b' => range(0, 10),
                'union' => range(0, 10),
                'intersection' => range(0, 3),
            ],
            [
                'a' => [],
                'b' => range(0, 10),
                'union' => range(0, 10),
                'intersection' => [],
            ],
            [
                'a' => range(0, 10),
                'b' => range(0, 10),
                'union' => range(0, 10),
                'intersection' => range(0, 10),
            ],
            [
                'a' => range(11, 20),
                'b' => range(0, 10),
                'union' => range(0, 20),
                'intersection' => [],
            ],
        ];

        foreach ($test_cases as $test_case) {
            $this->assertSame(
                $this->get_sorted_values_from_set(
                    cs_set($test_case['a'])->intersection(cs_set($test_case['b']))),
                $test_case['intersection']
            );
            $this->assertSame(
                $this->get_sorted_values_from_set(
                    cs_set($test_case['a'])->union(cs_set($test_case['b']))),
                $test_case['union']
            );
        }
    }

    function testDifference() {
        $test_cases = [
            [
                'a' => range(0, 10),
                'b' => range(0, 10),
                'difference' => [],
            ],
            [
                'a' => range(0, 10),
                'b' => range(0, 9),
                'difference' => [10],
            ],
            [
                'a' => range(0, 10),
                'b' => range(5, 10),
                'difference' => range(0, 4),
            ],
            [
                'a' => range(0, 10),
                'b' => range(11, 20),
                'difference' => range(0, 10),
            ],
        ];

        foreach ($test_cases as $test_case) {
            $this->assertSame(
                $this->get_sorted_values_from_set(
                    cs_set($test_case['a'])->difference(cs_set($test_case['b']))),
                $test_case['difference']
            );
        }
    }

    function testAddAndUpdate() {
        $set = cs_set();
        $set->update(range(0, 3));
        $this->assertSame(count($set), 4);

        $set->update(range(0, 3));
        $this->assertSame(count($set), 4);

        $set->update(range(0, 9));
        $this->assertSame(count($set), 10);

        $set->add(10);
        $this->assertSame(count($set), 11);

        $set->add(10);
        $this->assertSame(count($set), 11);

        $this->assertSame($this->get_sorted_values_from_set($set), range(0, 10));
    }

    function testRemove() {
        $set = cs_set();
        $set->update(range(0, 3));
        $this->assertSame(count($set), 4);

        // Remove an element not in the set, count remains the same
        $set->remove(100);
        $this->assertSame(count($set), 4);

        // Remove an element in the set, count should update
        $set->remove(0);
        $this->assertSame(count($set), 3);

        $this->assertSame($this->get_sorted_values_from_set($set), range(1, 3));
    }

    function testContains() {
        $set = cs_set();
        $this->assertFalse($set->contains(0));

        $set->add(0);
        $this->assertTrue($set->contains(0));

        $set->remove(0);
        $this->assertFalse($set->contains(0));

        $set->add(1);
        $this->assertFalse($set->contains('1'));
    }

    function testTypeStrictness() {
        $set = cs_set();

        $set->add(1);
        $this->assertTrue($set->contains(1), 'set should contain `1`');
        $this->assertFalse($set->contains('1'), 'set should not contain `"1"`');

        $set->add('1');
        $this->assertTrue($set->contains('1'), 'set should contain 1');
        $this->assertTrue($set->contains(1), 'set should contain `"1"`');
        $this->assertEqual(2, count($set), 'wrong length: %s');

        $set->add(true);
        $this->assertTrue($set->contains(true));
        $this->assertEqual(3, count($set), 'wrong length: %s');

        $seen_int = false;
        $seen_string = false;
        $seen_boolean = false;

        foreach ($set as $value) {
            if ($value === 1) {
                $seen_int = true;
            } else if ($value === '1') {
                $seen_string = true;
            } else if ($value === true) {
                $seen_boolean = true;
            } else {
                $this->fail('unknown value: ' . cs_inspect($value));
            }
        }

        $this->assertTrue($seen_int, 'should have gotten `1` back');
        $this->assertTrue($seen_string, 'should have gotten `"1"` back');
        $this->assertTrue($seen_boolean, 'should have gotten `TRUE` back');
    }

    function testErrorOnNonScalarItems() {
        $set = cs_set();

        try {
            $set->add([]);
            $this->fail('Should have thrown InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->pass('Properly threw InvalidArgumentException');
        }

        try {
            $set->add(new \stdclass());
            $this->fail('Should have thrown InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->pass('Properly threw InvalidArgumentException');
        }
    }

    function testNullSupport() {
        $set = cs_set();
        $this->assertFalse($set->contains(null));

        $set->add(null);
        $this->assertTrue($set->contains(null));
        $this->assertFalse($set->contains(false));
        $this->assertFalse($set->contains(0));

        $set->remove(null);
        $this->assertFalse($set->contains(null));
    }
}
