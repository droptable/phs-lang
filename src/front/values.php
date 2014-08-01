<?php 

namespace phs\front;

// value-kinds
const
  VAL_KIND_UNDEF   = 0, // undefined / unknown
  VAL_KIND_INT     = 1, // integer constant
  VAL_KIND_FLOAT   = 2, // float constant
  VAL_KIND_STRING  = 3, // string constant
  VAL_KIND_BOOL    = 4, // boolean constant
  VAL_KIND_LIST    = 5, // list constant (a list with constant items)
  VAL_KIND_DICT    = 6, // dict constant (a dict with ~some~ constant items)
  VAL_KIND_NEW     = 7, // new constant (the result of a new-expression)
  VAL_KIND_NULL    = 8, // NULL
  VAL_KIND_SYMBOL  = 9  // symbol-reference
;

class Value
{  
  // @var int
  public $kind;
  
  // @var mixed
  public $data;
  
  /**
   * constructor
   *
   * @param int $kind
   * @param mixed $data
   */
  public function __construct($kind, $data = null)
  {
    $this->kind = $kind;
    $this->data = $data;
  }
  
  /**
   * checks if the value is primitive
   *
   * @return boolean
   */
  public function is_primitive()
  {
    switch ($this->kind) {
      case VAL_KIND_INT:
      case VAL_KIND_FLOAT:
      case VAL_KIND_STRING:
      case VAL_KIND_BOOL:
      case VAL_KIND_NULL:
        return true;
      default:
        return false;
    }
  }
}

