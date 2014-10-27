<?php

namespace phs;

use RuntimeException as RTEx;

use phs\ast\Name;
use phs\ast\Ident;
use phs\ast\TypeId;

use phs\util\Set;
use phs\util\LooseSet;

const
  TY_NULL  = 0,
  TY_NUM   = 1,
  TY_LIST  = 2,
  TY_DICT  = 3,
  TY_TUPLE = 4,
  TY_BOOL  = 5,
  TY_STR   = 6,
  TY_NEW   = 7,
  TY_NONE  = 8,
  TY_UNKN  = 9,
  TY_FN    = 10,
  TY_REF   = 11
;

class Type
{
  // @var int  type-id
  public $kind;
  
  // @var Symbol  hint
  public $hint;
  
  /**
   * constructor
   *
   * @param int $kind
   * @param Symbol|TypeSet  $hint
   */
  public function __construct($kind, $hint = null)
  {
    assert($hint === null ||
           $hint instanceof Symbol ||
           $hint instanceof TypeSet);
    
    $this->kind = $kind;
    $this->hint = $hint;
  }
  
  public function __tostring() 
  {
    switch ($this->kind) {
      case TY_NULL:
        return 'null';
      case TY_NUM:
        return 'num';
      case TY_LIST:
        return 'list';
      case TY_DICT:
        return 'dict';
      case TY_TUPLE:
        return 'tuple';
      case TY_BOOL:
        return 'bool';
      case TY_STR:
        return 'str';
      case TY_NEW:
        $out = '<inst> ';
        if ($this->hint !== null)
          $out .= (string) $this->hint;
        else
          $out .= '<unkn>';
        return $out;
      case TY_NONE:
        return '<none>';
      case TY_UNKN:
        return '<unkn>';
      case TY_FN:
        return '<fn> [ ' . ((string) $this->hint->types) . ' ]';
      case TY_REF:
        return '<ref> ' . (string) $this->hint;
      default:
        assert(0);
    }
  }
  
  /**
   * checks if the given type is primitive
   *
   * @return boolean
   */
  public function is_primitive()
  {
    return $this->kind === TY_NULL ||
           $this->kind === TY_NUM ||
           $this->kind === TY_BOOL ||
           $this->kind === TY_STR;
  }
  
  /**
   * checks if the given type is intrinsic
   *
   * @return boolean
   */
  public function is_intrinsic()
  {
    return $this->is_primitive() ||
           $this->kind === TY_LIST ||
           $this->kind === TY_DICT ||
           $this->kind === TY_TUPLE;
  }
  
  /**
   * checks if the given type is complex
   *
   * @return boolean
   */
  public function is_complex()
  {
    return $this->kind === TY_LIST ||
           $this->kind === TY_DICT ||
           $this->kind === TY_TUPLE ||
           $this->kind === TY_NEW;
  }
  
  /**
   * creates a new type from a hint
   *
   * @param  mixed $hint
   * @return Type
   */
  public static function from($hint)
  {
    // from Value
    if ($hint instanceof Value) {
      $kind = TY_UNKN;
      
      switch ($hint->kind) {
        case VAL_KIND_UNDEF:  $kind = TY_UNKN;  break;
        case VAL_KIND_STR:    $kind = TY_STR;   break;
        case VAL_KIND_BOOL:   $kind = TY_BOOL;  break;
        case VAL_KIND_LIST:   $kind = TY_LIST;  break;
        case VAL_KIND_DICT:   $kind = TY_DICT;  break;
        case VAL_KIND_NULL:   $kind = TY_NULL;  break;
        case VAL_KIND_TUPLE:  $kind = TY_TUPLE; break;
        case VAL_KIND_NONE:   $kind = TY_NONE;  break;
        
        case VAL_KIND_INT:
        case VAL_KIND_FLOAT:
          $kind = TY_NUM; 
          break;
        
        case VAL_KIND_NEW:
        case VAL_KIND_SYMBOL:
          assert(0);
      }
      
      return new Type($kind);
    }
    
    // from TypeId
    elseif ($hint instanceof TypeId) {
      $kind = TY_UNKN;
      
      switch ($hint->type) {
        case T_TBOOL: 
          $kind = TY_BOOL;
          break;
          
        case T_TINT:
        case T_TFLOAT:
          $kind = TY_NUM;
          break;
            
        case T_TSTRING: 
        case T_TREGEXP:
          $kind = TY_STR;
          break;
        
        default:
          assert(0);
      }
      
      return new Type($kind);
    }
    
    // from Name/Ident
    elseif ($hint instanceof Name ||
            $hint instanceof Ident) {
      if ($hint->symbol instanceof ClassSymbol)
        return new Type(TY_NEW, $hint->symbol);
      
      if ($hint->smbol instanceof VarSymbol)
        return new Type(TY_NEW, $hint->symbol->types);
    }
    
    throw new RTEx('cannot infer type');
  }
}

class TypeSet extends LooseSet
{
  // @var Set  references
  private $refs;
  
  /**
   * constructor
   *
   */
  public function __construct()
  {
    $this->refs = new Set;
  }
  
  public function __tostring() 
  {
    $list = [];
    foreach ($this->iter() as $type)
      $list[] = "$type";
    
    return implode(', ', $list);
  }
  
  /**
   * references a other TypeSet.
   * all types added to the other typeset will be added to this one too. 
   *
   * @param  TypeSet $orig
   */
  public function ref(TypeSet $orig)
  {
    $this->ins($orig);
    $orig->refs->add($this);
  }
  
  /**
   * @see Set#add()
   *
   * @param mixed $ent
   */
  public function add($ent)
  {
    if ($this->check($ent))
      // remove <none> types
      foreach ($this->iter() as $cur)
        if ($cur->kind === TY_NONE)
          $this->delete($cur);  
    
    if (parent::add($ent))
      foreach ($this->refs->iter() as $ref)
        $ref->add($ent);
  }
  
  /**
   * @see Set#check()
   *
   * @param  mixed $v
   * @return bool
   */
  public function check($v)
  {
    return $v instanceof Type;
  }
  
  /**
   * @see LooseSet#compare()
   *
   * @param  Type $a
   * @param  Type $b
   * @return bool
   */
  public function compare($a, $b)
  {
    if ($a->kind !== $b->kind)
      return false;
    
    if ($a->kind === TY_NEW)
      return $b->hint === $a->hint;
    
    return true;
  }
}
