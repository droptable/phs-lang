<?php

namespace phs;

const
  VAL_KIND_STR = 1,
  VAL_KIND_REGEXP = 2,
  VAL_KIND_LNUM = 4,
  VAL_KIND_DNUM = 5,
  VAL_KIND_SNUM = 6,
  VAL_KIND_TRUE = 7,
  VAL_KIND_FALSE = 8,
  VAL_KIND_NULL = 9,
  VAL_KIND_SYMBOL = 10,
  VAL_KIND_UNKNOWN = 11
;

/** a value */
class Value
{
  // value-kind
  public $kind;
  
  // actual value
  public $value;
  
  /**
   * constructor
   * 
   * @param int $kind
   * @param mixed $value
   */
  public function __construct($kind, $value = null)
  {
    $this->kind = $kind;
    $this->value = $value;
  }

  /**
   * string representation
   * 
   * @return string
   */
  public function __toString()
  {
    switch ($this->kind) {
      case VAL_KIND_STR:
        return '"' . strtr($this->value, [ '"' => '\\"']) . '"';
      case VAL_KIND_REGEXP:
        return $this->value;
      case VAL_KIND_LNUM:
      case VAL_KIND_DNUM:
        return (string) $this->value;
      case VAL_KIND_TRUE:
        return 'true';
      case VAL_KIND_FALSE:
        return 'false';
      case VAL_KIND_NULL:
        return 'null';
      case VAL_KIND_SYMBOL:
        return '(symbol)';
    }
    
    return '(unknown)';
  }
}

