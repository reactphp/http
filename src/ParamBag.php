<?php
namespace React\Http;

class ParamBag implements \ArrayAccess {

    private $data = [];
    private $raise_exception = false;
    private $default = null;

    public function __construct($default=null, $raise_exception=false, $data=null) {
        if ($data) {
            $this->data = $data;
        }
        $this->raise_exception = $raise_exception ? true : false;
        $this->default = $default;
    }

    public function offsetExists($offset) {
        return isset($data[$offset]);
    }

    public function offsetGet($offset) {
        if (isset($this->data[$offset])) {
            return $this->data[$offset];
        } elseif ($this->raise_exception) {
            throw new \RuntimeException("Offset not found ".(string) $offset);
        } else {
            return $this->default;
        }
    }

    public function offsetSet($offset, $value) {
        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset) {
        unset($this->data[$offset]);
    }
}
