<?php

namespace phs;

use phs\ast\FnDecl;
use phs\ast\FnExpr;
use phs\ast\CtorDecl;
use phs\ast\DtorDecl;
use phs\ast\Name;
use phs\ast\Ident;
use phs\ast\TypeId;
use phs\ast\CallExpr;
use phs\ast\VarDecl;
use phs\ast\VarList;
use phs\ast\StrLit;
use phs\ast\NewExpr;
use phs\ast\AssignExpr;
use phs\ast\ObjLit;
use phs\ast\ObjKey;
use phs\ast\SelfExpr;
use phs\ast\SuperExpr;
use phs\ast\YieldExpr;
use phs\ast\RestArg;
use phs\ast\RestParam;
use phs\ast\ExprStmt;
use phs\ast\ParenExpr;

use phs\util\Set;

/** code generator */
class CodeGenerator extends AutoVisitor
{
  // @var Session
  private $sess;
  
  // @var resource  file-handle
  private $fhnd;
  
  // @var string
  private $buff;
  
  // @var int  tab-size
  private $tabs;
  
  // @var array
  private $mods;
  
  // @var int
  private $imod;
  
  // @var int
  private $nest;
  
  // @var bool  ignore values
  private $ignv;
  
  // @var bool  hoist strings
  private $hstr;
  
  // @var array  hoisted strings
  private $strs;
  
  // @var boolean  in call expression
  private $call;
  
  // @var boolean  in member expression
  private $member;
  
  // @var int  in-loop
  private $loop;
  
  // @var Set  in-loop locals
  private $lloc;
  
  // @var int  used to generate temporary variables
  private static $temp = 0;
  
  /**
   * constructor
   *
   * @param Session $sess
   */
  public function __construct(Session $sess)
  {
    parent::__construct();
    $this->sess = $sess;
  }
  
  /**
   * process a source
   *
   * @param  Source $src
   */
  public function process(Source $src)
  {
    if ($src->php)
      file_put_contents($src->get_temp(), $src->get_data());
    else {
      $this->buff = '';
      $this->tabs = 0;
      $this->nest = 0;
      $this->call = false;
      $this->hstr = false;
      $this->strs = [];
      $this->ignv = false;
      $this->member = false;
                  
      // module-generation specific
      $this->mods = [];
      $this->imod = 0;
      
      $this->emitln('<?php');
      $this->emitln('/**');
      $this->emitln(' * This is an automatically GENERATED file!');
      $this->emitln(' * Generator: PHS ', VERSION);
      $this->emitln(' * Timestamp: ', time());
      $this->emitln(' */');
      
      $this->visit($src->unit);
      $this->flush($src->get_temp());
    }   
  }
  
  /**
   * flushes the buffer to the given file-path
   *
   */
  private function flush($path)
  {
    static $ns_re = '/^namespace\s+(?:\w+(?:[\\\\]\w+)*\s+)?\{[ \n]*?\}(?:[ ]*\n)?/m';
    static $hs_re = '';
    
    $buff = $this->buff;
    $this->buff = '';
    
    // remove empty namespace-declarations
    $buff = preg_replace($ns_re, '', $buff);
    
    // more?
    
    // insert strings
    
    
    // write to output
    $fp = fopen($path, 'w+');
    assert($fp !== null); 
    fwrite($fp, $buff);
    fclose($fp);
  }
  
  /* ------------------------------------ */
  
  /**
   * emit data to the buffer
   *
   */
  public function emit()
  {
    for ($i = 0, $l = func_num_args(); $i < $l; ++$i)
      $this->buff .= func_get_arg($i);
  }
  
  /**
   * emit data + a new line to the buffer
   *
   */
  public function emitln()
  {
    for ($i = 0, $l = func_num_args(); $i < $l; ++$i)
      $this->buff .= func_get_arg($i);
    
    $this->buff .= "\n";
    $this->buff .= str_repeat('  ', $this->tabs);
  }
  
  /**
   * emits a list of values as quoted strings
   *
   */
  public function emitqs()
  {
    for ($i = 0, $l = func_num_args(); $i < $l; ++$i)
      $this->buff .= $this->quote(func_get_arg($i));
  }
  
  /**
   * quotes a string-value
   *
   * @param  mixed $val
   * @return string
   */
  public function quote($val)
  {
    return '"' . $this->format_str($val) . '"';
  }
  
  /**
   * increment tab-count
   *
   */
  public function indent()
  {
    $this->buff = rtrim($this->buff, "\n ");
    $this->tabs++;
    $this->emitln();
  }
  
  /**
   * decrement tab-count
   * 
   */
  public function dedent()
  {
    if ($this->tabs - 1 >= 0) {
      $this->buff = rtrim($this->buff, "\n ");
      $this->tabs--;
      $this->emitln();
    }
  }
  
  /* ------------------------------------ */
  
  /**
   * collects chained callees e.g.: foo()()
   *
   * @param  Node $node
   * @return array
   */
  public function collect_callees($node)
  {
    $list = [];
    $call = $node;
    
    for (;;) {
      $list[] = $call;
      
      if ($call->callee instanceof CallExpr)
        $call = $call->callee;
      else
        break;
    }
    
    return $list;
  }
  
  /**
   * formats a value to a soon-to-be-quoted string
   *
   * @param  string $val
   * @param  string $dlm
   * @return string
   */
  public function format_str($val, $dlm = '"')
  {
    $esc = false;
    $str = '';
    
    for ($i = 0, $l = strlen($val); $i < $l; ++$i) {
      $cur = $val[$i];
      
      if ($cur === '\\')
        $esc = !$esc;
      elseif ($cur === '"') {
        if ($esc) 
          $esc = false;
        else
          $str .= '\\';
      } else {
        switch ($cur) {
          case "\n": $cur = '\\n'; break; 
          case "\r": $cur = '\\r'; break;          
          case "\t": $cur = '\\t'; break;         
          case "\f": $cur = '\\f'; break;          
          case "\v": $cur = '\\v'; break;          
          case "\e": $cur = '\\e'; break;
          case '\\': $cur = '\\\\'; break;
          case $dlm: $cur = "\\$dlm"; break;
        }
      }
      
      $str .= $cur;
    }
    
    return $str;
  }
  
  /* ------------------------------------ */
  
  /**
   * emits a operator
   *
   * @param  Token  $op 
   * @param  boolean $bin
   */
  public function emit_op($op, $bin = false)
  {
    $val = $op->value;
    
    switch ($op->type) {
      case T_ACONCAT:
        $val = '.=';
        break;
      case T_CONCAT:
        if ($bin === true)
          $val = '.';
        break;
      case T_EQ:
        $val = '===';
        break;
      case T_NEQ:
        $val = '!==';
        break;
    }
    
    $this->emit($val);
  }
  
  /**
   * emits a string literal
   *
   * @param  StrLit $node
   * @param  bool   $cat   concatenate strings if necessary
   * @param  bool   $fmt   format string and wrap in quotes
   */
  public function emit_str($node, $cat = true, $fmt = true)
  {
    assert($node instanceof StrLit);
    
    $idx = 1;
    
    if ($node->data === '' && $node->parts)
      // first slice is empty: skip it
      $idx = 0;
    else {
      // emit first slice
      if ($fmt === true)
        $this->emit_str_fmt($node->data);
      else
        $this->emit($node->data);
    }
    
    if ($node->parts)
      foreach ($node->parts as $part) {
        if ($idx > 0) 
          if ($cat === true)
            $this->emit(' . ');
          else
            $this->emit(', ');
        
        if ($part instanceof StrLit) {
          if ($fmt === true)
            $this->emit_str_fmt($part->data);
          else
            $this->emit($part->data);
        } else {
          if ($cat === true) {
            $this->emit('(');
            $this->visit($part);
            $this->emit(')');
          } else
            $this->visit($part);
        }
        
        $idx++;
      }
  }
  
  /**
   * emits a formated string (with quotes and stuff)
   *
   * @param  string $val
   * @param  bool   $qt   use quotes
   */
  public function emit_str_fmt($val)
  {
    // scan string to decide which delimiter must be used
    $dlm = "'";
    $len = strlen($val);
    
    if (strpos($val, "\n") !== false ||
        strpos($val, "\r") !== false ||
        strpos($val, "\t") !== false ||
        strpos($val, "\f") !== false ||
        strpos($val, "\v") !== false ||
        strpos($val, "\e") !== false)
      $dlm = '"';
    
    $str = '';
    for ($idx = 0; $idx < $len; ++$idx) {
      $cur = $val[$idx];
      
      switch ($cur) {
        case "\n": $cur = '\\n'; break;
        case "\r": $cur = '\\r'; break;
        case "\t": $cur = '\\t'; break;
        case "\f": $cur = '\\f'; break;
        case "\v": $cur = '\\v'; break;
        case "\e": $cur = '\\e'; break;
        case '\\': $cur = '\\\\'; break;
        case $dlm:
          $cur = '\\';
          $cur .= $dlm;
          break;
      }
      
      $str .= $cur;
    }
    
    // finally
    $this->emit($dlm, $str, $dlm);
  }
  
  /**
   * emits a reference to a symbol
   *
   * @param  Symbol $sym
   */
  public function emit_sym_ref(Symbol $sym)
  {
    $memb = false;
    $mems = null;
    $host = '';
    
    if (($sym->scope instanceof MemberScope) ||
        ($sym->scope instanceof InnerScope && 
         $sym->scope->outer instanceof MemberScope)) {
      // reference to a member without "this." or "Class."
      $memb = true;
      $mems = $sym->scope instanceof InnerScope ?
        $sym->scope->outer : $sym->scope;
      $host = path_to_abs_ns($mems->host->path());
    }
    
    if (($sym instanceof VarSymbol) ||
        ($sym instanceof FnSymbol && ($sym->expr || $sym->nested) && 
         !($sym->flags & SYM_FLAG_EXTERN))) {
              
      if ($memb === true) {
        if ($sym->flags & SYM_FLAG_STATIC)
          $this->emit($host, '::$');
        else
          $this->emit('$this->');
      } elseif (!($sym->flags & SYM_FLAG_EXTERN &&
                  $sym->flags & SYM_FLAG_CONST))
        $this->emit('$');
      
      $this->emit($sym->id);
    } else {
      if ($memb === true) {
        if ($sym->flags & SYM_FLAG_STATIC) {
          if ($this->call)
            $this->emit('static::', $sym->id);
          else
            // static:: would be possible, but does only work within the 
            // class and can not be returned...
            $this->emit('\'', $host, '::', $sym->id, '\'');
        } else
          $this->emit('$this->', $sym->id);
      } else {
        if ($sym->flags & SYM_FLAG_NATIVE)
          // use raw id
          $this->emit($sym->rid);
        else {
          // quote paths if they're accessed in read-mode
          $quote = '';
          if ($sym instanceof IfaceSymbol ||
              $sym instanceof TraitSymbol ||
              (($sym instanceof FnSymbol || 
                $sym instanceof ClassSymbol) && 
               !$this->call && !$this->member))
            $quote = "'";
                  
          $this->emit($quote, 
            path_to_abs_ns($sym->path()), $quote);
        }
      }
    }
  }
  
  /**
   * emits a top-level assign expression
   *
   * @param  Node $expr
   * @param  string $dest
   */
  public function emit_top_assign($expr, $dest)
  {
    if ($expr instanceof CallExpr) {
      $this->visit_top_call_expr($expr, $dest);
      $this->emitln(';');
    } else {
      $temp = $this->hoist_dict_expr($expr, $dest);
            
      if ($temp === null) {
        $this->emit('$', $dest, ' = ');
        $this->visit($expr);
        $this->emitln(';');
      }
    }
  }
  
  /* ------------------------------------ */
  
  /**
   * emits a value
   *
   * @param  Value $value
   * @return boolean
   */
  public function emit_value(Value $value = null)
  {
    if ($this->ignv || !$value || $value->is_unkn()) 
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
  public function emit_str_value($val)
  {
    $buf = '"' . $this->format_str($val) . '"';
    
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
    $this->emit('new \\List_(');
    
    if (!empty ($list))    
      foreach ($list as $idx => $item) {
        if ($idx > 0) $this->emit(', ');
        $this->emit_value($item);
      }
    
    $this->emit(')');
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
      $this->emit('[]');
      return;
    }
    
    $this->emit('[ ');
    
    foreach ($list as $idx => $item) {
      if ($idx > 0) $this->emit(', ');
      $this->emit_value($item);
    }
    
    $this->emit(' ]');
  }
  
  /**
   * emits a list value
   *
   * @param  array $list
   * @return void
   */
  private function emit_dict_value($dict)
  {
    $this->emit('new \\Dict(');
    
    if (!empty ($dict)) {    
      $this->emitln('[');
      $this->indent();
      
      $idx = 0;
      foreach ($dict as $key => $val) {
        if ($idx++ > 0)
          $this->emitln(',');
        
        // PHP converts number-like keys to integers
        // ... and you can't do anything against it
        
        $this->emit_str_value((string)$key);
        $this->emit(' => ');
        $this->emit_value($val);
      }
      
      $this->dedent();
      $this->emit(']');
    }
    
    $this->emit(')');
  }
  
  /* ------------------------------------ */
  
  /**
   * emits the start (header) of a namespace
   *
   * @param  ModuleScope $mod
   */
  public function emit_ns_beg(ModuleScope $mod = null)
  {
    $this->emit('namespace ');
    
    if ($mod !== null && $mod->id !== null) {
      $this->emit(path_to_ns($mod->path()));
      $this->emit(' ');
    }
    
    $this->emitln('{');
    $this->indent();
  }
  
  /**
   * emits the end (footer) of a namespace
   *
   */
  public function emit_ns_end()
  {
    $this->dedent();
    $this->emitln('}');
  }
  
  /* ------------------------------------ */
  
  /**
   * emits member modifiers
   *
   * @param  Symbol $sym
   * @param  bool   $bind
   */
  public function emit_member_mods(Symbol $sym, $bind = false)
  {
    if ($sym->flags & SYM_FLAG_PUBLIC)
      $this->emit('public ');
    elseif ($sym->flags & SYM_FLAG_PRIVATE)
      $this->emit('private ');
    elseif ($sym->flags & SYM_FLAG_PROTECTED)
      $this->emit('protected ');
    
    if ($sym->flags & SYM_FLAG_STATIC)
      $this->emit('static ');
    
    if (!$bind && $sym instanceof FnSymbol) { 
      if ($sym->flags & SYM_FLAG_ABSTRACT)
        $this->emit('abstract ');
      elseif ($sym->flags & SYM_FLAG_FINAL)
        $this->emit('final ');
    }
  }
  
  /**
   * emits a function-member (function declaration)
   * 
   * @param  FnSymbol  $fsym
   */
  public function emit_fn_member(FnSymbol $fsym, $iface = false)
  {
    $this->emit_member_mods($fsym, $iface);
    
    $this->emit('function ', $fsym->id);
    $this->emit_fn_params($fsym->node);
    
    if (!($fsym->flags & SYM_FLAG_ABSTRACT)) {
      if ($fsym->ctor)
        $this->emitln('{}');
      else
        $this->emit_fn_body($fsym->node);
    } else
      $this->emitln(';');
  }
  
  /**
   * emits a function-member binding
   * 
   * @param  ClassSymbol  $csym
   * @param  FnSymbol  $fsym
   */
  public function emit_fn_cmbind(ClassSymbol $csym, FnSymbol $fsym)
  {
    if ($fsym->flags & SYM_FLAG_ABSTRACT)
      return;
    
    $this->emit_member_mods($fsym, true);
    $this->emit('$', $fsym->id);
    
    if ($fsym->flags & SYM_FLAG_STATIC) {
      $this->emit(' = [ \'\\', path_to_ns($csym->path()));
      $this->emit('\', \'', $fsym->id, '\' ]');  
    }
    
    $this->emitln(';');
  }
  
  /**
   * emits a variable member
   *
   * @param  VarSymbol $vsym
   */
  public function emit_var_member(VarSymbol $vsym)
  {
    $this->emit_member_mods($vsym);
    
    $this->emit('$', $vsym->id);
    
    // init with primitive value
    if ($vsym->value && $vsym->value->is_primitive()) {
      $this->emit(' = ');
      $this->emit_value($vsym->value);
    }
    
    // non-primitive values are initialized in the constructor
    
    $this->emitln(';');
  }
  
  /* ------------------------------------ */
  
  /**
   * emits a constructor declaration
   *
   * @param  ClassSymbol $csym
   * @param  FnSymbol $ctor 
   * @param  array    $vars
   * @param  array    $funs
   */
  public function emit_ctor_decl(ClassSymbol $csym, FnSymbol $ctor = null, 
                                 array $vars, array $funs)
  {
    // constructor cannot be abstract
    if ($ctor && $ctor->flags & SYM_FLAG_ABSTRACT)
      $ctor->flags ^= SYM_FLAG_ABSTRACT;
    
    if ($ctor === null)
      // default constructor is public by default
      $this->emit('public function __construct() '); 
    else {
      $this->emit_member_mods($ctor);
      $this->emit('function __construct');
      $this->emit_fn_params($ctor->node, false);
    }
    
    $this->emitln('{');
    $this->indent();
    
    // emit property assigns
    foreach ($vars as $var) {
      if ($var->init && !$var->value->is_primitive()) {
        $init = $var->init;
        $temp = '$this->' . $var->id;
        
        if ($init instanceof CallExpr)
          $this->visit_top_call_expr($init, $temp);
        elseif ($init instanceof ObjLit)
          $this->hoist_dict_expr($init, $temp);
        else {
          $this->emit($temp, ' = ');
          $this->visit($init);
        }
        
        $this->emitln(';');
      }
    }
    
    if ($ctor !== null) {
      $this->emit_fn_checks($ctor->node);
      $tprm = [];
      
      foreach ($ctor->params as $param)
        if ($param->that)
          $tprm[$param->rid] = $param;
      
      // 1. initialize members
      foreach ($vars as $var) {
        // ignore var if a this-param will override it
        if (isset ($tprm[$var->rid]))
          continue;
        
        if (!$var->value || !$var->node->init ||
            $var->value->is_none() ||
            $var->value->is_primitive())
          continue; // no value or already emitted
        
        $init = $var->node->init;
        $temp = $this->hoist_dict_expr($init);
        
        $this->emit('$this->', $var->id, ' = ');
        
        if ($temp === null)
          $this->visit($init);
        else
          $this->emit($temp);
        
        $this->emitln(';');
      }
      
      // 2. setup this-params
      foreach ($tprm as $param) {
        $this->emit('$this->', $param->rid, ' = ');
        // use a reference to the parameter if the parameter 
        // itself is not already a reference
        if (!$param->ref) $this->emit('&');
        $this->emitln('$', $param->id, ';');
      }
    }
    
    // 3. bind methods
    $super = $csym->members->super;
    
    foreach ($funs as $fun) {
      if (($fun->flags & SYM_FLAG_STATIC) ||
          ($fun->flags & SYM_FLAG_HIDDEN) ||
          $super && $super->has($fun->id))
        continue;
      
      $this->emit('$this->', $fun->id, ' = [ $this, ');
      $this->emitln("'", $fun->id, "'", ' ];');
    }
    
    // no super-class or super-class is the root-object
    if (!$super || $super->host === $this->sess->robj)
      $this->emitln('$this->hash = [ $this, \'hash\' ];');
    
    // 4. call super if needed
    $esup = false;
    
    if ($ctor !== null && $ctor->node->body)
      foreach ($ctor->node->body->body as $stmt) {
        if ($stmt instanceof ExprStmt) {
          foreach ($stmt->expr as $expr) {
            if ($expr instanceof CallExpr &&
                $expr->callee instanceof SuperExpr)
              $esup = true;
            
            // super() must be the very first expression
            break;
          }
        }  
        
        // super() must be the very first expression
        break;
      }
    
    if ($esup === false && $super && $super->ctor)
      $this->emitln('parent::__construct();');
    
    // 5. emit actual body
    if ($ctor !== null && $ctor->node->body)
      $this->emit_fn_body($ctor->node, false, false, false, false, false);
    
    $this->dedent();
    $this->emitln('}'); 
  }
  
  /**
   * emits a destructor
   *
   * @param  ClassSymbol $csym
   * @param  FnSymbol    $dtor
   */
  public function emit_dtor_decl(ClassSymbol $csym, FnSymbol $dtor = null)
  {
    if ($dtor === null)
      return;
    
    if ($dtor && $dtor->flags & SYM_FLAG_ABSTRACT)
      $dtor->flags ^= SYM_FLAG_ABSTRACT;
    
    $this->emit_member_mods($dtor);
    $this->emit('function __destruct() ');
    // ignore params, the gc does not pass any
    $this->emit_fn_body($dtor->node);
  }
  
  /**
   * emits trait-usage
   *
   * @param  array<TraitUsageMap> $traits
   */
  public function emit_used_traits(array $traits)
  {
    // note: traits are currently empty-declarations
    // class_uses() will work as expected, but
    // all methods from traits are copied ahead-of-time
    
    foreach ($traits as $trait) {
      $this->emit('use \\');
      $this->emit(path_to_ns($trait->trait->symbol->path()));
      $this->emitln(';');
    }
  }
  
  /* ------------------------------------ */
  
  /**
   * emits a function-declaration
   *
   * @param  Node $node
   */
  public function emit_fn_decl($node)
  {
    $fsym = $node->symbol;
    
    if (($fsym->flags & SYM_FLAG_EXTERN) ||
        ($fsym->flags & SYM_FLAG_NATIVE))
      return;
        
    $this->emit('function ', $fsym->id);
    $this->emit_fn_params($node);
    $this->emit_fn_body($node);
  }
  
  /**
   * emits a function-expression
   *
   * @param  Node $node
   * @param  boolean  $decl  the function is actually a declaration
   */
  public function emit_fn_expr($node, $decl = false)
  {
    $fsym = $node->symbol;
    
    if (substr($fsym->id, 0, 1) !== '~')
      $this->emit('$', $fsym->id, ' = ');
    
    $this->emit('function');
    $this->emit_fn_params($node);
    $this->emit_fn_body($node, true, $decl);
    
    if ($decl === false)
      $this->buff = rtrim($this->buff, "\n ");
  }
  
  /**
   * emits function-params
   *
   * @param  Node $node
   */
  public function emit_fn_params($node)
  {
    static $defs = [
      T_TINT => '0',
      T_TBOOL => 'false',
      T_TFLOAT => '0.',
      T_TTUP => '[]',
      T_TDEC => '0',
      T_TSTR => "''"
    ];
    
    $fsym = $node->symbol;
    
    $this->emit('(');
          
    foreach ($fsym->params as $idx => $psym) {
      if ($idx > 0) $this->emit(', ');
      
      if ($psym->hint) {
        if (!($psym->hint instanceof TypeId)) {
          $hint = $psym->hint->symbol;
          
          if ($hint->flags & SYM_FLAG_NATIVE)
            // use raw id
            $path = $hint->rid;
          else
            $path = path_to_abs_ns($hint->path());
                  
          $this->emit($path);
          $this->emit(' ');
        } elseif ($psym->hint->type === T_TTUP)
          // <array> is a valid hint in php
          $this->emit('array ');
        elseif ($psym->hint->type === T_TCALLABLE)
          $this->emit('callable ');
      }
      
      if ($psym->ref) $this->emit('&');
      if ($psym->rest) $this->emit('...');
      
      $this->emit('$', $psym->id);   
      
      if (!$psym->rest && ($psym->opt || $psym->init)) {
        $defv = 'null';
        
        if (!$psym->init && /* use null if a initializer is set */
            $psym->hint && $psym->hint instanceof TypeId &&
            $psym->hint->type !== T_TCALLABLE)
          $defv = $defs[$psym->hint->type];
        
        $this->emit(' = ', $defv);
      }
    }
      
    $this->emit(') ');
  }
  
  /**
   * emits function global-captures
   *
   * @param  Node $node
   */
  public function emit_fn_globals($node)
  {
    foreach ($node->scope->capt as $sym)
      if (($sym instanceof VarSymbol ||
           $sym instanceof FnSymbol && $sym->nested) &&
          $sym->scope->is_global() &&
          !($sym->flags & (SYM_FLAG_EXTERN | SYM_FLAG_CONST)))
        $this->emitln('global $', $sym->id, ';');
  }
  
  /**
   * emits function captures
   *
   * @param  Node $node
   */
  public function emit_fn_captures($node)
  {
    $fsym = $node->symbol;
    $capt = [];
    
    if (($node instanceof FnExpr && 
         substr($fsym->id, 0, 1) !== '~') || 
        ($node instanceof FnDecl && $node->nested))
      // capture self
      $capt[] = $fsym;
    
    foreach ($node->scope->capt as $csym)
      if (!$csym->scope->is_global())
        $capt[] = $csym;
    
    if (empty ($capt))
      return;
    
    $this->emit('use (');
      
    foreach ($capt as $idx => $csym) {
      if ($idx > 0) $this->emit(', ');
      $this->emit('&$', $csym->id);
    }
      
    $this->emit(') ');
  }
  
  /**
   * emits additional parameter type-checks
   *
   * @param  Node $node
   */
  public function emit_fn_checks($node)
  {
    static $ttos = [
      T_TINT => 'int',
      T_TBOOL => 'bool',
      T_TFLOAT => 'float',
      T_TDEC => 'numeric',
      T_TSTR => 'string',
      T_TOBJ => 'object',
      T_TCALLABLE => 'callable'
    ];
    
    $fsym = $node->symbol;
    
    foreach ($fsym->params as $idx => $psym) {
      $id = $psym->id;
      
      if ($psym->init) {
        $this->emitln('if ($', $id, ' === null) {');
        $this->indent();
        
        if ($psym->init instanceof CallExpr) {
          $this->visit_top_call_expr($psym->init, $id);
          $this->emitln(';');
        } else {
          $temp = $this->hoist_dict_expr($psym->init, $id);
          
          if ($temp === null) {
            $this->emit('$', $id, ' = ');
            $this->visit($psym->init);
            $this->emitln(';');
          }
        }
        
        $this->dedent();
        $this->emitln('}');
      }
      
      if ($psym->hint && 
          $psym->hint instanceof TypeId &&
          $psym->hint->type !== T_TTUP &&
          $psym->hint->type !== T_TCALLABLE) {
        $nr = '';
        $fn = $ttos[$psym->hint->type];
        
        if ($psym->rest) {
          $nr = '_T' . (self::$temp++);
          $id = '_T' . (self::$temp++);
          $this->emit('foreach ($', $psym->id, ' as $', $nr);
          $this->emitln(' => $', $id, ')');
          $this->indent();
        }
        
        if ($psym->ref) {
          $this->emit('if (');
            
          if ($psym->opt)
            $this->emit('$', $id, ' !== null && ');
          
          $this->emitln('!is_', $fn, '($', $id, ')) {');
          $this->indent();
          $this->emit('throw new \\InvalidArgumentException(', "'");
          $this->emit($fsym->rid, '(): parameter #', $idx + 1, ' ');
          $this->emit('(`', $psym->rid, '`) - expected argument');
          
          // well, now we have argumentS
          if ($psym->rest) $this->emit('s');
          
          $this->emit(' of type ', $fn);
          
          // mark the argument as tuple
          if ($psym->rest) 
            $this->emit(' [...]');
          
          $this->emit(", ' . gettype($", $id, ") . ' given");
          
          // show offset of invalid tuple-value
          if ($psym->rest) 
            $this->emit(" at position ' . ($", $nr, ' + ', $idx + 1, ')');
          else
            $this->emit("'");
          
          $this->emitln(');');
          $this->dedent();
          $this->emitln('}');
        } else {
          $this->emit('$', $id, ' = ');
          
          if ($psym->hint->type === T_TDEC)
            $this->emit('+');
          else   
            $this->emit('(', $fn, ')');
          
          $this->emitln('$', $id, ';');
        }
        
        if ($psym->rest) {
          self::$temp -= 2;
          $this->dedent();
        } 
      }
    }
  }
  
  /**
   * emits a function-body
   *
   * @param  Node $node
   * @param  boolean $expr  this body is part of a function-expression
   * @param  boolean $term  the function needs a terminator (semicolon)
   * @param  boolean $encl  enclose the body with { } 
   * @param  boolean $chks  emit parameter checks
   */
  public function emit_fn_body($node, $expr = false, 
                                      $term = false, 
                                      $encl = true,
                                      $chks = true)
  {
    if ($expr === true)
      $this->emit_fn_captures($node);
    
    if ($encl === true) {
      $this->emitln('{');
      $this->indent();
    }
    
    $this->emit_fn_globals($node);
    
    if ($chks)
      $this->emit_fn_checks($node);
    
    // visit block-body directly
    $this->nest++;    
    $this->visit($node->body->body);
    $this->nest--;
    
    if ($encl === true) {
      $this->dedent();
      $this->emit('}');
      
      if ($term === true)
        $this->emit(';');
    }
    
    $this->emitln();
  }
  
  /* ------------------------------------ */
  
  /**
   * hoist dict expressions
   *
   * @param  Node $node
   * @param  string $temp
   * @return string
   */
  public function hoist_dict_expr($node, $temp = null)
  {
    if (!($node instanceof ObjLit))
      return null;
    
    $decr = false;
    
    if ($temp === null) {
      $temp = '_T' . (self::$temp++);
      $decr = true;
    }
    
    $this->emitln('$', $temp, ' = new \\Dict;');
    
    foreach ($node->pairs as $pair) {
      $hdct = null;
      
      if ($pair->arg instanceof ObjLit)
        // hoist value first
        $hdct = $this->hoist_dict_expr($pair->arg);
      elseif ($pair->arg instanceof CallExpr) {
        // hoist call
        $ctmp = '_T' . (self::$temp++);
        $this->visit_top_call_expr($pair->arg, $ctmp);
        $this->emitln(';');
        self::$temp--;
        $hdct = '$' . $ctmp;
      }
      
      $this->emit('$', $temp, '->');
      
      if ($pair->key instanceof ObjKey) {
        // computed
        $this->emit('{');
        $this->visit($pair->key->expr);
        $this->emit('}');
      } else
        $this->emit(ident_to_str($pair->key));
        
      $this->emit(' = ');
      
      if ($hdct !== null)
        $this->emit($hdct);
      else
        $this->visit($pair->arg);
      
      $this->emitln(';');
      
      if ($hdct !== null)
        $this->emitln('unset (', $hdct, ');');
    }
    
    if ($decr === true)
      self::$temp--;
    
    return '$' . $temp;
  }
  
  /**
   * hoist dict arguments
   *
   * @param  array $node
   * @return array  
   */
  public function hoist_dict_args($node)
  {
    if (!$node) return $node;
    
    $args = [];
    $tmpd = 0;
    
    foreach ($node as $arg) {
      // hoist obj-literal
      if ($arg instanceof ObjLit) {
        $arg = $this->hoist_dict_expr($arg);
        self::$temp++; // keep `temp` incremented for now
        $tmpd++;
        
      // hoist call
      } elseif ($arg instanceof CallExpr) {
        $temp = '_T' . (self::$temp++);
        $this->visit_top_call_expr($arg, $temp);
        $this->emitln(';');
        $arg = '$' . $temp;
        $tmpd++;
      }
      
      // else
      // don't touch $arg
      
      $args[] = $arg;
    }
    
    self::$temp -= $tmpd;
    return $args;
  }
  
  /* ------------------------------------ */
  
  /**
   * Visitor#visit_fn_args()
   *
   * @param  array $args
   */
  public function visit_fn_args($args) 
  {
    $this->emit('(');
    
    if (!empty($args)) {
      $len = count($args);
      foreach ($args as $idx => $arg) {
        if ($idx > 0) $this->emit(', ');
        
        // due to "hoist_dict_args()"
        if (is_string($arg))
          $this->emit($arg);
        
        elseif ($arg instanceof RestArg) {
          $this->emit('...');
          $this->visit($arg->expr);
        } 
        
        else
          $this->visit($arg);
      }
    }
    
    $this->emit(')');
  }
  
  /**
   * Visitor#visit_fn_params()
   *
   * @param  array $params
   */
  public function visit_fn_params($params) 
  {
    // handled by emit_fn_params()
  }
  
  /* ------------------------------------ */
  
  /**
   * Visitor#visit_unit()
   *
   * @param  Node  $node
   */
  public function visit_unit($node) 
  {
    $this->emit_ns_beg();
    $this->visit($node->body);
    $this->emit_ns_end();
  }
  
  /**
   * Visitor#visit_module()
   *
   * @param  Node  $node
   */
  public function visit_module($node) 
  {
    $pmod = null;
    
    // close previous module
    if ($this->imod > 0)
      $pmod = array_pop($this->mods);
    
    $this->emit_ns_end();
    
    $this->imod++;
    
    $cmod = $node->scope;
    array_push($this->mods, $cmod);
    
    $this->emit_ns_beg($cmod);
    $this->visit($node->body);
    $this->emit_ns_end();
    
    $this->imod--;
    
    // re-open previous module
    if ($pmod) {
      array_push($this->mods, $pmod);
      $this->emit_ns_beg($pmod);
    } else
      $this->emit_ns_beg();
  }
  
  /**
   * Visitor#visit_content()
   *
   * @param  Node  $node
   */
  public function visit_content($node) 
  {
    $this->visit($node->body);  
  } 
  
  /**
   * Visitor#visit_block()
   *
   * @param  Node  $node
   */
  public function visit_block($node) 
  {
    $this->nest++;
    
    $this->emitln('{');
    $this->indent();
    $this->visit($node->body);
    $this->dedent();
    $this->emitln('}');
    
    // unset vars  
    $scope = $node->scope;
    if ($scope->has_locals()) {
      $this->emit('unset (');
      $locs = [];
      
      foreach ($scope->iter() as $sym)
        if (!($sym->flags & SYM_FLAG_EXTERN))
          $locs[] = $sym;
      
      foreach ($locs as $idx => $sym) {
        if ($idx > 0) $this->emit(', ');
        $this->emit('$', $sym->id);
      }
      
      $this->emit(')');
      $this->emitln(';');
    }
    
    $this->nest--;
  }
  
  /**
   * Visitor#visit_enum_decl()
   *
   * @param  Node  $node
   */
  public function visit_enum_decl($node) 
  {
    // TODO: implement enums
  }
  
  /**
   * Visitor#visit_class_decl()
   *
   * @param  Node  $node
   */
  public function visit_class_decl($node) 
  {
    $csym = $node->symbol;
    
    if ($csym->flags & SYM_FLAG_EXTERN)
      return;
    
    if ($csym->flags & SYM_FLAG_ABSTRACT)
      $this->emit('abstract ');
    elseif ($csym->flags & SYM_FLAG_FINAL)
      $this->emit('final ');
    
    $this->emit('class ', $csym->id);
    
    if ($csym->members->super) {
      $this->emit(' extends \\');
      $this->emit(path_to_ns($csym->members->super->host->path()));
    }
    
    if ($csym->ifaces) {
      $this->emit(' implements ');
      foreach ($csym->ifaces as $idx => $iface) {
        if ($idx > 0) $this->emit(', ');
        $this->emit('\\', path_to_ns($iface->symbol->path()));  
      }
    }
    
    $this->emitln(' {');
    $this->indent();
    
    if ($csym->traits) {
      $this->emit_used_traits($csym->traits);      
      $this->emitln();
    }
    
    $vars = [];
    $funs = [];
    
    foreach ($csym->members->iter() as $msym)
      if ($msym instanceof VarSymbol)
        $vars[] = $msym;
      elseif ($msym instanceof FnSymbol &&
              !$msym->ctor && !$msym->dtor &&
              !$msym->getter && !$msym->setter)
        $funs[] = $msym;
    
    $vout = 0;
    
    // 1. emit properties
    foreach ($vars as $var) {
      $this->emit_var_member($var);
      $vout++;
    }
    
    // 2. emit method-bindings
    foreach ($funs as $fun)
      if (!($fun->flags & SYM_FLAG_STATIC) &&
          !($fun->flags & SYM_FLAG_HIDDEN)) {
        $this->emit_fn_cmbind($csym, $fun);
        $vout++;
      }
      
    $super = $csym->members->super;
    
    if (!$super || $super->host === $this->sess->robj) {
      $this->emitln('public $hash;');
      $vout++;
    }
    
    if ($vout > 0)
      $this->emitln();
    
    // 3. emit constructor
    $this->emit_ctor_decl($csym, $csym->members->ctor, $vars, $funs);
    
    // 4. emit destructor
    $this->emit_dtor_decl($csym, $csym->members->dtor);
    
    // 5. emit getter
    // TODO
    
    // 6. emit setter
    // TODO
    
    // 7. emit methods
    foreach ($funs as $fun)
      $this->emit_fn_member($fun);
    
    $this->dedent();
    $this->emitln('}');
  }
  
  /**
   * Visitor#visit_nested_mods()
   *
   * @param  Node  $node
   */
  public function visit_nested_mods($node) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_ctor_decl()
   *
   * @param  Node  $node
   */
  public function visit_ctor_decl($node) 
  {
    // noop
  }

  /**
   * Visitor#visit_dtor_decl()
   *
   * @param  Node  $node
   */
  public function visit_dtor_decl($node) 
  {
    // noop
  }

  /**
   * Visitor#visit_getter_decl()
   *
   * @param  Node  $node
   */
  public function visit_getter_decl($node) 
  {
    // noop
  }

  /**
   * Visitor#visit_setter_decl()
   *
   * @param  Node  $node
   */
  public function visit_setter_decl($node) 
  {
    // noop
  }

  /**
   * Visitor#visit_trait_decl()
   *
   * @param  Node  $node
   */
  public function visit_trait_decl($node) 
  {
    $tsym = $node->symbol;
    
    if ($tsym->flags & SYM_FLAG_EXTERN)
      return;
    
    $this->emitln('trait ', $tsym->id, ' {');
    $this->indent();
    
    if ($tsym->traits) {
      $this->emit_used_traits($tsym->traits);
      $this->emitln();
    }
    
    // TODO:
    // trait-functions don't have own scopes,
    // therefore emit_xyz_member does not work.
    // this needs to be fixed soon, but 
    // it's not super relevant to have traits
    // compiled to php, because "use xyz" is completely
    // done in the resolver-pass.
    
    /*
    foreach ($tsym->members->iter() as $msym) {
      if ($msym->origin !== null)
        continue; // comes from another trait
        
      if ($msym instanceof FnSymbol)
        $this->emit_fn_member($msym);
      else
        $this->emit_var_member($msym);
    } 
    */
   
    $this->dedent();
    $this->emitln('}');
  }

  /**
   * Visitor#visit_iface_decl()
   *
   * @param  Node  $node
   */
  public function visit_iface_decl($node) 
  {
    $isym = $node->symbol;
    
    if ($isym->flags & SYM_FLAG_EXTERN)
      return;
     
    $this->emit('interface ', $isym->id);
    
    if ($isym->ifaces) {
      $this->emit(' extends ');
      foreach ($isym->ifaces as $idx => $iface) {
        if ($idx > 0) $this->emit(', ');
        $this->emit('\\', path_to_ns($iface->symbol->path()));  
      }
    }
    
    $this->emitln(' {');
    $this->indent();
    
    foreach ($isym->members->iter() as $fsym) {
      assert($fsym instanceof FnSymbol);
      $this->emit_fn_member($fsym, true);
    }
    
    $this->dedent();
    $this->emitln('}');
  }
    

  /**
   * Visitor#visit_fn_decl()
   *
   * @param  Node  $node
   */
  public function visit_fn_decl($node) 
  {
    $fsym = $node->symbol;
    
    if ($fsym->flags & SYM_FLAG_EXTERN)
      return;
    
    if ($node->nested)
      $this->emit_fn_expr($node, true);
    else
      $this->emit_fn_decl($node);
  }

  /**
   * Visitor#visit_var_decl()
   *
   * @param  Node  $node
   */
  public function visit_var_decl($node) 
  {
    foreach ($node->vars as $var) {
      $sym = $var->symbol;
      
      if (!$var->init && !($sym->flags & SYM_FLAG_STATIC)) 
        // no declaration needed
        continue;
      
      if (($sym->flags & SYM_FLAG_EXTERN) ||
          ($sym->flags & SYM_FLAG_NATIVE))
        continue;
      
      if (($sym->flags & SYM_FLAG_CONST) &&
          $var->init->value &&
          $var->init->value->is_primitive() &&
          $sym->scope->is_global()) {
        // use a real constant
        $this->emit('const ', $sym->id, ' = ');
        $this->visit($var->init);
        $this->emitln(';');
      } else {
        if ($sym->flags & SYM_FLAG_STATIC) {
          // function static
          $this->emit('static $', $sym->id);
          
          if ($var->init) {
            $this->emit(' = ');
            
            if ($var->init->value && 
                $var->init->value->is_primitive()) {
              $this->visit($var->init);
              $this->emitln(';');
            } else {
              $this->emitln('null;');
              $this->emit('if ($', $sym->id, ' === null) {');
              $this->indent();
              $this->emit_top_assign($var->init, $sym->id);
              $this->dedent();
              $this->emitln('}');
            }
          } else
            $this->emitln(';');
        } else
          $this->emit_top_assign($var->init, $sym->id);
      }
    }
  }
  
  /**
   * Visitor#visit_var_list()
   *
   * @param  Node  $node
   */
  public function visit_var_list($node)
  {
    $this->emit('list (');
      
    foreach ($node->vars as $idx => $var) {
      if ($idx > 0) $this->emit(', ');
      $this->emit('$', $var->symbol->id);
    }
    
    $this->emit(') = ');
    $this->visit($node->expr);
    $this->emitln(';');
  }
  
  /**
   * Visitor#visit_use_decl()
   *
   * @param  Node  $node
   */
  public function visit_use_decl($node) 
  {
    // noop  
  }
  
  /**
   * Visitor#visit_require_decl()
   *
   * @param  Node  $node
   */
  public function visit_require_decl($node) 
  {
    $this->emit('require_once ');
    $this->emitqs(join_path($node->source->get_dest()));
    $this->emitln(';');
  }
  
  /**
   * Visitor#visit_label_decl()
   *
   * @param  Node  $node
   */
  public function visit_label_decl($node) 
  {
    $this->emitln(ident_to_str($node->id), ':');
    $this->visit($node->stmt);
  }
  
  /**
   * Visitor#visit_do_stmt()
   *
   * @param  Node  $node
   */
  public function visit_do_stmt($node) 
  {
    $this->emit('do ');
    $this->visit($node->stmt);
    $this->buff = rtrim($this->buff, "\n ");
    $this->emit(' while (');
    $this->visit($node->test);
    $this->emitln(');');  
  }
  
  /**
   * Visitor#visit_if_stmt()
   *
   * @param  Node  $node
   */
  public function visit_if_stmt($node) 
  {
    $this->emit('if (');
    $this->visit($node->test);
    $this->emit(') ');
    $this->visit($node->stmt);
    
    if ($node->elsifs) {
      foreach ($node->elsifs as $elsif) {
        $this->buff = rtrim($this->buff, "\n ");
        $this->emit(' elseif (');
        $this->visit($elsif->test);
        $this->emit(') ');
        $this->visit($elsif->stmt);   
      } 
    } 
    
    if ($node->els) {
      $this->buff = rtrim($this->buff, "\n ");
      $this->emit(' else ');
      $this->visit($node->els->stmt);
    }
  }  

  /**
   * Visitor#visit_for_stmt()
   *
   * @param  Node  $node
   */
  public function visit_for_stmt($node) 
  {
    if ($node->init && 
        ($node->init instanceof VarDecl ||
         $node->init instanceof VarList)) {
      // hoist declaration
      $this->visit($node->init);
      $this->emit('for (; ');
    } else {
      $this->emit('for (');
      
      if ($node->init) {
        $this->visit($node->init);
        $this->buff = rtrim($this->buff, "\n ");
        $this->emit(' ');
      } else
        $this->emit('; ');
    }
    
    if ($node->test) {
      $this->visit($node->test);
      $this->buff = rtrim($this->buff, "\n ");
      $this->emit(' ');
    } else
      $this->emit('; ');
    
    if ($node->each)
      foreach ($node->each as $idx => $expr) {
        if ($idx > 0) $this->emit(', ');
        $this->visit($expr);
      }
      
    $this->emit(') ');
    $this->visit($node->stmt);  
    
    if ($node->init instanceof VarDecl ||
        $node->init instanceof VarList) {
      $this->emit('unset (');
      
      foreach ($node->init->vars as $idx => $var) {
        if ($idx > 0) $this->emit(', ');
        $this->emit('$', $var->symbol->id);
      }
      
      $this->emit(');');
    }
  }  

  /**
   * Visitor#visit_for_in_stmt()
   *
   * @param  Node  $node
   */
  public function visit_for_in_stmt($node) 
  {
    $this->emit('foreach (');
    $this->visit($node->rhs);
    $this->emit(' as ');
    
    $key = $node->lhs->key;
    $arg = $node->lhs->arg;
    
    if ($key !== null)
      $this->emit('$', $key->symbol->id, ' => ');  
    
    $this->emit('$', $arg->symbol->id);
    $this->emit(') ');
    $this->visit($node->stmt);
    
    $this->emit('unset (');
    
    if ($key != null)
      $this->emit('$', $key->symbol->id, ', ');
    
    $this->emit('$', $arg->symbol->id);
    $this->emitln(');');
  }  

  /**
   * Visitor#visit_try_stmt()
   *
   * @param  Node  $node
   */
  public function visit_try_stmt($node) 
  {
    // TODO: implement try-catch-finally
  }  

  /**
   * Visitor#visit_php_stmt()
   *
   * @param  Node  $node
   */
  public function visit_php_stmt($node) 
  {
    $this->emitln('/* inline php {{ */');
    $this->indent();
    
    $unst = [];
    
    if ($node->usage)
      foreach ($node->usage as $usage)
        foreach ($usage->items as $item) {
          $this->emit('$');
          
          if ($item->alias)
            $sid = ident_to_str($item->alias);
          else
            $sid = ident_to_str($item->id);
          
          $unst[] = $sid;
          $this->emit($sid);           
          
          $this->emit(' = &');
          $this->emit_sym_ref($item->id->symbol);
          $this->emitln(';');
        }
    
    $this->emit_str($node->code, true, false);
    
    if ($unst) {
      $this->emitln();
      $this->emit('unset (');
      foreach ($unst as $idx => $sid) {
        if ($idx > 0) $this->emit(', ');
        $this->emit('$', $sid);
      }
      $this->emitln(');');
    }
    
    $this->dedent();
    $this->emitln('/* inline php }} */');
  }  

  /**
   * Visitor#visit_goto_stmt()
   *
   * @param  Node  $node
   */
  public function visit_goto_stmt($node) 
  {
    $this->emitln('goto ', ident_to_str($node->id), ';');  
  }  

  /**
   * Visitor#visit_test_stmt()
   *
   * @param  Node  $node
   */
  public function visit_test_stmt($node) 
  {
    // if INCLUDE_TESTS
    // ...
    // TODO: implement
  }  

  /**
   * Visitor#visit_break_stmt()
   *
   * @param  Node  $node
   */
  public function visit_break_stmt($node) 
  {
    $this->emit('break');
    
    if ($node->level > 1)
      $this->emit(' ', $node->level);
    
    $this->emitln(';');
  }

  /**
   * Visitor#visit_continue_stmt()
   *
   * @param  Node  $node
   */
  public function visit_continue_stmt($node) 
  {
    $this->emit('continue');
    
    if ($node->level > 1)
      $this->emit(' ', $node->level);
    
    $this->emitln(';');
  }

  /**
   * Visitor#visit_print_stmt()
   *
   * @param  Node  $node
   */
  public function visit_print_stmt($node) 
  {
    $this->emit('echo ');
    
    // empty string just for a new line
    // -> ignore
    if (count($node->expr) === 1 && 
        $node->expr[0] instanceof StrLit &&
        empty ($node->expr[0]->parts) &&
        $node->expr[0]->data === '')
      goto out;
    
    foreach ($node->expr as $i => $expr) {
      if ($i > 0) $this->emit(', ');
      
      if ($expr instanceof StrLit)
        $this->emit_str($expr, false);
      else
        $this->visit($expr);
    }
    
    $this->emit(', ');
    
    out:
    $this->emitln('\\PHP_EOL;');
  }  

  /**
   * Visitor#visit_throw_stmt()
   *
   * @param  Node  $node
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
   */
  public function visit_assert_stmt($node) 
  {
    $this->emit('\\assert(');
    $this->visit($node->expr);
    
    if ($node->message) {
      $this->emit(', ');
      $this->visit($node->message);
    }
    
    $this->emitln(');');  
  }  

  /**
   * Visitor#visit_switch_stmt()
   *
   * @param  Node  $node
   */
  public function visit_switch_stmt($node) 
  {
    $this->emit('switch (');
    $this->visit($node->test);
    $this->emitln(') {');
    $this->indent();
    
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
          $this->emitln();
      }
      
      $this->indent();
      $this->visit($case->body);
      $this->dedent();
    }
    
    $this->dedent();
    $this->emitln('}');
  }  

  /**
   * Visitor#visit_return_stmt()
   *
   * @param  Node  $node
   */
  public function visit_return_stmt($node) 
  {
    $temp = null;
    if ($node->expr) {
      $expr = $node->expr;
      
      if ($expr instanceof ObjLit)
        // hoist value first
        $temp = $this->hoist_dict_expr($expr);
      elseif ($expr instanceof CallExpr) {
        // hoist call
        $ctmp = '_T' . (self::$temp++);
        $this->visit_top_call_expr($expr, $ctmp);
        $this->emitln(';');
        self::$temp--;
        $temp = '$' . $ctmp;
      }
    }
    
    $this->emit('return');
    
    if ($node->expr) {
      $this->emit(' ');
      
      if ($temp !== null)
        $this->emit($temp);
      else
        $this->visit($node->expr);
    }
    
    $this->emitln(';');
  }  

  /**
   * Visitor#visit_expr_stmt()
   *
   * @param  Node  $node
   */
  public function visit_expr_stmt($node) 
  {
    if ($node->expr)
      foreach ($node->expr as $expr) {
        /* specialized call */
        if ($expr instanceof CallExpr)
          $this->visit_top_call_expr($expr);
        
        /* specialized assign */
        elseif ($expr instanceof AssignExpr)
          $this->visit_top_assign_expr($expr);
        
        /* everything else */
        else
          $this->visit($expr);
        
        $this->emitln(';');
      }
    else
      $this->emitln(';');
  }
  
  /**
   * specialized version of Visitor#visit_call_expr()
   *
   * @param  Node $node
   * @param  string  $temp
   */
  public function visit_top_call_expr($node, $temp = null)
  {
    /* chained call */
    if ($node->callee instanceof CallExpr) {
      // this is a optimized form of what 
      // visit_call_expr() already does with chained calls
      
      $decr = $temp === null;
      $list = $this->collect_callees($node);
      $temp = $temp ?: '_T' . (self::$temp++);
      $init = array_pop($list);
      
      // special dict-argument-handler
      $args = $this->hoist_dict_args($init->args);
      
      // foo()() ->
      // $_T = foo();
      // $_T = $_T();
      // ...
      $this->emit('$', $temp, ' = ');
      $this->call = true;
      $this->visit($init->callee);
      $this->call = false;
      $this->visit_fn_args($args);
      
      $tmpd = [];
      foreach ($args as $arg)
        if (is_string($arg))
          $tmpd[] = $arg;
        
      while (!empty ($list)) {
        $this->emitln(';');
        $this->emit('$', $temp, ' = $', $temp);
        
        $call = array_pop($list);
        $args = $this->hoist_dict_args($call->args);
        $this->visit_fn_args($args);
        
        foreach ($args as $arg)
          if (is_string($arg))
            $tmpd[] = $arg;
      }
      
      if ($decr === true) {
        self::$temp--;
        $tmpd[] = '$' . $temp;
      }
      
      if (!empty ($tmpd)) {
        $this->emitln(';');
        $this->emit('unset (');
        foreach ($tmpd as $idx => $temp) {
          if ($idx > 0) $this->emit(', ');
          $this->emit($temp);
        }
        $this->emit(')');
      }
    
    /* super call */
    } elseif ($node->callee instanceof SuperExpr) {
      $args = $this->hoist_dict_args($node->args);
      $this->emit('parent::__construct');
      $this->visit_fn_args($args);    
    
    /* iife */
    } elseif ($node->callee instanceof FnExpr ||
              $node->callee instanceof ParenExpr) {
      $call = $node->callee;
      if ($call instanceof ParenExpr)
        $call = $call->expr;
      
      $decr = $temp === null;
      $temp = $decr ? '_T' . (self::$temp) : $temp;
      
      $this->emit('$', $temp, ' = ');
      $this->visit($call);
      $this->emitln(';');
      
      $args = $this->hoist_dict_args($node->args);
      
      $this->emit('$', $temp, ' = $', $temp);
      $this->visit_fn_args($args);
      $this->emitln(';');
      
      if ($decr) {
        $this->emitln('unset ($', $temp, ');');
        self::$temp--;
      }
    
    /* normal call */
    } else {
      $args = $this->hoist_dict_args($node->args);
      
      if ($temp !== null)
        $this->emit('$', $temp, ' = ');
      
      $this->call = true;
      $this->visit($node->callee);
      $this->call = false;
      $this->visit_fn_args($args);
      
      $tmpd = [];
      if ($args)
        foreach ($args as $arg)
          if (is_string($arg))
            $tmpd[] = $arg;
        
      if (!empty ($tmpd)) {
        $this->emitln(';');
        $this->emit('unset (');
        foreach ($tmpd as $idx => $temp) {
          if ($idx > 0) $this->emit(', ');
          $this->emit($temp);
        }
        $this->emit(')');
      }
    }
  }
  
  /**
   * specialized version of Visitor#visit_assign_expr()
   *
   * @param  Node $node
   */
  public function visit_top_assign_expr($node)
  {    
    /* hoist dictionary literals */
    if ($node->right instanceof ObjLit) {
      $temp = $this->hoist_dict_expr($node->right);
      $this->visit($node->left);
      $this->emit_op($node->op);
      
      if ($temp !== null) {
        $this->emit($temp);
        $this->emitln(';');
        $this->emit('unset (', $temp, ')');
      } else
        $this->visit($node->right);
      
    /* call */
    } elseif ($node->right instanceof CallExpr) {
      // layout call
      if (($node->left instanceof Name ||
           $node->left instanceof Ident) &&
          $node->op->type === T_ASSIGN &&
          $node->left->symbol instanceof VarSymbol) {
        $temp = $node->left->symbol->id;
        $this->visit_top_call_expr($node->right, $temp);
        
      // hoist call
      } else {
        $temp = '_T' . (self::$temp++);
        $this->visit_top_call_expr($node->right, $temp);
        $this->emitln(';');
        $this->visit($node->left);
        $this->emit(' ');
        $this->emit_op($node->op);
        $this->emit(' ');
        $this->emitln('$', $temp, ';');
        $this->emit('unset ($', $temp, ')');
        self::$temp--;
      }
      
    /* not special */ 
    } else
      $this->visit_assign_expr($node);
  }  

  /**
   * Visitor#visit_paren_expr()
   *
   * @param  Node  $node
   */
  public function visit_paren_expr($node) 
  {
    $call = $this->call;
    $this->call = false;
    
    $this->emit('(');
    $this->visit($node->expr);
    $this->emit(')');  
    
    $this->call = $call;
  }  

  /**
   * Visitor#visit_tuple_expr()
   *
   * @param  Node  $node
   */
  public function visit_tuple_expr($node) 
  {
    if ($this->emit_value($node->value))
      return;
    
    $call = $this->call;
    $this->call = false;
    
    $this->emit('[ ');
    
    foreach ($node->seq as $idx => $item) {
      if ($idx > 0) $this->emit(', ');
      $this->visit($item);
    }
    
    $this->emit(' ]');
    
    $this->call = $call;
  }  

  /**
   * Visitor#visit_fn_expr()
   *
   * @param  Node  $node
   */
  public function visit_fn_expr($node) 
  {
    $this->emit_fn_expr($node);
  }
  
  /**
   * Visitor#visit_bin_expr()
   *
   * @param  Node  $node
   */
  public function visit_bin_expr($node) 
  {
    if ($this->emit_value($node->value))
      return;
    
    if ($node->op->type === T_IN ||
        $node->op->type === T_NIN) {
      
      if ($node->op->type === T_NIN)
        $this->emit('!');
      
      // boilerplate
      $tmp1 = '_T' . (self::$temp++);
      $tmp2 = '_T' . (self::$temp++);
      
      $this->emitln('[');
      $this->indent();
      $this->emit('0 => $', $tmp1, ' = ');
      $this->visit($node->left);
      $this->emitln(', ');
      $this->emit('0 => $', $tmp2, ' = ');
      $this->visit($node->right);
      $this->emitln(', ');
      $this->emit('0 => (is_array($', $tmp2, ') ?');
      $this->emit(' in_array($', $tmp1, ', $', $tmp2, ', true) :'); // array
      $this->emit(' (is_string($', $tmp2, ') ?');
      $this->emit(' strpos($', $tmp2, ', $', $tmp1, ') !== false :'); // string
      $this->emit(' ($', $tmp2, ' instanceof \\Inable ?');
      $this->emit(' $', $tmp2, '->contains($', $tmp1, ') : false)))'); // object
      $this->emitln();
      $this->dedent();
      $this->emit('][0]');
      
      self::$temp -= 2;
      
    } else {
      $this->visit($node->left);
      $this->emit(' ');
      $this->emit_op($node->op, true);
      $this->emit(' ');
      $this->visit($node->right);  
    }  
  }
  
  /**
   * Visitor#visit_check_expr()
   *
   * @param  Node  $node
   */
  public function visit_check_expr($node) 
  {
    if ($this->emit_value($node->value))
      return;
    
    if ($node->op->type === T_NIS)
      $this->emit('!');
    
    if ($node->right instanceof TypeId) {
      $this->emit('\\is_');
      
      switch ($node->right->type) {
        case T_TINT:
          $this->emit('int');
          break;
        case T_TBOOL:
          $this->emit('bool');
          break;
        case T_TFLOAT:
          $this->emit('float');
          break;
        case T_TSTR:
          $this->emit('string');
          break;
        case T_TTUP:
          $this->emit('array');
          break;
        case T_TDEC:
          $this->emit('numeric');
          break;
        case T_TOBJ:
          $this->emit('object');
          break;
        case T_TCALLABLE:
          $this->emit('callable');
          break;
        default:
          assert(0);
      }
      
      $this->emit('(');
      $this->visit($node->left);
      $this->emit(')');
      
    } else {
      $this->visit($node->left);
      $this->emit(' instanceof ');
      
      $call = $this->call;
      $this->call = true; 
      
      $this->visit($node->right);
      
      $this->call = $call;
    }
  }
  
  /**
   * Visitor#visit_cast_expr()
   *
   * @param  Node  $node
   */
  public function visit_cast_expr($node) 
  {
    if ($node->type instanceof TypeId) {
      if ($node->type->type === T_TDEC) {
        $this->emit('(');
        $this->visit($node->expr);
        $this->emit(') + 0');
      } else {
        $this->emit('(');
        
        switch ($node->type->type) {
          case T_TINT:
            $this->emit('int');
            break;
          case T_TBOOL:
            $this->emit('bool');
            break;
          case T_TFLOAT:
            $this->emit('float');
            break;
          case T_TSTR:
            $this->emit('string');
            break;
          case T_TTUP:
            $this->emit('array');
            break;
          case T_TOBJ:
            $this->emit('object');
          default:
            assert(0);
        }
        
        $this->emit(')');
        $this->visit($node->expr);
      }
    } else {
      $this->emit('\\', path_to_ns($node->type->symbol->path()));
      $this->emit('::from(');
      $this->visit($node->expr);
      $this->emit(')');
    }
  }

  /**
   * Visitor#visit_update_expr()
   *
   * @param  Node  $node
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
   */
  public function visit_assign_expr($node) 
  {
    $this->visit($node->left);
    $this->emit(' ');
    $this->emit_op($node->op);
    $this->emit(' ');
    
    $paren = false;
    if ($node->right instanceof YieldExpr) {
      $paren = true;
      $this->emit('(');
    }
    
    $this->visit($node->right);  
    
    if ($paren)
      $this->emit(')');
  }
  
  /**
   * Visitor#visit_member_expr()
   *
   * @param  Node  $node
   */
  public function visit_member_expr($node) 
  {
    if ($this->emit_value($node->value))
      return;
    
    $paren = false;
    if ($node->object instanceof NewExpr) {
      $paren = true;
      $this->emit('(');
    }
    
    if ($node->symbol)
      // static member expression
      $this->emit_sym_ref($node->symbol);
    else {
      $call = $this->call;
      $this->call = false;
      
      $memb = $this->member;
      $this->member = true;
      
      $this->visit($node->object);
      
      if ($paren)
        $this->emit(')');
      
      if ($node->object instanceof SelfExpr ||
          $node->object instanceof SuperExpr ||
          ($node->object instanceof Name &&
           $node->object->symbol instanceof ClassSymbol))
        $this->emit('::');
      else
        $this->emit('->');
          
      if ($node->computed) {
        $this->emit('{'); 
        $this->visit($node->member);
        $this->emit('}');
      } else
        $this->emit(ident_to_str($node->member));
      
      $this->call = $call;
      $this->member = $memb;
    }
  }

  /**
   * Visitor#visit_offset_expr()
   *
   * @param  Node  $node
   */
  public function visit_offset_expr($node) 
  {
    if ($this->emit_value($node->value))
      return;
    
    $call = $this->call;
    $this->call = false;
    
    $this->visit($node->object);
    $this->emit('[');
    $this->visit($node->offset);
    $this->emit(']');
    
    $this->call = $call;
  }
  
  /**
   * Visitor#visit_cond_expr()
   *
   * @param  Node  $node
   */
  public function visit_cond_expr($node) 
  {
    if ($this->emit_value($node->value))
      return;
    
    $this->emit('(');
    $this->visit($node->test);
    $this->emit(' ? ');
    
    if ($node->then)
      $this->visit($node->then);
    
    $this->emit(' : ');
    $this->visit($node->els);
    $this->emit(')');
  }
  
  /**
   * Visitor#visit_call_expr()
   *
   * @param  Node  $node
   */
  public function visit_call_expr($node) 
  {
    /* chained call */
    if ($node->callee instanceof CallExpr) {
      // foo()() is not possible in PHP
      // therefore we have to generate a workaround      
      
      $list = $this->collect_callees($node);
      $temp = '_T' . (self::$temp++);
      $init = array_pop($list);
      
      // foo()() ->
      // [
      //   0 => $_T = foo(), 
      //   0 => $_T = $_T() 
      // ][0]
      $this->emitln('[ /* chained call */');
      $this->indent();
      $this->emit('0 => ($', $temp, ' = ');
      
      $this->call = true;
      $this->visit($init->callee);
      $this->call = false;
      $this->visit_fn_args($init->args);
      
      while (!empty ($list)) {
        $this->emitln('),');
        $this->emit('0 => ($', $temp, ' = $', $temp);
        $this->visit_fn_args(array_pop($list)->args);
      }
      
      $this->emit(')');
      $this->dedent();
      $this->emit('][0]');
      
      self::$temp--;
      
    /* iife */
    } elseif ($node->callee instanceof FnExpr ||
              $node->callee instanceof ParenExpr) {
      $temp = '_T' . (self::$temp++);
      $this->emitln('[');
      $this->indent();
      
      $this->emit('0 => ($', $temp, ' = ');
        
      $call = $node->callee;
      if ($call instanceof ParenExpr)
        $call = $call->expr;
      
      $this->visit($call);
      
      $this->emitln('),');
      $this->emit('0 => $', $temp);
      $this->visit_fn_args($node->args);
      
      $this->dedent();
      $this->emit('][0]');
      
      self::$temp--;
      
    /* normal call */  
    } else {
      $this->call = true;
      $this->visit($node->callee);
      $this->call = false;
      $this->visit_fn_args($node->args);
    }
  }
  
  /**
   * Visitor#visit_yield_expr()
   *
   * @param  Node  $node
   */
  public function visit_yield_expr($node) 
  {
    $this->emit('yield ');
    
    if ($node->key) {
      $this->visit($node->key);
      $this->emit(' => ');
    }
    
    $this->visit($node->arg);
  }

  /**
   * Visitor#visit_unary_expr()
   *
   * @param  Node  $node
   */
  public function visit_unary_expr($node) 
  {
    $this->emit($node->op->value);
    $this->visit($node->expr);
  }

  /**
   * Visitor#visit_new_expr()
   *
   * @param  Node  $node
   */
  public function visit_new_expr($node) 
  {
    $this->call = true;
    
    $this->emit('new ');
    $this->visit($node->name);
    $this->visit_fn_args($node->args);
    
    $this->call = false;
  }

  /**
   * Visitor#visit_del_expr()
   *
   * @param  Node  $node
   */
  public function visit_del_expr($node) 
  {
    $this->emit('unset (');
    $this->visit($node->expr);
    $this->emit(')');
  }
  

  /**
   * Visitor#visit_lnum_lit()
   *
   * @param  Node  $node
   */
  public function visit_lnum_lit($node) 
  {
    $this->emit($node->data);  
  }
  
  /**
   * Visitor#visit_dnum_lit()
   *
   * @param  Node  $node
   */
  public function visit_dnum_lit($node) 
  {
    $this->emit($node->data);  
  }
  
  /**
   * Visitor#visit_snum_lit()
   *
   * @param  Node  $node
   */
  public function visit_snum_lit($node) 
  {
    Logger::assert_at($node->loc, false, 'snum-lit!');
  }
  
  /**
   * Visitor#visit_regexp_lit()
   *
   * @param  Node  $node
   */
  public function visit_regexp_lit($n) {}  

  /**
   * Visitor#visit_arr_gen()
   *
   * @param  Node  $node
   */
  public function visit_arr_gen($n) {}
  
  /**
   * Visitor#visit_arr_lit()
   *
   * @param  Node  $node
   */
  public function visit_arr_lit($node) 
  {
    if ($this->emit_value($node->value))
      return;
    
    $this->emit('new \\List_(');
    
    if (!empty ($node->items))
      foreach ($node->items as $idx => $item) {
        if ($idx > 0) $this->emit(', ');
        $this->visit($item);
      }
    
    $this->emit(')');
  }
  
  /**
   * Visitor#visit_obj_lit()
   *
   * @param  Node  $node
   */
  public function visit_obj_lit($node) 
  {
    if ($this->emit_value($node->value))
      return;
    
    $this->emit('new \\Dict(');
    
    if (!empty ($node->pairs)) {
      $this->emitln('[');
      $this->indent();
      
      foreach ($node->pairs as $idx => $pair) {
        if ($idx > 0) $this->emitln(', ');
        if ($pair->key instanceof ObjKey) {
          $this->emit('(');
          $this->visit($pair->key->expr);
          $this->emit(')');
        } else
          $this->emitqs(ident_to_str($pair->key));
        
        $this->emit(' => ');
        $this->visit($pair->arg);
      }
      
      $this->dedent();
      $this->emit(']');
    }
    
    $this->emit(')');  
  }
  
  /**
   * Visitor#visit_name()
   *
   * @param  Node  $node
   */
  public function visit_name($node) 
  {
    $sym = $node->symbol;
    
    Logger::assert_at($node->loc, $sym !== null,
      'name %s has no symbol!', name_to_str($node));
    
    $this->emit_sym_ref($sym);
  }
  
  /**
   * Visitor#visit_ident()
   *
   * @param  Node  $node
   */
  public function visit_ident($node) 
  {
    Logger::assert_at($node->loc, false, 
      'visit ident %s ?', ident_to_str(node));
  }  

  /**
   * Visitor#visit_this_expr()
   *
   * @param  Node  $node
   */
  public function visit_this_expr($node) 
  {
    $this->emit('$this');
  }
  
  /**
   * Visitor#visit_super_expr()
   *
   * @param  Node  $node
   */
  public function visit_super_expr($node) 
  {
    if ($this->member)
      $this->emit('parent');
    else
      $this->emit('\'\\', path_to_ns($node->symbol->path()), '\'');
  }  

  /**
   * Visitor#visit_self_expr()
   *
   * @param  Node  $node
   */
  public function visit_self_expr($node) 
  {
    if ($this->member || $this->call)
      $this->emit('static');
    else
      $this->emit('\'\\', path_to_ns($node->symbol->path()), '\'');
  }
  
  /**
   * Visitor#visit_null_lit()
   *
   * @param  Node  $node
   */
  public function visit_null_lit($node) 
  {
    $this->emit('null');  
  }
  
  /**
   * Visitor#visit_true_lit()
   *
   * @param  Node  $node
   */
  public function visit_true_lit($node) 
  {
    $this->emit('true');  
  }
  
  /**
   * Visitor#visit_false_lit()
   *
   * @param  Node  $node
   */
  public function visit_false_lit($node) 
  {
    $this->emit('false');  
  }
  
  /**
   * Visitor#visit_str_lit()
   *
   * @param  Node  $node
   */
  public function visit_str_lit($node) 
  {
    $this->emit_str($node);
  }
  
  /**
   * Visitor#visit_kstr_lit()
   *
   * @param  Node  $node
   */
  public function visit_kstr_lit($node) 
  {
    $this->emitqs($node->data);  
  }
  
  /**
   * Visitor#visit_type_id()
   *
   * @param  Node  $node
   */
  public function visit_type_id($node) 
  {
    // noop  
  }
  
  /**
   * Visitor#visit_engine_const()
   *
   * @param  Node  $node
   */
  public function visit_engine_const($node) 
  {
    $this->emitqs($node->data);
  }
}
