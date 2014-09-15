<?php 

namespace phs\front;

use phs\Logger;

use phs\util\Result;

use phs\lang\BuiltInList;
use phs\lang\BuiltInDict;

// value-kinds
const
  VAL_KIND_UNDEF   = 0,  // unknown type (not reducible at compile-time)
  VAL_KIND_INT     = 1,  // integer constant
  VAL_KIND_FLOAT   = 2,  // float constant
  VAL_KIND_STRING  = 3,  // string constant
  VAL_KIND_STR     = 3, 
  VAL_KIND_BOOL    = 4,  // boolean constant
  VAL_KIND_LIST    = 5,  // list constant (a list with constant items)
  VAL_KIND_DICT    = 6,  // dict constant (a dict with constant items)
  VAL_KIND_NEW     = 7,  // new constant (the result of a new-expression)
  VAL_KIND_NULL    = 8,  // NULL
  VAL_KIND_SYMBOL  = 9,  // symbol-reference
  VAL_KIND_TUPLE   = 10, // tuple
  VAL_KIND_NONE    = 11  // uninitialized, no type known at this point
;

class Value
{  
  // @var int
  public $kind;
  
  // @var mixed
  public $data;
  
  // @var boolean
  private $frozen = false;
    
  // @var boolean
  private $guard = true;
    
  // @var Value
  public /* const */ static $NONE;
  
  // @var Value
  public /* const */ static $UNDEF;
  
  /**
   * constructor
   *
   * @param int $kind
   * @param mixed $data
   */
  public function __construct($kind, $data = null)
  {    
    $this->kind = $kind;
    $this->data = $this->cast($data);
  }
  
  /**
   * returns a readable type 
   *
   * @return string
   */
  public function inspect()
  {
    static $types = [
      VAL_KIND_UNDEF  => 'undefined',
      VAL_KIND_INT    => 'int',
      VAL_KIND_FLOAT  => 'float',
      VAL_KIND_STRING => 'string',
      VAL_KIND_BOOL   => 'bool',
      VAL_KIND_LIST   => 'list',
      VAL_KIND_DICT   => 'dict',
      VAL_KIND_NEW    => '???',
      VAL_KIND_NULL   => 'null',
      VAL_KIND_SYMBOL => '???',
      VAL_KIND_TUPLE  => 'tuple',
      VAL_KIND_NONE   => '<none>'
    ];
    
    // circular reference check
    assert($this->guard);
    
    // new-value
    if ($this->kind === VAL_KIND_NEW)
      $res = 'instance of ' . $this->unwrap()->path();
    
    // reference to a symbol
    elseif ($this->kind === VAL_KIND_SYMBOL) {
      $this->guard = false;
      $res = $this->unwrap()->value->inspect();
      $this->guard = true;
    }
    
    // normal value
    else {
      $res = $types[$this->kind];
      
      switch ($this->kind) {
        case VAL_KIND_INT:
        case VAL_KIND_FLOAT:
        case VAL_KIND_STRING:
          $res .= ' ' . $this->data;
          break;
        
        case VAL_KIND_BOOL:
          $res .= ' ' . ($this->data ? 'true' : 'false');
          break;
      }
    }
    
    return $res;
  }
  
  /**
   * casts the assigned data
   *
   * @return void
   */
  private function cast(&$data)
  {
    if ($this->kind === VAL_KIND_NEW ||
        $this->kind === VAL_KIND_SYMBOL)
      goto out;
          
    switch ($this->kind) {
      case VAL_KIND_NULL:
      case VAL_KIND_NONE:
      case VAL_KIND_UNDEF:
        $data = null;
        break;
      
      case VAL_KIND_INT:
        $data = (int) $data;
        break;
        
      case VAL_KIND_FLOAT:
        $data = (float) $data;
        break;
        
      case VAL_KIND_STR:
      case VAL_KIND_STRING:
        $data = (string) $data;
        break;
        
      case VAL_KIND_BOOL:
        $data = (bool) $data;
        break;
        
      case VAL_KIND_LIST:
      case VAL_KIND_DICT:   
      case VAL_KIND_TUPLE:
        if (!is_array($data))
          $data = [];
        break;
      
      default:
        assert(0);
    }
    
    out:
    return $data;
  }
  
  /**
   * sets new data 
   *
   * @param  mixed $data
   * @return void
   */
  public function update($data)
  {
    assert($this->is_mutable());
    $this->data = $this->cast($data);
  }
  
  /**
   * makes the value immutable
   *
   * @return void
   */
  public function freeze()
  {
    $this->frozen = true;
  }
  
  /**
   * checks if some kind of value is available
   *
   * @return boolean
   */
  public function is_some()
  {
    return $this->kind !== VAL_KIND_NONE &&
           $this->kind !== VAL_KIND_UNDEF;
  }
  
  /**
   * checks if no actual value is available
   *
   * @return boolean
   */
  public function is_none()
  {
    return $this->kind === VAL_KIND_NONE;
  }
  
  /**
   * checks if the value is undefined
   *
   * @return boolean
   */
  public function is_undef()
  {
    return $this->kind === VAL_KIND_UNDEF;
  }
  
  /**
   * check if the value has a type, but the acutal value is not known
   *
   * @return boolean
   */
  public function is_unkn()
  {
    return $this->is_none() || 
           $this->is_undef() ||
           ($this->kind !== VAL_KIND_NULL && 
            $this->data === null);
  }
  
  /**
   * checks if the value can be changed
   *
   * @return boolean
   */
  public function is_mutable()
  {
    return !$this->frozen;
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
  
  /* ------------------------------------ */
  
  /**
   * converts the given value to a string
   *
   * @param  Value  $val
   * @return boolean
   */
  public function as_str()
  {    
    if ($this->kind === VAL_KIND_STRING)
      return true;
    
    assert($this->is_mutable());
     
    $data = $this->data;
    
    switch ($this->kind) {
      case VAL_KIND_FLOAT:
      case VAL_KIND_BOOL:
      case VAL_KIND_STRING:
      case VAL_KIND_LIST:
      case VAL_KIND_DICT:
      case VAL_KIND_INT:
        $data = (string) $data;
        break;
        
      case VAL_KIND_NULL:
      case VAL_KIND_NONE:
        $data = ''; 
        break;
      
      case VAL_KIND_TUPLE:
      case VAL_KIND_NEW:
      case VAL_KIND_UNDEF:
        return false;
        
      default:
        assert(0);
    }
    
    $this->kind = VAL_KIND_STRING;
    $this->data = $data;
    
    return true;
  }
    
  /**
   * converts the given value to an int
   *
   * @param  Value  $val
   * @return boolean
   */
  public function as_int()
  {
    if ($this->kind === VAL_KIND_INT)
      return true;
    
    assert($this->is_mutable());
    
    $data = $this->data;
    $okay = true;
    
    switch ($this->kind) {
      case VAL_KIND_FLOAT:
      case VAL_KIND_BOOL:
      case VAL_KIND_STRING:
      case VAL_KIND_TUPLE:
        $data = (int) $data;
        break;
        
      case VAL_KIND_NULL: 
      case VAL_KIND_NONE:
        $data = 0; 
        break;
      
      case VAL_KIND_LIST:
        $data = $data->size() ? 1 : 0;
        break;
        
      case VAL_KIND_DICT:
      case VAL_KIND_NEW:
      case VAL_KIND_UNDEF:
        return false;
      default:
        assert(0);
    }
    
    $this->kind = VAL_KIND_INT;
    $this->data = $data;
    
    return true;
  }
  
  /**
   * converts the given value to a float
   *
   * @param  Value  $val
   * @return boolean
   */
  public function as_float()
  {
    if ($this->kind === VAL_KIND_FLOAT)
      return true;
    
    assert($this->is_mutable());
    
    $data = $this->data;
    
    switch ($this->kind) {
      case VAL_KIND_INT:
      case VAL_KIND_BOOL:
      case VAL_KIND_STRING:
      case VAL_KIND_TUPLE:
        $data = (float) $data;
        break;
        
      case VAL_KIND_NULL: 
      case VAL_KIND_NONE:
        $data = 0.0; 
        break;
      
      case VAL_KIND_LIST:
        $data = $data->size() > 1 ? 1 : 0;
        break;
        
      case VAL_KIND_DICT:
      case VAL_KIND_NEW:
      case VAL_KIND_UNDEF:
        return false;
      default:
        assert(0);
    }
    
    $this->kind = VAL_KIND_FLOAT;
    $this->data = $data;
    
    return true;
  }
  
  /**
   * converts the given value to a number
   *
   * @param  Value  $val
   * @return boolean
   */
  public function as_num()
  {
    if ($this->kind === VAL_KIND_INT ||
        $this->kind === VAL_KIND_FLOAT)
      return true;
    
    if ($this->kind === VAL_KIND_BOOL)
      return $this->as_int();
    
    return $this->as_float();
  }
  
  /**
   * converts a value to a boolean
   *
   * @param  Value  $val
   * @return boolean
   */
  public function as_bool()
  {
    if ($this->kind === VAL_KIND_BOOL)
      return true;
    
    assert($this->is_mutable());
    
    $data = $this->data;
    
    switch ($this->kind) {
      case VAL_KIND_INT:
      case VAL_KIND_BOOL:
      case VAL_KIND_STRING:
        $data = (bool) $data;
        break;
        
      case VAL_KIND_NULL: 
      case VAL_KIND_NONE:
        $data = false; 
        break;
      
      case VAL_KIND_LIST:
      case VAL_KIND_DICT:
        $data = $data->size() > 0;
        break;
      
      case VAL_KIND_TUPLE:
        $data = !empty ($data);
        break;
        
      case VAL_KIND_NEW:
      case VAL_KIND_UNDEF:
        return false;
        
      default:
        assert(0);
    }
    
    $this->kind = VAL_KIND_BOOL;
    $this->data = $data;
    
    return true;
  }
  
  /* ------------------------------------ */
  
  /**
   * converts the value to a string and returns the new value.
   *
   * @return Result<Value>
   */
  public function to_str()
  { 
    $self = clone $this;
    
    if ($self->as_str())
      return Result::Some($self);
    
    return Result::Error();
  }
  
  /**
   * converts the value to an int and returns the new value.
   *
   * @return Result<Value>
   */
  public function to_int()
  { 
    $self = clone $this;
    
    if ($self->as_int())
      return Result::Some($self);
    
    return Result::Error();
  }
  
  /**
   * converts the value to a float and returns the new value.
   *
   * @return Result<Value>
   */
  public function to_float()
  { 
    $self = clone $this;
    
    if ($self->as_float())
      return Result::Some($self);
    
    return Result::Error();
  }
  
  /**
   * converts the value to a number and returns the new value.
   *
   * @return Result<Value>
   */
  public function to_num()
  { 
    $self = clone $this;
    
    if ($self->as_num())
      return Result::Some($self);
    
    return Result::Error();
  }
  
  /**
   * converts the value to a boolean and returns the new value.
   *
   * @return Result<Value>
   */
  public function to_bool()
  { 
    $self = clone $this;
    
    if ($self->as_bool())
      return Result::Some($self);
    
    return Result::Error();
  }
  
  /* ------------------------------------ */
  
  /**
   * creates a new value and freezes it immediately
   *
   * @param  int $type
   * @param  mixed $data
   * @return Value
   */
  public static function immutable($type, $data = null)
  {
    $val = new static($type, $data);
    $val->freeze();
    return $val;
  }
}

// undefined values must be immutable
Value::$NONE = Value::immutable(VAL_KIND_NONE);
Value::$UNDEF = Value::immutable(VAL_KIND_UNDEF);
