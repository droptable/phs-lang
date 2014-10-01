<?php

namespace phs\front;


// NEXT: `this` and `super`



require_once 'utils.php';
require_once 'visitor.php';
require_once 'symbols.php';
require_once 'values.php';
require_once 'scope.php';
require_once 'lookup.php';

use phs\Logger;
use phs\Session;
use phs\FileSource;

use phs\util\Set;
use phs\util\Map;
use phs\util\Cell;
use phs\util\Entry;
use phs\util\Table;

use phs\front\ast\Node;
use phs\front\ast\Unit;

use phs\front\ast\Expr;
use phs\front\ast\Name;
use phs\front\ast\Ident;
use phs\front\ast\TypeId;
use phs\front\ast\UseAlias;
use phs\front\ast\UseUnpack;
use phs\front\ast\Param;
use phs\front\ast\ThisParam;
use phs\front\ast\RestParam;
use phs\front\ast\ClassDecl;
use phs\front\ast\IfaceDecl;
use phs\front\ast\TraitDecl;
use phs\front\ast\SelfExpr;
use phs\front\ast\ThisExpr;
use phs\front\ast\SuperExpr;

use phs\lang\BuiltInList;
use phs\lang\BuiltInDict;

// type-context 
const
  TYC_ANY  = 1, // no specific context
  TYC_NEW  = 2, // new-context (new-expression)
  TYC_HINT = 3, // hint-context (parameter)
  TYC_CAST = 4  // cast-context (cast-expression) 
;

/** 
 * unit resolver
 * 
 * - executes <require>
 * - executes <use>
 * - checks reachability
 */
class UnitResolver extends AutoVisitor
{  
  // mixin lookup-methods
  use Lookup;
  
  // mixin late-bindings for __class__ and __method__
  use LateBindings;
  
  // @var Session
  private $sess;
  
  // @var string  root path
  private $rpath;
  
  // @var string  current unit path
  private $upath;
  
  // @var Scope
  private $scope;
  
  // @var UnitScope
  private $sunit;
  
  // @var GlobScope
  private $sglob;
  
  // @var ClassSymbol  current class
  // to resolve `this` and `super`
  private $cclass;
  
  // @var array<FnSymbol>  function stack
  private $fnstk;
  
  // @var NodeCollector  used for applied trait-members
  private $ncol;
  
  /**
   * constructor
   *
   * @param Session $sess
   */
  public function __construct(Session $sess)
  {
    parent::__construct();
    $this->sess = $sess;
    $this->ncol = new NodeCollector($this->sess);
    $this->rpath = $sess->rpath;
    $this->sglob = $sess->scope;
  }
  
  /**
   * resolver
   *
   * @param  Unit      $unit 
   * @return void
   */
  public function resolve(Unit $unit)
  {
    $this->upath = dirname($unit->loc->file);
    $this->sunit = $unit->scope; 
    $this->fnstk = [];
    
    $this->enter($this->sunit, $unit);
  }
  
  /* ------------------------------------ */
  
  /**
   * enters a scope
   *
   * @param  Scope $next
   * @param  Node|array $node
   */
  private function enter(Scope $next, $node)
  {
    $prev = $this->scope;
    $this->scope = $next;
    
    $this->scope->enter();
    
    // resolve classes, interfaces and traits first
    $this->resolve_types();
    
    $this->visit($node);
    $this->scope->leave();
    
    $this->scope = $prev;
  }
  
  /**
   * enters a function
   *
   * @param  FnDecl|FnExpr|CtorDecl|DtorDecl|GetterDecl|SetterDecl $node
   */
  private function enter_fn($node)
  {
    $prev = $this->scope;
    $this->scope = $node->scope;
    
    $this->scope->enter();
    
    if ($node->symbol && $node->symbol->origin)
      // function comes from a trait!
      // make origin-scope accessible
      $this->scope->delegate($node->symbol->origin->scope);
    
    array_push($this->fnstk, $node->symbol);
    
    $this->visit_fn_params($node->params);
    $this->visit($node->body);
    $this->scope->leave();
    
    $this->scope = $prev;
    
    array_pop($this->fnstk);
  }
  
  /* ------------------------------------ */
  
  /**
   * collects symbols inside a node (late bindings)
   *
   * @param  Symbol $sym
   * @param  Scope $scope
   */
  private function collect(Symbol $sym, Scope $scope)
  {
    // make cyclic reference ...
    $node = $sym->node;
    $node->symbol = $sym;
    
    $this->ncol->collect_node($node, $scope);
  }
  
  /* ------------------------------------ */
    
  /**
   * processes a require-declaration
   *
   * @param  string $path
   * @param  boolean $php
   * @param  Location $loc
   */
  private function process_require($path, $php, $loc)
  {
    static $re_abs = null;
    static $re_ext = '/\.ph[sp]$/';
    
    if ($re_abs === null) {
      $re_abs = '/^(?:\\/';
      
      // allow '/' and '\' on windows
      if (PHP_OS === 'WINNT')
        $re_abs .= '|\\\\';
      
      $re_abs .= ')/';
    } 
    
    // 1. replace slashes 
    $path = str_replace([ '\\', '/' ], DIRECTORY_SEPARATOR, $path);
      
    // 2.1. make path absolute
    if (!preg_match($re_abs, $path))
      $path = $this->upath . DIRECTORY_SEPARATOR . $path;
    
    // 2.2. make absolute path relative to root-unit
    else
      $path = $this->rpath . $path;
    
    // 3. add extension if necessary
    if (!preg_match($re_ext, $path))
      $path .= $php ? '.php' : '.phs';
    
    //               destination --v
    $fsrc = new FileSource($path, null, $php, $loc);
    $this->sess->add_source($fsrc);
  }
    
  /**
   * handles a lookup result
   *
   * @param  Node  $node
   * @param  string $sid
   * @param  ScResult $res
   * @return boolean
   */
  private function process_lookup($node, $res)
  {
    $ref = null;
    
    if ($node instanceof Name)
      $ref = name_to_str($node);
    elseif ($node instanceof Ident)
      $ref = ident_to_str($node);
    else
      assert(0);
    
    // no symbol found
    if ($res->is_none() && !$res->is_priv())
      Logger::error_at($node->loc, 'access to undefined symbol `%s`', $ref);
    
    // private symbol "trap"
    elseif ($res->is_priv()) {
      $sym = &$res->unwrap();
      Logger::error_at($node->loc, 'access to private %s \\', $sym);
      Logger::error('from invalid context');
      Logger::error_at($node->loc, 'referenced as %s', $ref);
      Logger::error_at($sym->loc, 'declaration was here');
    }
    
    // error
    elseif ($res->is_error()) {
      Logger::error_at($node->loc, '[bug] error while looking up `%s`', $ref);
    }
    
    // yay!
    else {
      $sym = &$res->unwrap();
      
      if (!$sym->reachable) {
        // nay ...
        Logger::error_at($node->loc, 'access to undefined symbol `%s`', $ref);
        Logger::info_at($sym->loc, 'a variable with the name `%s` \\', $ref);
        Logger::info('gets defined here but is not yet accessible');
        Logger::info('try to move variable-declarations at the top of \\');
        Logger::info('the file or blocks {...} to avoid errors like this');
      } else {
        $node->symbol = $sym;
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * resolves all types in the current scope
   *
   */
  private function resolve_types()
  {
    foreach ($this->scope->iter(SYM_NS2) as $sym)   
      switch ($sym->kind) {
        case SYM_KIND_CLASS:
          $this->resolve_class($sym);
          break;
        case SYM_KIND_TRAIT:
          $this->resolve_trait($sym);
          break;
        case SYM_KIND_IFACE:
          $this->resolve_iface($sym);
          break;
        default:
          assert(0);
      }
  }
  
  /**
   * resolves a class symbol
   *
   * @param  ClassSymbol $sym
   */
  public function resolve_class($sym)
  {
    if ($sym->resolved) return;
    $this->resolve_super($sym);
    $this->resolve_ifaces($sym);
    $this->resolve_traits($sym);
    
    // mark class itself as abstract if 
    // one (or more) methods are abstract
    foreach ($sym->members->iter(SYM_FN_NS) as $fn) {
      if ($fn->flags & SYM_FLAG_ABSTRACT) {
        $sym->flags |= SYM_FLAG_ABSTRACT;
        break;
      }
    }
    
    // mark vars inside as reachable
    foreach ($sym->members->iter(SYM_VAR_NS) as $var)
      if ($var instanceof VarSymbol)
        $var->reachable = true;
    
    $sym->resolved = true;
  }
  
  /**
   * resolves a trait symbol
   *
   * @param  TraitSymbol $sym
   */
  public function resolve_trait($sym)
  {
    if ($sym->resolved) return;
    $this->resolve_traits($sym);
    $sym->resolved = true;
  }
  
  /**
   * resolves a interface symbol
   *
   * @param  IfaceSymbol $sym
   */
  public function resolve_iface($sym)
  {
    if ($sym->resolved) return;
    $this->resolve_ifaces($sym);
    $sym->resolved = true;
  }
  
  /**
   * resolve a super-class
   *
   * @param  ClassSymbol $csym
   */
  private function resolve_super($csym) 
  {
    if (!$csym->super) return;
    
    $sup = $csym->super;
    $res = $this->lookup_name($sup, SYM_CLASS_NS);
    
    if ($this->process_lookup($sup, $res)) {
      $sym = &$res->unwrap();
      
      if ($sym->kind !== SYM_KIND_CLASS) {
        Logger::error_at($sup->loc, 'super-class `%s` \\', name_to_str($sup));
        Logger::error('does not resolve to a class-symbol');
      } else {
        $csym->super->symbol = $sym;
        
        if ($sym->resolved === false)
          $this->resolve_class($sym);
        
        // install super-class in member-scope
        $csym->members->super = $sym->members;
      }
    }
  }
  
  /**
   * resolves interfaces
   *
   * @param  IfaceSymbol|ClassSymbol $csym
   */
  private function resolve_ifaces($csym)
  {
    if (!$csym->ifaces) return;
    
    foreach ($csym->ifaces as $iface) {
      $res = $this->lookup_name($iface, SYM_IFACE_NS);
      
      if ($this->process_lookup($iface, $res)) {
        $sym = &$res->unwrap();
        
        if ($sym->kind !== SYM_KIND_IFACE) {
          Logger::error_at($iface->loc, 'implementation `%s` \\', name_to_str($iface));
          Logger::error('does not resolve to a interface');
        } else {
          $iface->symbol = $sym;
          
          if ($sym->resolved === false)
            $this->resolve_iface($sym);
        }
      }
    }
  }
  
  /**
   * resolves trait-usage
   *
   * @param  TraitSymbol|ClassSymbol $csym
   */
  private function resolve_traits($csym)
  {
    if (!$csym->traits) return;
    
    $okay = [];
    foreach ($csym->traits as $trait) {
      // TODO: multiple usage should not be resolved every time
      $use = $trait->trait;
      $res = $this->lookup_name($use, SYM_TRAIT_NS);
      
      if ($this->process_lookup($use, $res)) {
        $sym = &$res->unwrap();
        
        if ($sym->kind !== SYM_KIND_TRAIT) {
          Logger::error_at($use->loc, 'trait-usage `%s` \\', name_to_str($use));
          Logger::error('does not resolve to a trait-symbol');
        } else {
          $use->symbol = $sym;
          
          if ($sym->resolved === false)
            $this->resolve_trait($sym);
          
          $okay[] = $trait;
        }
      }
    }
    
    $this->resolve_trait_usage($csym, $okay);
  }
  
  /**
   * resolves usage from traits
   *
   * @param  TraitSymbol|ClassSymbol $csym
   * @param  array  $traits
   */
  private function resolve_trait_usage($csym, array $traits)
  {
    $is_class = $csym instanceof ClassSymbol;
    
    foreach ($traits as $trait) {
      $use = $trait->trait->symbol;
      
      if ($trait->orig) {
        // use one item
        $res = $use->members->get($trait->orig);
        
        if (!$res->is_some()) {
          Logger::error_at($trait->loc, 'trait `%s` has \\', $use->id);
          Logger::error('has no member called `%s`', $trait->orig);
        } else {          
          $sym = &$res->unwrap();
          
          if ($csym->members->has($trait->dest ?: $sym->id, $sym->ns))
            continue;
          
          $dup = clone $sym;
          
          if ($trait->flags !== SYM_FLAG_NONE)
            $dup->flags = $trait->flags;
          
          if ($trait->dest)
            $dup->id = $trait->dest;
          
          // save origin for proper error-reporting
          if ($dup->origin === null)
            $dup->origin = $use; 
          
          $csym->members->add($dup);
          
          if ($is_class)
            $this->collect($dup, $csym->members);
        }
      } else {
        // use all items
        foreach ($use->members->iter() as $sym) {
          if ($csym->members->has($sym->id, $sym->ns))
            continue;
          
          $dup = clone $sym;
          
          // save origin for proper error-reporting
          if ($dup->origin === null)
            $dup->origin = $use;
          
          $csym->members->add($dup);
          
          if ($is_class)
            $this->collect($dup, $csym->members);
        }
      }
    }
  }
  
  /**
   * resolves members of a class/trait
   *
   * @param  ClassSymbol|TraitSymbol $csym
   */
  private function resolve_members($csym)
  {
    foreach ($csym->members->iter() as $sym)
      if ($sym instanceof VarSymbol) {
        if ($sym->node->init)
          $this->visit($sym->node->init);
      } else
        $this->enter_fn($sym->node);
  }
  
  /**
   * resolves a type
   * 
   * @param  Node  $node
   * @param  int   $flag
   * @return boolean
   */
  private function resolve_type($node, $tyc = TYC_ANY)
  {
    // typeid is always allowed
    if ($node instanceof TypeId)
      return true;
    
    if ($node instanceof Name)
      $res = $this->lookup_name($node);
    elseif ($node instanceof Ident)
      $res = $this->lookup_ident($node);
    elseif ($node instanceof SelfExpr)
      // well this depends ... TODO
      return true;
    else
      assert(0);
    
    if (!$this->process_lookup($node, $res))
      return false;
    
    $sym = &$res->unwrap();
    
    // basic test
    if ($sym->kind !== SYM_KIND_CLASS &&
        $sym->kind !== SYM_KIND_IFACE)
      return false;
    
    switch ($tyc) {
      case TYC_ANY:
      case TYC_HINT:
        // no special restrictions
        return true;
      
      case TYC_NEW: 
        // must be a class and not abstract
        return $sym->kind === SYM_KIND_CLASS &&
               !($sym->flags & SYM_FLAG_ABSTRACT);
               
      case TYC_CAST:
        // must be a class and must have a static method named "from"
        if ($sym->kind !== SYM_KIND_CLASS)
          return false;
        
        // if the class is declared as <extern> we have to trust the implementor
        if ($sym->flags & SYM_FLAG_EXTERN)
          return true;
        
        $from = $sym->members->get('from', SYM_FN_NS);
        if ($from->is_none()) return false;
        
        $fsym = &$from->unwrap();
        return (bool) (!($fsym->flags & SYM_FLAG_PRIVATE) && 
                       !($fsym->flags & SYM_FLAG_PROTECTED) &&
                       $fsym->flags & SYM_FLAG_STATIC);
      
      default:
        assert(0);
    }
  }
  
  private function resolve_this_member($node, $mid)
  {
    // early binding does not make sense
    
  }
  
  private function resolve_self_member($node, $mid)
  {
    
  }
  
  private function resolve_super_member($node, $mid)
  {
    
  }
    
  /* ------------------------------------ */
  
  public function visit_module($node)
  {
    // leave module, but not the unit
    if ($this->scope instanceof ModuleScope)
      $this->scope->leave();
    
    assert($node->scope !== null);
    
    $this->enter($node->scope, $node->body);
    
    // re-enter previous module or unit
    $this->scope->enter();
  }
  
  public function visit_block($node)
  {
    assert($node->scope);
    $this->enter($node->scope, $node->body);
  }
  
  public function visit_class_decl($node)
  {
    assert($node->scope !== null);
    assert($node->symbol !== null);
    
    $this->scope = $node->scope;
    $this->cclass = $node->symbol;
    
    $this->resolve_members($node->symbol);
    
    $this->cclass = null;
  }
  
  public function visit_trait_decl($node)
  {
    // noop
  }
  
  public function visit_fn_decl($node)
  {
    assert($node->scope !== null);
    assert($node->symbol !== null);
    
    $this->enter_fn($node);
  }
  
  public function visit_fn_expr($node)
  {
    assert($node->scope !== null);
    $this->enter_fn($node);
  }
  
  public function visit_fn_params($params)
  {
    if (!$params) return;
    
    foreach ($params as $param) {
      assert(!($param instanceof ThisParam));
      
      if ($param->hint)
        $this->resolve_type($param->hint, TYC_HINT);
      
      $param->symbol->reachable = true;
    }
  }
  
  public function visit_var_decl($node)
  {
    foreach ($node->vars as $var) {
      if ($var->init)
        $this->visit($var->init);
      
      // mark variable as reachable
      $var->symbol->reachable = true;
    }
  }
  
  public function visit_enum_decl($node)
  {
    foreach ($node->vars as $var) {
      if ($var->init)
        $this->visit($var->init);
      
      // mark variable as reachable
      $var->symbol->reachable = true;
    }
  }
  
  public function visit_require_decl($node)
  {
    $this->visit($node->expr);
    $path = $node->expr->value;
    
    if (!$path || $path->kind !== VAL_KIND_STR) {
      Logger::error_at($node->loc, 'require path does not reduce \\');
      Logger::error('to a constant string');
    } else
      $this->process_require($path->data, $node->php, $node->loc);
  }
  
  public function visit_for_in_stmt($node)
  {
    $prev = $this->scope;
    $this->scope = $node->scope;
    $this->scope->enter();
    
    $vars = [];
    
    if ($node->lhs->key)
      $vars[] = $node->lhs->key;
    
    if ($node->lhs->arg)
      $vars[] = $node->lhs->arg;
    
    foreach ($vars as $var)
      $var->symbol->reachable = true;
    
    $this->visit($node->rhs);
    $this->visit($node->stmt);
    
    $this->scope->leave();
    $this->scope = $prev;
  }
  
  public function visit_for_stmt($node)
  {
    $prev = $this->scope;
    $this->scope = $node->scope;
    $this->scope->enter();
    
    $this->visit($node->init);
    $this->visit($node->test);
    $this->visit($node->each);
    $this->visit($node->stmt);
    
    $this->scope->leave();
    $this->scope = $prev;
  }
  
  public function visit_new_expr($node)
  {
    $expr = $node->name;
    
    if (!$this->resolve_type($expr, TYC_NEW)) {
      Logger::error_at($expr->loc, 'invalid type-name in new-expression');
    }
  }
  
  public function visit_cast_expr($node) 
  {
    $this->visit($node->expr);
    $type = $node->type;
    
    if (!$this->resolve_type($type, TYC_CAST)) {
      Logger::error_at($type->loc, 'only primitive types and classes with \\');
      Logger::error('a static `from` method can be used in a type-cast');
    }
  }
  
  public function visit_member_expr($node)
  {
    parent::visit_member_expr($node);
    
    // cast member to string
    if ($node->computed) {
      if (!$node->member->value ||
          $node->member->value->is_none())
        return; // must be resolved at runtime
      
      if (!$node->member->value->as_str()) 
        return; // error
    }
    
    if ($this->cclass) {
      $mid = null;
      
      if ($node->computed)        
        $mid = $node->member->value->data;
      else
        $mid = ident_to_str($node->member);
      
      if ($node->object instanceof SelfExpr)
        $this->resolve_self_member($node, $mid);
      elseif ($node->object instanceof ThisExpr)
        $this->resolve_this_member($node, $mid);
      elseif ($node->object instanceof SuperExpr)
        $this->resolve_super_member($node, $mid);
    }
  }
  
  public function visit_this_expr($node)
  {
    if (!$this->cclass)
      Logger::error_at($node->loc, '`this` outside of class or trait');
  }
  
  public function visit_super_expr($node)
  {
    if (!$this->cclass)
      Logger::error_at($node->loc, '`super` outside of class or trait');
  }
  
  public function visit_self_expr($node)
  {
    if (!$this->cclass)
      Logger::error_at($node->loc, '`self` outside of class or trait');
  }
  
  public function visit_name($node)
  {
    $res = $this->lookup_name($node);
    $this->process_lookup($node, $res);    
  }
  
  public function visit_ident($node)
  {
    $res = $this->lookup_ident($node);
    $this->process_lookup($node, $res);
  }
  
  public function visit_engine_const($node)
  {
    if ($node->value && $node->value->is_some())
      return;
    
    // must be late-bindings of __class__ or __method__
    switch ($node->type) {
      case T_CCLASS:
        $this->reduce_class_const($node);
        break;
      case T_CMETHOD:
        $this->reduce_method_const($node);
        break;
      default:
        assert(0);
    }
  }
}
