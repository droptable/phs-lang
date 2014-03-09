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
  VAL_KIND_EMPTY = 9,
  VAL_KIND_ARR = 10,
  VAL_KIND_OBJ = 11,
  VAL_KIND_FN = 12,
  // VAL_KIND_NONE = 13,
  VAL_KIND_CLASS = 14,
  VAL_KIND_TRAIT = 15,
  VAL_KIND_IFACE = 16,
  VAL_KIND_TYPE = 17,
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
      case VAL_KIND_EMPTY:
        return '(empty)';
      case VAL_KIND_OBJ:
        return '<object>';
      case VAL_KIND_CLASS:
        return '<class>';
      case VAL_KIND_TRAIT:
        return '<trait>';
      case VAL_KIND_IFACE:
        return '<iface>';
      case VAL_KIND_ARR:
        return '<array>';
      case VAL_KIND_FN:
        return "<function {$this->name}>";
    }
    
    return '(unknown)';
  }
  
  /* ------------------------------------ */
  
  /**
   * fetches/creates a value from a symbol
   * 
   * @param  Symbol $sym
   * @return Value
   */
  public static function from(Symbol $sym)
  {              
    if ($sym->kind === SYM_KIND_VAR)
      $nval = $sym->value; 
    elseif ($sym->kind === REF_KIND_VAR)
      $nval = $sym->symbol->value;     
    else {
      $skind = $sym->kind;
      
      if ($skind > SYM_REF_DIVIDER)
        $skind -= SYM_REF_DIVIDER;
      
      $nkind = VAL_KIND_UNKNOWN;
      
      switch ($skind) {
        case SYM_KIND_CLASS:
          $nkind = VAL_KIND_CLASS;
          break;
        case SYM_KIND_TRAIT:
          $nkind = VAL_KIND_TRAIT;
          break;
        case SYM_KIND_IFACE:
          $nkind = VAL_KIND_IFACE;
          break;
        case SYM_KIND_FN:
          $nkind = VAL_KIND_FN;
          break;
        default:
          assert(0);
      }
      
      $nval = new Value($nkind);
    }
    
    $nval->symbol = $sym;
    return $nval;    
  }
  
  /* ------------------------------------ */
  
  public function __clone()
  {
    // don't make things weird
    $this->symbol = null;
  }
}

class FnValue extends Value
{
  // the id of this anonymus function
  public $name;
  
  // the function symbol
  public $sfym;
  
  public function __construct(FnSym $sym)
  {
    // init with "true" so that constant expressions will work as expected
    parent::__construct(VAL_KIND_FN, true);
    $this->name = $sym->name;
    $this->fsym = $sym;
  }
}
