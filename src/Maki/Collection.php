<?php

namespace Maki;

class Collection implements \ArrayAccess {
    protected $data = [];

    public function __construct(array $arr = [])
    {
        $this->data = $arr;
    }

    public function get($key, $default = null)
    {
        return $this->offsetExists($key) ? $this->offsetGet($key) : $default;
    }

    public function has($key)
    {
        return $this->offsetExists($key);
    }

    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }

    public function merge(array $array = [])
    {
        $this->data = array_merge($this->data, $array);
    }

    public function pull($key, $default = null)
    {
        $value = $this->get($key, $default);
        $this->offsetUnset($key);

        return $value;
    }

    public function toArray()
    {
        return $this->data;
    }
}

