<?php

namespace phs;

const
  VAL_KIND_STR = 1,
  VAL_KIND_REGEXP = 2,
  VAL_KIND_LNUM = 4,
  VAL_KIND_DNUM = 5,
  VAL_KIND_SNUM = 6,
  VAL_KIND_BOOL = 7,
  VAL_KIND_NULL = 8,
  VAL_KIND_SYMBOL = 9,
  VAL_KIND_ARR = 10,
  VAL_KIND_OBJ = 11,
  VAL_KIND_FN = 12,
  VAL_KIND_NONE = 13,
  VAL_KIND_UNKNOWN = 99
;

/** a value */
class Value
{
  // value-kind
  public $kind;
  
  // actual value
  public $value;
  
  // the symbol associated with this value
  public $symbol;
  
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
      case VAL_KIND_BOOL:
        return $this->value ? 'true' : 'false';
      case VAL_KIND_NULL:
        return 'null';
      case VAL_KIND_SYMBOL:
        return '(symbol)';
      case VAL_KIND_OBJ:
        return '<object>';
      case VAL_KIND_ARR:
        return '<array>';
    }
    
    return '(unknown)';
  }
}

