<?php

namespace phs\front;

require_once 'utils.php';
require_once 'visitor.php';

use phs\Logger;
use phs\Session;

use phs\front\ast\Node;
use phs\front\ast\Unit;
use phs\front\ast\Expr;
use phs\front\ast\Name;
use phs\front\ast\Param;
use phs\front\ast\ThisParam;
use phs\front\ast\RestParam;
use phs\front\ast\NamedArg;
use phs\front\ast\RestArg;
use phs\front\ast\ObjLit;
use phs\front\ast\ObjKey;
use phs\front\ast\FnExpr;
use phs\front\ast\CallExpr;
use phs\front\ast\UseAlias;
use phs\front\ast\UseUnpack;
use phs\front\ast\StrLit;

class AstFormatter extends Visitor
{
  // @var int
  private $tabs = 0;
  
  // @var string
  private $buff = '';
  
  // @var bool  hide strings
  private $hstr;
  
  // @var array
  private $strs = [];
  
  /**
   * constructor
   *
   */
  public function __construct()
  {
    parent::__construct();
  }
  
  /**
   * foramts the unit back to its source-code
   *
   * @param  Unit   $unit
   * @return string
   */
  public function format(Unit $unit)
  {
    $this->buff = '';
    $this->hstr = true;
    
    $this->emitln('/* generated by phs (ast to source) */');
    $this->visit($unit);
    
    // remove unnecessary new-lines at the end of blocks
    $this->buff = preg_replace('/(?:\n\h*)+\n(\h*)\}/s', "\n$1}", $this->buff);
    
    // replace `for (;;) {}` with `for {...}`
    $this->buff = preg_replace('/\bfor\s*\(\s*;\s*;\s*\)/', 'for', $this->buff);
    
    // remove trailing new-lines and whitespace
    $this->buff = rtrim($this->buff, "\n ");
    
    // remove trailing ";"
    $this->buff = preg_replace('/(?:\s*[;]){2,}$/', ';', $this->buff);
    
    // add final new-line
    $this->buff .= "\n";
    
    // insert strings
    $this->buff = preg_replace_callback('/\\\\(\d+)/', function($m) {
      return $this->strs[(int)$m[1]];
    }, $this->buff);
    
    // done!
    $buff = $this->buff;
    unset ($this->buff); // not longer needed
    return $buff;
  }
  
  /**
   * writes something to the buffer
   *
   * @param  string $msg
   * @return void
   */
  private function emit($msg)
  {
    $this->buff .= $msg;
  }
  
  /**
   * writes something to the buffer + a new line at the end
   *
   * @param  string $msg
   * @return void
   */
  private function emitln($msg)
  {
    $this->emit($msg);
    $this->emit("\n");
    $this->emit(str_repeat('  ', $this->tabs));
  }
  
  /**
   * emits `use`
   *
   * @param  Nmae|UseAlias|UseUNpack $item
   * @return void
   */
  private function emit_use($item)
  {
    if ($item instanceof Name)
      $this->visit($item);
    elseif ($item instanceof UseAlias) {
      $this->visit($item->name);
      $this->emit(' as ');
      $this->visit($item->alias);
    } elseif ($item instanceof UseUnpack) {
      $this->visit($item->base);
      $this->tabs++;
      $this->emitln(' {');
      
      foreach ($item->items as $idx => $sub) {
        if ($idx > 0) $this->emit(', ');
        $this->emit_use($sub);
      }
      
      $this->tabs--;
      $this->emitln('');
      $this->emitln('}');
    } else
      assert(0);
  }
  
  /**
   * emits modifiers
   *
   * @param  array $mods
   * @return void
   */
  private function emit_mods($mods)
  {
    if ($mods) {
      $this->emit(implode(' ', mods_to_arr($mods)));
      $this->emit(' ');
    }
  }
  
  /**
   * emits function-parameters
   *
   * @param  array $params
   * @return void
   */
  private function emit_params($params) 
  {
    $this->emit('(');
    
    if (!empty($params)) {
      $len = count($params);
      foreach ($params as $idx => $param) {
        if ($param->hint) {
          $this->visit($param->hint);
          $this->emit(' ');
        }
        
        if ($param instanceof ThisParam) {
          if ($param->ref) $this->emit('&');
          $this->emit('this.');
        } elseif ($param instanceof RestParam)
          $this->emit('...');
        elseif ($param->mods)
          $this->emit_mods($param->mods);
        
        if ($param instanceof Param && $param->ref)
          $this->emit('&');
        
        $this->emit(ident_to_str($param->id));
        
        if ($param instanceof Param) {
          if ($param->opt)
            $this->emit('?');
          elseif ($param->init) {
            $this->emit(' = ');
            $this->visit($param->init);
          }
        }
        
        if ($idx + 1 < $len) 
          $this->emit(', ');
      }
    }
    
    $this->emit(')');
  }
  
  /**
   * emits function-arguments
   *
   * @param  array $args
   * @return void
   */
  private function emit_args($args)
  {
    $this->emit('(');
    
    if (!empty($args)) {
      $len = count($args);
      foreach ($args as $idx => $arg) {
        if ($arg instanceof NamedArg) {
          $this->visit($arg->name);
          $this->emit(': ');
          $this->visit($arg->expr);
        } elseif ($arg instanceof RestArg) {
          $this->emit('...');
          $this->visit($arg->expr);
        } else
          $this->visit($arg);
        
        if ($idx + 1 < $len)
          $this->emit(', ');
      }
    }
    
    $this->emit(')');
  }
  
  /**
   * emits a expression
   *
   * @param  array $expr
   * @param  boolean $top
   * @return void
   */
  private function emit_expr($expr, $top = false) 
  {
    $len = count($expr);
    foreach ($expr as $idx => $ex) {
      if ($idx > 0) $this->emit(', ');
      
      $paren = false;
      
      // special case: (fn()...)();
      if ($top === true && $this->is_piife($ex)) {
        $this->emit('(');
        $paren = true;
      }
      
      $this->visit($ex);
      
      if ($paren)
        $this->emit(')');
    }
    
    // parens for iife's not longer needed
    $top = false; 
  }
  
  /**
   * emits a value
   *
   * @param  Value $value
   * @return boolean
   */
  private function emit_value(Value $value = null)
  {
    if (!$value || $value->kind === VAL_KIND_UNDEF || 
                   $value->kind === VAL_KIND_NONE) 
      return false;
     
    switch ($value->kind) {
      case VAL_KIND_INT:
      case VAL_KIND_FLOAT:
        $this->emit((string) $value->data);
        break;
        
      case VAL_KIND_STR:
        $this->emit_str_value($value->data);
        break;
        
      case VAL_KIND_BOOL:
        $this->emit_bool_value($value->data);
        break;
        
      case VAL_KIND_LIST:
        $this->emit_list_value($value->data);
        break;
        
      case VAL_KIND_TUPLE:
        $this->emit_tuple_value($value->data);
        break;
        
      case VAL_KIND_DICT:
        $this->emit_dict_Value($value->data);
        break;
        
      case VAL_KIND_NULL:
        $this->emit('null');
        break;
        
      case VAL_KIND_NEW:
      case VAL_KIND_SYMBOL:
        return false;
      
      default:
        assert(0);
    }
    
    return true;
  }
  
  /**
   * emits a string value
   *
   * @param  string $val
   * @return void
   */
  private function emit_str_value($val)
  {
    $buf = '"' . $this->format_str($val, '"') . '"';
    
    if ($this->hstr) {
      $this->emit('\\');
      $this->emit(array_push($this->strs, $buf) - 1);
    } else
      $this->emit($buf);
  }
  
  /**
   * emits a boolean value
   *
   * @param  boolean $val
   * @return void
   */
  private function emit_bool_value($val) 
  {
    $this->emit($val ? 'true' : 'false');
  }
  
  /**
   * emits a list value
   *
   * @param  array $list
   * @return void
   */
  private function emit_list_value($list)
  {
    if (empty ($list)) {
      $this->emit('[]');
      return;
    }
    
    $this->emit('[');
    $this->tabs++;
    $this->emitln(' ');
    
    foreach ($list as $idx => $item) {
      if ($idx > 0)
        $this->emitln(',');
      
      $this->emit_value($item);
    }
    
    $this->tabs--;
    $this->emitln('');
    $this->emit(']');
  }
  
  /**
   * emits a tuple value
   *
   * @param  array $list
   * @return void
   */
  private function emit_tuple_value($list)
  {
    if (empty ($list)) {
      $this->emit('()');
      return;
    }
    
    $this->emit('(');
    $this->tabs++;
    $this->emitln(' ');
    
    foreach ($list as $idx => $item) {
      if ($idx > 0)
        $this->emitln(',');
      
      $this->emit_value($item);
    }
    
    if (count($list) === 1)
      $this->emit(',');
    
    $this->tabs--;
    $this->emitln('');
    $this->emit(')');
  }
  
  /**
   * emits a list value
   *
   * @param  array $list
   * @return void
   */
  private function emit_dict_value($dict)
  {
    if (empty ($dict)) {
      $this->emit('{}');
      return;
    }
    
    $this->emit('{');
    $this->tabs++;
    $this->emitln(' ');
    
    $idx = 0;
    foreach ($dict as $key => $val) {
      if ($idx++ > 0)
        $this->emitln(',');
      
      $this->emit_str($key);
      $this->emit(': ');
      $this->emit_value($item);
    }
    
    $this->tabs--;
    $this->emitln('');
    $this->emit('}');
  }
  
  /**
   * checks if a expression is a 
   * immediately-invoked function expression (iife) in parentheses
   * 
   * @param  Node  $ex
   * @return boolean
   */
  private function is_piife($ex)
  {
    return ($ex instanceof CallExpr && 
      is_array($ex->callee) &&
      $ex->callee[0] instanceof FnExpr);
  }
  
  /**
   * emits attributes
   *
   * @param  array $items
   * @return void
   */
  private function emit_attr($items)
  {
    foreach ($items as $idx => $attr) {
      if ($idx > 0) $this->emit(', ');
      $this->emit(ident_to_str($attr->name));
      
      if ($attr->value) {
        $this->emit('(');
        $this->emit_attr_val($attr->value);
        $this->emit(')');
      }
    }
  }
  
  /**
   * emits a attribut value
   *
   * @param  AttrVal|<Literal> $attr
   * @return void
   */
  private function emit_attr_val($attr)
  {
    $this->emit(ident_to_str($attr->name));
    
    if ($attr->sub instanceof AttrVal) {
      $this->emit('(');
      $this->emit_attr_val($attr->sub);
      $this->emit(')');
    } else {
      $this->emit('=');
      $this->visit($attr->sub);
    }
  }
  
  /**
   * emits trait-usage 
   * 
   * @param  array  $traits
   */
  private function emit_traits($traits) 
  {
    if (!$traits) return;
    
    foreach ($traits as $node) {
      $this->emit('use ');
      $this->visit($node->name);
      
      if ($node->items) {
        $this->tabs++;
        $this->emitln(' {');
        
        foreach ($node->items as $item) {
          if ($item instanceof Name)
            $this->visit($item);
          else {
            $this->emit(ident_to_str($item->id));
            $this->emit(' as ');
            $this->emit_mods($item->mods);
            $this->emit(ident_to_str($item->alias));
          }
          
          $this->emitln(';');
        }
        
        $this->tabs--;
        $this->emitln('');
        $this->emit('}');
      }
      
      $this->emitln(';');
    }
  }
  
  /**
   * opens a block
   *
   * @param  string $msg
   * @return void
   */
  private function open_block($msg)
  {
    $this->emit($msg);
    $this->tabs++;
    $this->emitln('');
  }
  
  /**
   * closes a block
   *
   * @param  string $msg
   * @return void
   */
  private function close_block($msg)
  {
    $this->tabs--;
    $this->emitln('');
    $this->emitln($msg);
  }
  
  /**
   * formats an interpreted string to a source-string
   *
   * @param  string $str
   * @param  string $dlm
   * @return string
   */
  private function format_str($str, $dlm)
  {
    $esc = false;
    $buf = '';
    
    for ($i = 0, $l = strlen($str); $i < $l; ++$i) {
      $c = $str[$i];
      
      if ($c === '\\')
        $esc = !$esc;
      elseif ($c === '"') {
        if ($esc) 
          $esc = false;
        else
          $buf .= '\\';
      } else {
        switch ($c) {
          case "\n": $c = '\\n'; break; 
          case "\r": $c = '\\r'; break;          
          case "\t": $c = '\\t'; break;         
          case "\f": $c = '\\f'; break;          
          case "\v": $c = '\\v'; break;          
          case "\e": $c = '\\e'; break;
          case '\\': $c = '\\\\'; break;
          case $dlm: $c = "\\{$dlm}"; break;
        }
      }
      
      $buf .= $c;
    }
    
    return $buf;
  }
  
  /**
   * handles a string-literal
   *
   * @param  Node $node
   * @return string
   */
  private function handle_str($node)
  {
    $buf = '';
    $buf .= $node->flag;
    $buf .= '"';
    $buf .= $this->format_str($node->data, $node->delim);
    
    if ($node->flag !== 'r' && count($node->parts))
      foreach ($node->parts as $idx => $part) {
        if ($idx & 1) 
          $buf .= $this->format_str($part->data, $node->delim);
        else {
          $buf .= '${';
          $buf .= $this->handle_expr($part);
          $buf .= '}';
        }
      }
      
    $buf .= '"';
    return $buf;
  }
  
  /**
   * handles a sub-expression (e.g. in string-interpolation)
   *
   * @param  Node $node
   * @return string
   */
  private function handle_expr($node)
  {
    $buff = $this->buff;
    
    $this->buff = '';
    $this->hstr = false;
    
    $this->visit($node);
    
    $resl = $this->buff;
    $this->buff = $buff;
    
    return $resl;
  }
  
  /**
   * Visitor#visit_module()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_module($node)
  {
    $this->emit('module ');
    
    if ($node->name) {
      $this->emit(name_to_str($node->name));
      $this->emit(' ');
    }
    
    $this->open_block('{');
    $this->visit($node->body);
    $this->close_block('}');
  }
  
  /**
   * Visitor#visit_block()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_block($node)
  {
    $this->open_block('{');
    $this->visit($node->body);
    $this->close_block('}');
  }
  
  /**
   * Visitor#visit_enum_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_enum_decl($node) 
  {
    $this->emit_mods($node->mods);
    $this->emit('enum ');
    $this->open_block('{');
    
    $len = count($node->vars);
    foreach ($node->vars as $idx => $var) {
      $this->emit(ident_to_str($var->id));
      if ($var->init) {
        $this->emit(' = ');
        $this->visit($var->init);
      }
      
      if ($idx + 1 < $len)
        $this->emitln(',');
    }
    
    $this->close_block('}');
  }
  
  /**
   * Visitor#visit_class_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_class_decl($node) 
  {
    $this->emit_mods($node->mods);
    $this->emit('class ');
    $this->emit(ident_to_str($node->id));
    
    if ($node->ext) {
      $this->emit(' : ');
      $this->emit(name_to_str($node->ext));
    }
    
    if ($node->impl) {
      $this->emit(' ~ ');
      $this->emit(implode(', ', array_map('phs\front\name_to_str', $node->impl)));
    }  
    
    if ($node->members === null)
      $this->emitln(';');
    else {
      $this->emit(' ');
      $this->open_block('{');
      $this->emit_traits($node->traits);
      $this->visit($node->members);
      $this->close_block('}');
    }
  }
  
  /**
   * Visitor#visit_nested_mods()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_nested_mods($node) 
  {
    $this->emit_mods($node->mods);
    $this->open_block('{');
    $this->visit($node->members);
    $this->close_block('}');  
  }
  
  /**
   * Visitor#visit_ctor_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_ctor_decl($node) 
  {
    $this->emit_mods($node->mods);
    $this->emit('new ');
    $this->emit_params($node->params);
    
    if (!$node->body)
      $this->emitln(';');
    else {
      $this->emit(' ');
      $this->visit($node->body);  
    }
  }
  
  /**
   * Visitor#visit_dtor_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_dtor_decl($node) 
  {
    $this->emit('del ');
    $this->emit_params($node->params);
    
    if (!$node->body)
      $this->emitln(';');
    else {
      $this->emit(' ');
      $this->visit($node->body);  
    }
  }
  
  /**
   * Visitor#visit_getter_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_getter_decl($node) 
  {
    $this->emit('get ');
    $this->emit(ident_to_str($node->id));
    $this->emit_params($node->params);
    $this->emit(' ');
    $this->visit($node->body);  
  }
  
  /**
   * Visitor#visit_setter_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_setter_decl($node) 
  {
    $this->emit('set ');
    $this->emit(ident_to_str($node->id));
    $this->emit_params($node->params);
    $this->emit(' ');
    $this->visit($node->body);
  }
  
  /**
   * Visitor#visit_member_attr()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_member_attr($node)
  {
    $this->emit('@ ');
    $this->emit_attr($node->attr->items);
    $this->emitln('');
    $this->visit($node->member);
  }
  
  /**
   * Visitor#visit_trait_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_trait_decl($node) 
  {
    $this->emit_mods($node->mods);
    $this->emit('trait ');
    $this->emit(ident_to_str($node->id));
    
    if ($node->members === null)
      $this->emitln(';');
    else {
      $this->emit(' ');
      $this->open_block('{');
      $this->emit_traits($node->traits);
      $this->visit($node->members);
      $this->close_block('}');
    }
  }
  
  /**
   * Visitor#visit_iface_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_iface_decl($node) 
  {
    $this->emit_mods($node->mods);
    $this->emit('iface ');
    $this->emit(ident_to_str($node->id));
    
    if ($node->exts) {
      $this->emit(' : ');
      $this->emit(implode(', ', array_map('phs\front\name_to_str', $node->exts)));
    }
    
    if ($node->members === null)
      $this->emitln(';');
    else {
      $this->emit(' ');
      $this->open_block('{');
      $this->visit($node->members);
      $this->close_block('}');
    }
  }
  
  /**
   * Visitor#visit_fn_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_fn_decl($node) 
  {
    $this->emit_mods($node->mods);
    $this->emit('fn ');
    $this->emit(ident_to_str($node->id));
    $this->emit_params($node->params);
    
    if (!$node->body)
      $this->emitln(';');
    else {
      $this->emit(' ');
      $this->visit($node->body);
    }
  }
  
  /**
   * Visitor#visit_attr_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_attr_decl($node) 
  {
    $this->emit('@ ');
    $this->emit_attr($node->attr->items);
    $this->emitln(';');
  }
  
  /**
   * Visitor#visit_topex_attr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_topex_attr($node) 
  {
    $this->emit('@ ');
    $this->emit_attr($node->attr->items);
    $this->emitln('');
    $this->visit($node->topex);  
  }
  
  /**
   * Visitor#visit_comp_attr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_comp_attr($node) 
  {
    $this->emit('@ ');
    $this->emit_attr($node->attr->items);
    $this->emitln('');
    $this->visit($node->comp); 
  }  
  
  /**
   * Visitor#visit_var_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_var_decl($node) 
  {
    $this->emit_mods($node->mods);
    if (!$node->mods) $this->emit('let ');
    
    $len = count($node->vars);
    foreach ($node->vars as $idx => $var) {
      $this->emit(ident_to_str($var->id));
      if ($var->init) {
        $this->emit(' = ');
        $this->visit($var->init);
      }
      
      if ($idx + 1 < $len)
        $this->emit(',');
    }  
    
    $this->emitln(';');
  }
  
  /**
   * Visitor#visit_use_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_use_decl($node) 
  {
    $this->emit('use ');
    $this->emit_use($node->item);
    $this->buff = rtrim($this->buff);
    $this->emitln(';');
  }
  
  /**
   * Visitor#visit_require_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_require_decl($node) 
  {
    $this->emit('require ');
    if ($node->php) $this->emit('__php__ ');
    $this->visit($node->expr);
    $this->emitln(';');
  }
  
  /**
   * Visitor#visit_label_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_label_decl($node) 
  {
    $this->visit($node->id);
    $this->emit(': ');
    $this->visit($node->stmt);  
  }
  
  /**
   * Visitor#visit_do_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_do_stmt($node) 
  {
    $this->emit('do ');
    $this->visit($node->stmt);
    $this->buff = rtrim($this->buff);
    $this->emit(' while (');
    $this->visit($node->test);
    $this->emitln(');');  
  }
  
  /**
   * Visitor#visit_if_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_if_stmt($node) 
  {
    $this->emit('if (');
    $this->visit($node->test);
    $this->emit(') ');
    $this->visit($node->stmt);
    
    if ($node->elsifs) {
      foreach ($node->elsifs as $elsif) {
        $this->buff = rtrim($this->buff);
        $this->emit(' elsif (');
        $this->visit($elsif->test);
        $this->emit(') ');
        $this->visit($elsif->stmt);   
      } 
    } 
    
    if ($node->els) {
      $this->buff = rtrim($this->buff);
      $this->emit(' else ');
      $this->visit($node->els->stmt);
    }
  }
  
  /**
   * Visitor#visit_for_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_for_stmt($node) 
  {
    $this->emit('for (');
      
    if ($node->init)
      $this->visit($node->init->expr);
    
    $this->emit('; ');
    
    if ($node->test)
      $this->visit($node->test->expr);
    
    $this->emit('; ');
    $this->visit($node->each);
    $this->emit(') ');
    $this->visit($node->stmt);
  }
  
  /**
   * Visitor#visit_for_in_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_for_in_stmt($node) 
  {
    $this->emit('for (');
    
    if ($node->lhs->key !== null) {
      $this->emit(ident_to_str($node->lhs->key));
      $this->emit(': ');  
    }
    
    $this->emit(ident_to_str($node->lhs->value));
    $this->emit(' in ');
    $this->visit($node->rhs);
    $this->emit(') ');
    
    $this->visit($node->stmt);
  }
  
  /**
   * Visitor#visit_try_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_try_stmt($node) 
  {
    $this->emit('try ');
    $this->visit($node->body);
    
    if ($node->catches) {
      foreach ($node->catches as $catch) {
        $this->buff = rtrim($this->buff);
        $this->emit(' catch ');
          
        if ($catch->name) {
          $this->emit('(');
          $this->visit($catch->name);
          
          if ($catch->id) {
            $this->emit(': ');
            $this->visit($catch->id);
          }
          
          $this->emit(') ');
        }
        
        $this->visit($catch->body);      
      }
    }  
    
    if ($node->finalizer) {
      $this->buff = rtrim($this->buff);
      $this->emit(' finally ');
      $this->visit($node->finalizer->body);
    }
  }
  
  /**
   * Visitor#visit_php_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_php_stmt($node) 
  {
    $this->tabs++;
    $this->emitln('__php__ {');
    
    if ($node->usage) {
      foreach ($node->usage as $usage) {
        $this->emit('use ');
        foreach ($usage->items as $idx => $item) {
          if ($idx > 0) $this->emit(', ');
          $this->emit(ident_to_str($item->id));
          if ($item->alias) {
            $this->emit(' as ');
            $this->emit(ident_to_str($item->alias));
          }
        }
        $this->emitln(';');
      }
    }
    
    $this->visit($node->code);
    $this->tabs--;
    $this->emitln('');
    $this->emitln('}');
  }
  
  /**
   * Visitor#visit_goto_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_goto_stmt($node) 
  {
    $this->emit('goto ');
    $this->visit($node->id);
    $this->emitln(';');
  }
  
  /**
   * Visitor#visit_test_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_test_stmt($node) 
  {
    $this->emit('__test__');
    
    if ($node->name) {
      $this->emit(' ');
      $this->visit($node->name);
    }
    
    $this->emit(' ');
    $this->visit($node->block);  
  }
  
  /**
   * Visitor#visit_break_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_break_stmt($node) 
  {
    $this->emit('break');
    
    if ($node->id) {
      $this->emit(' ');
      $this->emit(ident_to_str($node->id));
    }  
    
    $this->emitln(';');
  }
  
  /**
   * Visitor#visit_continue_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_continue_stmt($node) 
  {
    $this->emit('continue');
    
    if ($node->id) {
      $this->emit(' ');
      $this->emit(ident_to_str($node->id));
    }  
    
    $this->emitln(';');
  }
  
  /**
   * Visitor#visit_print_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_print_stmt($node) 
  {
    $this->emit('print ');
    $this->visit($node->expr);
    $this->emitln(';');  
  }
  
  /**
   * Visitor#visit_throw_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_throw_stmt($node) 
  {
    $this->emit('throw ');
    $this->visit($node->expr);
    $this->emitln(';');  
  }
  
  /**
   * Visitor#visit_while_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_while_stmt($node) 
  {
    $this->emit('while (');
    $this->visit($node->test);
    $this->emit(') ');
    $this->visit($node->stmt);  
  }
  
  /**
   * Visitor#visit_assert_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_assert_stmt($node) 
  {
    $this->emit('assert ');
    $this->visit($node->expr);
    if ($node->message) {
      $this->emit(' : ');
      $this->visit($node->message);
    }  
    
    $this->emitln(';');
  }
  
  /**
   * Visitor#visit_switch_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_switch_stmt($node) 
  {
    $this->emit('switch (');
    $this->visit($node->test);
    $this->tabs++;
    $this->emitln(') {');
    
    foreach ($node->cases as $case) {
      $len = count($case->labels);
      foreach ($case->labels as $idx => $label) {
        if ($label->expr === null)
          $this->emit('default:');
        else {
          $this->emit('case ');
          $this->visit($label->expr);
          $this->emit(':');
        }
        
        if ($idx + 1 < $len)
          $this->emitln('');
      }
      
      $this->tabs++;
      $this->emitln('');
      $this->visit($case->body);
      $this->tabs--;
      $this->emitln('');
    }
    
    $this->tabs--;
    $this->emitln('');
    $this->emitln('}');
  }
  
  /**
   * Visitor#visit_return_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_return_stmt($node) 
  {
    $this->emit('return');
    
    if ($node->expr) {
      $this->emit(' ');
      $this->visit($node->expr);
    }
    
    $this->emit(';');
  }
  
  /**
   * Visitor#visit_expr_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_expr_stmt($node) 
  {
    if ($node->expr)
      $this->emit_expr($node->expr, true);
    $this->emitln(';');
  }
  
  /**
   * Visitor#visit_paren_expr()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_paren_expr($node)
  {
    if ($this->emit_value($node->value))
      return;
    
    $this->emit('(');
    $this->emit_expr($node->expr);
    $this->emit(')');
  }
  
  /**
   * Visitor#visit_tuple_expr()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_tuple_expr($node)
  {
    if ($this->emit_value($node->value))
      return;
    
    $this->emit('(');
    
    if (empty($node->seq))
      $this->emit(')');
    else {      
      foreach ($node->seq as $idx => $expr) {
        if ($idx > 0) $this->emit(', ');
        $this->visit($expr);
      }
      
      if (count($node->seq) === 1)
        $this->emit(',');
      
      $this->emit(')');
    }
  }
  
  /**
   * Visitor#visit_fn_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_fn_expr($node) 
  {
    $this->emit('fn');
    
    if ($node->id) {
      $this->emit(' ');
      $this->emit(ident_to_str($node->id));
    }
     
    $this->emit_params($node->params);
    $this->emit(' ');
    $this->visit($node->body);
        
    $this->buff = rtrim($this->buff);
  }
  
  /**
   * Visitor#visit_bin_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_bin_expr($node) 
  {
    if ($this->emit_value($node->value))
      return;
    
    $this->visit($node->left);
    $this->emit(' ');
    $this->emit($node->op->value);
    $this->emit(' ');
    $this->visit($node->right);  
  }
  
  /**
   * Visitor#visit_check_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_check_expr($node) 
  {
    if ($this->emit_value($node->value))
      return;
    
    $this->visit($node->left);
    $this->emit(' ');
    $this->emit($node->op->value);
    $this->emit(' ');
    $this->visit($node->right); 
  }
  
  /**
   * Visitor#visit_cast_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_cast_expr($node) 
  {
    if ($this->emit_value($node->value))
      return;
    
    $this->visit($node->expr);
    $this->emit(' as ');
    $this->visit($node->type);  
  }
  
  /**
   * Visitor#visit_update_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_update_expr($node) 
  {
    if ($node->prefix)
      $this->emit($node->op->value);
    
    $this->visit($node->expr);
    
    if (!$node->prefix)
      $this->emit($node->op->value);
  }
  
  /**
   * Visitor#visit_assign_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_assign_expr($node) 
  {
    $this->visit($node->left);
    $this->emit(' ');
    $this->emit($node->op->value);
    $this->emit(' ');
    $this->visit($node->right);  
  }
  
  /**
   * Visitor#visit_member_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_member_expr($node) 
  {
    if ($this->emit_value($node->value))
      return;
    
    $this->visit($node->object);
    $this->emit('.');
      
    if ($node->computed)
      $this->emit('{'); 
    
    $this->visit($node->member);
    
    if ($node->computed)
      $this->emit('}');
  }
  
  /**
   * Visitor#visit_offset_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_offset_expr($node) 
  {
    if ($this->emit_value($node->value))
      return;
    
    $this->visit($node->object);
    $this->emit('[');
    $this->visit($node->offset);
    $this->emit(']');
  }
  
  /**
   * Visitor#visit_cond_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_cond_expr($node) 
  {
    if ($this->emit_value($node->value))
      return;
    
    $this->visit($node->test);
    $this->emit(' ? ');
    
    if ($node->then)
      $this->visit($node->then);
    
    $this->emit(' : ');
    $this->visit($node->els);
  }
  
  /**
   * Visitor#visit_call_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_call_expr($node) 
  {
    $this->visit($node->callee);
    $this->emit_args($node->args);
  }
  
  /**
   * Visitor#visit_yield_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_yield_expr($node) 
  {
    $this->emit('yield ');
    
    if ($node->key) {
      $this->visit($node->key);
      $this->emit(': ');
    }
    
    $this->visit($node->value);
  }
  
  /**
   * Visitor#visit_unary_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_unary_expr($node) 
  {
    if ($this->emit_value($node->value))
      return;
    
    $this->emit($node->op->value);
    $this->visit($node->expr);  
  }
  
  /**
   * Visitor#visit_new_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_new_expr($node) 
  {
    $this->emit('new ');
    $this->visit($node->name);
    $this->emit_args($node->args);  
  }
  
  /**
   * Visitor#visit_del_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_del_expr($node) 
  {
    $this->emit('del ');
    $this->visit($node->expr);
  }
  
  /**
   * Visitor#visit_lnum_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_lnum_lit($node) 
  {
    $this->emit($node->data);  
  }
  
  /**
   * Visitor#visit_dnum_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_dnum_lit($node) 
  {
    $this->emit($node->data);   
  }
  
  /**
   * Visitor#visit_snum_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_snum_lit($node) 
  {
    $this->emit($node->data); 
    $this->emit($node->suffix);  
  }
  
  /**
   * Visitor#visit_regexp_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_regexp_lit($node) 
  {
    $this->emit($node->data);
  }
  
  /**
   * Visitor#visit_arr_gen()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_arr_gen($node)
  {
    $this->emit('[ ');
    $this->visit($node->expr);
    $this->emit(' for (');
    $this->visit($node->init);
    $this->emit(' in ');
    $this->visit($node->each);
    $this->emit(') ]');
  }
  
  /**
   * Visitor#visit_arr_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_arr_lit($node) 
  {
    if ($this->emit_value($node->value))
      return;
    
    if (!$node->items) {
      $this->emit('[]');
      return;  
    }
    
    $this->emit('[');
    $this->tabs++;
    $this->emitln('');
    
    $len = count($node->items);
    foreach ($node->items as $idx => $item) {
      $this->visit($item);
      
      if ($idx + 1 < $len)
        $this->emitln(', ');
    }  
    
    $this->tabs--;
    $this->emitln('');
    $this->emit(']');
  }
  
  /**
   * Visitor#visit_obj_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_obj_lit($node) 
  {    
    if ($this->emit_value($node->value))
      return;
    
    $this->emit('{');
    $this->tabs++;
    $this->emitln('');
    
    $len = count($node->pairs);
    if ($len > 0)
      foreach ($node->pairs as $idx => $pair) {
        if ($pair->key instanceof ObjKey) {
          $this->emit('(');
          $this->visit($pair->key->expr);
          $this->emit(')');
        } else
          $this->visit($pair->key);
        
        $this->emit(': ');
        $this->visit($pair->value);
        if ($idx + 1 < $len)
          $this->emitln(',');
      }
      
    $this->tabs--;
    $this->emitln('');
    $this->emit('}');
  }
  
  /**
   * Visitor#visit_name()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_name($node) 
  {
    if ($node->symbol && 
        $node->symbol->kind === SYM_KIND_VAR &&
        $node->symbol->flags & SYM_FLAG_CONST && 
        $node->symbol->value &&
        $this->emit_value($node->symbol->value))
      return;
    
    $this->emit(name_to_str($node));  
  }
  
  /**
   * Visitor#visit_ident()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_ident($node) 
  {
    if ($node->symbol && 
        $node->symbol->kind === SYM_KIND_VAR &&
        $node->symbol->flags & SYM_FLAG_CONST && 
        $node->symbol->value &&
        $this->emit_value($node->symbol->value))
      return;
    
    $this->emit(ident_to_str($node));
  }
  
  /**
   * Visitor#visit_this_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_this_expr($node) 
  {
    $this->emit('this');  
  }
  
  /**
   * Visitor#visit_super_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_super_expr($node) 
  {
    $this->emit('super');  
  }
  
  /**
   * Visitor#visit_null_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_null_lit($node) 
  {
    $this->emit('null');  
  }
  
  /**
   * Visitor#visit_true_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_true_lit($node) 
  {
    $this->emit('true');  
  }
  
  /**
   * Visitor#visit_false_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_false_lit($node) 
  {
    $this->emit('false');  
  }
  
  /**
   * Visitor#visit_engine_const()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_engine_const($node) 
  {
    if ($this->emit_value($node->value))
      return;
    
    switch ($node->type) {
      case T_CFN:
        $this->emit('__fn__');
        break;
      case T_CCLASS:
        $this->emit('__class__');
        break;
      case T_CMETHOD:
        $this->emit('__method__');
        break;
      case T_CMODULE:
        $this->emit('__module__');
        break;
    }
  }
  
  /**
   * Visitor#visit_str_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_str_lit($node) 
  {
    if ($this->emit_value($node->value))
      return;
    
    $buf = $this->handle_str($node);
    
    if ($this->hstr) {
      $this->emit('\\');
      $this->emit(array_push($this->strs, $buf) -1); 
    } else
      $this->emit($buf);
  }
  
  /**
   * Visitor#visit_kstr_lit()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_kstr_lit($node)
  {
    if ($this->emit_value($node->value))
      return;
    
    $buf = 'c"' . $this->format_str($node->data, '"') . '"';
    
    if ($this->hstr) {
      $this->emit('\\');
      $this->emit(array_push($this->strs, $buf) - 1);
    } else
      $this->emit($buf);
  }
  
  /**
   * Visitor#visit_type_id()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_type_id($node) 
  {
    switch ($node->type) {
      case T_TINT:
        $this->emit('int');
        break;
      case T_TBOOL:
        $this->emit('bool');
        break;
      case T_TFLOAT:
        $this->emit('float');
        break;
      case T_TSTRING:
        $this->emit('string');
        break;
      case T_TREGEXP:
        $this->emit('regexp');
        break;
      default:
        assert(0);
    }
  }
}
