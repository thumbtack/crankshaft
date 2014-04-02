<?php

function cs_assert_callable() {
    return call_user_func_array('Thumbtack\Crankshaft\Crankshaft::AssertCallable', func_get_args());
}

function cs_chain() {
    return call_user_func_array('Thumbtack\Crankshaft\Crankshaft::Chain', func_get_args());
}

function cs_cmp() {
    return call_user_func_array('Thumbtack\Crankshaft\Crankshaft::Compare', func_get_args());
}

function cs_combine() {
    return call_user_func_array('Thumbtack\Crankshaft\Crankshaft::Combine', func_get_args());
}

function cs_count() {
    return call_user_func_array('Thumbtack\Crankshaft\Crankshaft::Count', func_get_args());
}

function cs_has_key() {
    return call_user_func_array('Thumbtack\Crankshaft\Crankshaft::HasKey', func_get_args());
}

function cs_identity() {
    return call_user_func_array('Thumbtack\Crankshaft\Crankshaft::Identity', func_get_args());
}

function cs_iter() {
    return call_user_func_array('Thumbtack\Crankshaft\Crankshaft::Iter', func_get_args());
}

function cs_range() {
    return call_user_func_array('Thumbtack\Crankshaft\Crankshaft::Range', func_get_args());
}

function cs_repeat() {
    return call_user_func_array('Thumbtack\Crankshaft\Crankshaft::Repeat', func_get_args());
}

function cs_set() {
    return call_user_func_array('Thumbtack\Crankshaft\Crankshaft::Set', func_get_args());
}

function cs_to_bool() {
    return call_user_func_array('Thumbtack\Crankshaft\Crankshaft::ToBool', func_get_args());
}

function cs_to_iterator() {
    return call_user_func_array('Thumbtack\Crankshaft\Crankshaft::ToIterator', func_get_args());
}

function cs_zip() {
    return call_user_func_array('Thumbtack\Crankshaft\Crankshaft::Zip', func_get_args());
}

function cs_zip_longest() {
    return call_user_func_array('Thumbtack\Crankshaft\Crankshaft::ZipLongest', func_get_args());
}
