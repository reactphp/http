<?php

namespace React\Http;


class HeaderBag extends \ArrayObject
{
    /** Map Header keys in a case-insensitive manner.
     * This is a map from an upper case version of a header name
     * to it's original name (so we can preserve case for posterity).
     *
     * @var array
     */
    protected $headermap = [];
    
    
    public function __construct($input = [], $flags = 0, $iterator_class = "ArrayIterator")
    {
        foreach ($input as $key => $val) {
            $this->headermap[strtoupper($key)] = $key;
        }
        
        parent::__construct($input, $flags, $iterator_class);
    }
    
    public function offsetGet($index)
    {
        if (!isset($this->headermap[strtoupper($index)])) {
            return false;
        }
        return parent::offsetGet($this->headermap[strtoupper($index)]);
    }
    
    public function offsetExists($index)
    {
        return isset($this->headermap[strtoupper($index)]);
    }
    
    public function offsetSet($index, $value)
    {
        $this->headermap[strtoupper($index)] = $index;
        return parent::offsetSet($index, $value);
    }
    
    public function toArray()
    {
        $ret = [];
        
        
        foreach ($this->headermap as $header)
        {
            $ret[$header] = $this->offsetGet($header);
        }
        
        return $ret;
    }
}