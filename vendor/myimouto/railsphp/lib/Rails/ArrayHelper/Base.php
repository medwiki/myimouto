<?php
namespace Rails\ArrayHelper;

abstract class Base implements \ArrayAccess, \IteratorAggregate
{
    abstract protected function _get_array();
    
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if ($offset === null)
            $this->_get_array()[] = $value;
        else
            $this->_get_array()[$offset] = $value;
    }
    
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return isset($this->_get_array()[$offset]);
    }
    
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        unset($this->_get_array()[$offset]);
    }
    
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        if ($this->offsetExists($offset))
            return $this->_get_array()[$offset];
        return null;
    }
    
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        return new \ArrayIterator($this->_get_array());
    }
    
    public function merge()
    {
        foreach (func_get_args() as $arr) {
            if (!is_array($arr))
                throw new Exception\InvalidArgumentException(
                    sprintf("All arguments passed to merge() must be array, %s passed", gettype($arr))
                );
            foreach ($arr as $k => $v)
                $this->offsetSet($k, $v);
        }
        return $this;
    }
}