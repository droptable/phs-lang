<?php

namespace phs;

require_once 'utils.php';
require_once 'visitor.php';
require_once 'symbols.php';
require_once 'values.php';
require_once 'scope.php';
require_once 'lookup.php';
require_once 'collect.php';
require_once 'reduce.php';

use phs\ast\Node;
use phs\ast\Unit;
use phs\ast\Expr;
use phs\ast\Name;
use phs\ast\Ident;
use phs\ast\TypeId;
use phs\ast\UseAlias;
use phs\ast\UseUnpack;
use phs\ast\Param;
use phs\ast\ThisParam;
use phs\ast\RestParam;
use phs\ast\ClassDecl;
use phs\ast\IfaceDecl;
use phs\ast\TraitDecl;
use phs\ast\SelfExpr;
use phs\ast\ThisExpr;
use phs\ast\SuperExpr;
use phs\ast\DoStmt;
use phs\ast\ForStmt;
use phs\ast\ForInStmt;
use phs\ast\WhileStmt;
use phs\ast\SwitchStmt;

use phs\util\Set;
use phs\util\Map;
use phs\util\Cell;
use phs\util\Entry;

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
 * - executes <require>                                    [X]
 * - executes trait-usage in classes                       [X]
 * - checks interface-implementations (iface and abstract) [X]
 * - checks reachability                                   [X]
 * - checks accessibility (static)                         [X]
 */
class ResolveTask extends AutoVisitor implements Task
{  
  // mixin lookup-methods
  use Lookup;
  
  // mixin late-bindings for __class__ and __method__
  // @see "reduce.php"
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
  
  // @var array  loop-labels
  // to resolve break/continue with labels
  private $labels;
  
  // @var array  label stack
  private $lstack;
  
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
  public function run(Unit $unit)
  {
    $this->upath = dirname($unit->loc->file);
    $this->sunit = $unit->scope; 
    $this->fnstk = [];
    $this->labels = [];
    $this->lstack = [];
    
    $this->enter($this->sunit, $unit);
  }
  
  /* ------------------------------------ */
  
  /**
   * increments all active lables
   *
   */
  private function inc_labels()
  {
    foreach ($this->labels as &$level)
      ++$level;
  }
  
  /**
   * decrements all active labels
   *
   */
  private function dec_labels()
  {
    foreach ($this->labels as &$level)
      --$level;
  }
  
  /**
   * verifies a label break/continue
   *
   * @param  Node $node
   */
  private function verify_label($node)
  {
    $lid = ident_to_str($node->id);
    
    if (!isset ($this->labels[$lid])) {
      // note: the label exists and is reachable ...
      // checked in validate.php
      Logger::error_at($node->loc, 'cannot break/continue \\');
      Logger::error('label `%s` \\', $lid);
      Logger::error('because this label does not identify a loop \\');
      Logger::error('or switch statement');
    } else
      $node->level = $this->labels[$lid];
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
    array_push($this->lstack, $this->labels);
    
    $this->visit_fn_params($node->params);
    
    if ($node->body)
      $this->visit($node->body);
    
    $this->scope->leave();
    
    $this->scope = $prev;
    
    array_pop($this->fnstk);
    $this->labels = array_pop($this->lstack);
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
   * @param  Node   $node
   * @param  string $path
   * @param  boolean $php
   * @param  Location $loc
   */
  private function process_require($node, $path, $php, $loc)
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
    $this->sess->add_import($fsrc);
    
    if ($node)
      $node->source = $fsrc;
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
    
    return $this->resolve_lookup($node, $res, $ref);
  }
  
  /**
   * resolves a lookup
   *
   * @param  Node $node
   * @param  ScResult $res
   * @param  string $ref
   * @return boolean
   */
  private function resolve_lookup($node, $res, $ref)
  {
    // no symbol found
    if ($res->is_none() && !$res->is_priv())
      Logger::error_at($node->loc, 'access to undefined symbol `%s`', $ref);
    
    // private symbol "trap"
    elseif ($res->is_priv()) {
      $sym = &$res->unwrap();
      Logger::error_at($node->loc, 'access to private %s \\', $sym);
      Logger::error('from invalid context');
      Logger::error_at($sym->loc, 'declaration was here');
    }
    
    // error
    elseif ($res->is_error()) {
      Logger::error_at($node->loc, '[bug] error while looking up `%s`', $ref);
    }
    
    // found something useful
    else {
      $sym = &$res->unwrap();
      
      if (!$sym->reachable) {
        Logger::error_at($node->loc, 'access to undefined symbol `%s`', $ref);
        Logger::info_at($sym->loc, 'a variable with the name `%s` \\', $ref);
        Logger::info('gets defined here but is not yet accessible');
        Logger::info('try to move variable-declarations at the top of \\');
        Logger::info('the file or blocks {...} to avoid errors like this');
      } else {
        // there is a better way to do this
        // but it works for now
        if ($sym->flags & SYM_FLAG_PROTECTED) {
          $cls = $this->cclass;
          
          while ($cls) {
            if ($cls->members->contains($sym))
              goto sok;
            
            if ($cls->super)
              $cls = $cls->super->symbol;
            else
              break;
          }
          
          Logger::error_at($node->loc, 'access to protected %s \\', $sym);
          Logger::error('from invalid context');
          goto err;
        }
        
        sok:
                
        $node->symbol = $sym;
        return true;
      }
    }
    
    err:
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
    if (!($sym->flags & SYM_FLAG_EXTERN))
      foreach ($sym->members->iter(SYM_FN_NS) as $fn) {
        if ($fn instanceof FnSymbol && 
            $fn->flags & SYM_FLAG_ABSTRACT) {
          $sym->flags |= SYM_FLAG_ABSTRACT;
          break;
        }
      }
      
    if (($sym->flags & SYM_FLAG_ABSTRACT) &&
        ($sym->flags & SYM_FLAG_FINAL)) {
      Logger::error_at($sym->loc, '%s cannot be \\', $sym);
      Logger::error('abstract and final at the same time');
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
    if (!$csym->super) {
      if ($this->sess->robj)
        $csym->members->super = $this->sess->robj->members;
      // else: no super-class
    } else {
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
          Logger::info_at($use->loc, 'usage instead resolved to %s', $sym);
          Logger::info_at($sym->loc, 'declared here');
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
        $res = $use->members->rec($trait->orig);
        
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
   * @param  ClassSymbol|IfaceSymbol $csym
   */
  private function resolve_members($csym)
  {
    $csym->members->enter();
    
    foreach ($csym->members->iter() as $sym)
      if ($sym instanceof VarSymbol) {
        if ($sym->node->init)
          $this->visit($sym->node->init);
      } else
        $this->enter_fn($sym->node);
    
    $this->verify_impls($csym); 
    $csym->members->leave();
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
    elseif ($node instanceof SelfExpr) {
      if (!$this->cclass) {
        Logger::error_at($node->loc, 'cannot use `self` \\');
        Logger::error('as type-name outside of class/trait');
        return false;
      }
      
      $sym = $this->cclass;
      goto chk;
    } else {
      Logger::error_at($node->loc, 'invalid type-name');
      return false;
    }
    
    if (!$this->process_lookup($node, $res))
      return false;
    
    $sym = &$res->unwrap();
    
    // basic test
    if ($sym->kind !== SYM_KIND_CLASS &&
        $sym->kind !== SYM_KIND_IFACE) {
      Logger::error_at($node->loc, '%s is not a valid type', $sym);
      return false;
    }
    
    chk:
    switch ($tyc) {
      case TYC_ANY:
      case TYC_HINT:
        // no special restrictions
        return true;
      
      case TYC_NEW: 
        // must be a class
        if ($sym->kind !== SYM_KIND_CLASS) {
          Logger::error_at($node->loc, 'cannot use %s in new-expression', $sym);
          return false;
        }
        
        // must not be abstract
        if ($sym->flags & SYM_FLAG_ABSTRACT) {
          Logger::error_at($node->loc, 'cannot use abstract %s \\', $sym);
          Logger::error('in new-expression');
          return false;
        }
        
        if (($sym->flags & SYM_FLAG_INCOMPLETE) &&
            !($sym->flags & SYM_FLAG_EXTERN))
          Logger::error_at($node->loc, 'access to incomplete %s', $sym);
        
        return true;
               
      case TYC_CAST:   
        if ($sym->kind !== SYM_KIND_CLASS) {
          Logger::error_at($node->loc, 'cannot use %s in cast-expression', $sym);
          return false;
        }
             
        // if the class is declared as <extern> without extern methods
        // we have to trust the implementor
        if (($sym->flags & SYM_FLAG_EXTERN) && 
            ($sym->flags & SYM_FLAG_INCOMPLETE))
          return true;
        
        // must have a public static `from` method
        $from = $sym->members->get('from', SYM_FN_NS);
        if ($from->is_none())
          goto fse;
        
        $fsym = &$from->unwrap();
        if ($fsym->flags & SYM_FLAG_PRIVATE ||
            $fsym->flags & SYM_FLAG_PROTECTED)
          goto fse;
        
        if ($fsym->flags & SYM_FLAG_STATIC)
          return true;
        
        Logger::error_at($fsym->loc, '%s method `from` must be \\', $sym);
        Logger::error('declared static to be used in cast-expressions');
        Logger::info_at($node->loc, 'used as cast-type here');
        return false;
        
        fse:
        Logger::error_at($node->loc, '%s must have a \\', $sym);
        Logger::error('public static `from` method to be used \\');
        Logger::error('in cast-expressions');
        return false;
        
      default:
        assert(0);
    }
  }
  
  private function resolve_this_member($node, $mid)
  {
    $res = $this->cclass->members->get($mid);
    
    if ($res->is_none())
      return; // maybe dynamic
    
    $sym = &$res->unwrap();
    
    if ($sym->flags & SYM_FLAG_STATIC) {
      Logger::error_at($node->loc, 'access to static %s \\', $sym);  
      Logger::error('from object-context (this)');
    } else {
      $node->symbol = $sym;
      $node->object->symbol = $this->cclass;
    }
  }
  
  private function resolve_self_member($node, $mid)
  {
    $res = $this->cclass->members->get($mid);
    
    if ($res->is_none()) {
      Logger::error_at($node->loc, 'access to undefined static member \\');
      Logger::error('`%s` of %s', $mid, $this->cclass);
    } else {
      $sym = &$res->unwrap();
      
      if (!($sym->flags & SYM_FLAG_STATIC)) {
        Logger::error_at($node->loc, 'access to non-static %s \\', $sym);
        Logger::error('from class-context (self)');
      } else {
        $node->symbol = $sym;
        $node->object->symbol = $this->cclass;
      }
    }
  }
  
  private function resolve_super_member($node, $mid)
  {
    // how super works:
    // 1. super can access any method (static or not)
    // 2. super cannot access non-static variables
    
    $mscp = $this->cclass->members;
    
    if (!$mscp->super) {
      Logger::error_at($node->loc, 'cannot use `super` in %s \\', $this->cclass);
      Logger::error('without parent class');
    } else {
      $res = $mscp->super->get($mid);
      $hst = $mscp->super->host;
      
      if ($res->is_priv()) {
        Logger::error_at($node->loc, 'access to private member \\');
        Logger::error('`%s` of %s \\', $mid, $hst);
        Logger::error('from parent-context (super)');
      } elseif ($res->is_none()) {
        Logger::error_at($node->loc, 'access to undefined member \\');
        Logger::error('`%s` of %s \\', $mid, $hst);
        Logger::error('from parent-context (super)');
      } else {
        $sym = &$res->unwrap();
        
        if ($sym->kind !== SYM_KIND_FN && !($sym->flags & SYM_FLAG_STATIC)) {
          Logger::error_at($node->loc, 'cannot access non-static \\');
          Logger::error('%s from parent-context (super)', $sym);
          Logger::info('only methods and static variables of \\');
          Logger::info('the parent class can be accessed via `super`');
        } else {
          $node->symbol = $sym;
          $node->object->symbol = $mscp->host;
        }
      }
    }
  }
  
  /**
   * verifies implementations from interfaces and super-classes
   *
   * @param  ClassSymbol|IfaceSymbol $csym
   */
  private function verify_impls($csym)
  {
    $impl = new SymbolMap;
    $curr = $csym;
    $clss = $csym instanceof ClassSymbol;
    
    // collect abstract symbols
    for (;;) {
      // collect symbols from interfaces
      if ($curr->ifaces)
        foreach ($curr->ifaces as $iface)
          if ($iface->symbol) {
            $iface = $iface->symbol;
            foreach ($iface->members->iter() as $isym)
              $impl->put($isym);
          }
      
      if (!$clss) break;
       
      $curr = $curr->super;
            
      if (!$curr || !$curr->symbol)
        break;
      
      $curr = $curr->symbol;
      
      // collect abstract symbols from super-class      
      if ($curr->flags & SYM_FLAG_ABSTRACT)
        foreach ($curr->members->iter() as $ssym)
          if ($ssym->flags & SYM_FLAG_ABSTRACT)
            $impl->put($ssym);
    }
        
    // resolve implementations
    foreach ($impl->iter() as $isym)
      $this->verify_impl($csym, $isym);
  }
  
  /**
   * verifies a implementation
   *
   * @param  ClassSymbol $csym
   * @param  Symbol $isym
   * @return boolean
   */
  private function verify_impl($csym, $isym)
  {
    $cres = $csym->members->rec($isym->id, $isym->ns);
    
    if ($cres->is_none() || $cres->unwrap() === $isym)
      // mark class as abstract
      $csym->flags |= SYM_FLAG_ABSTRACT;
    else {
      $cmem = &$cres->unwrap();
      
      if ($cmem->flags !== ($isym->flags ^ SYM_FLAG_ABSTRACT) &&
          $cmem->flags !== $isym->flags /* <- declared abstract */) {
        // access-flag can be loosened
        if (($isym->flags & SYM_FLAG_PROTECTED) &&
            ($cmem->flags & SYM_FLAG_PUBLIC)) {
          $iflags = $isym->flags ^ SYM_FLAG_PROTECTED;
          $cflags = $cmem->flags ^ SYM_FLAG_PUBLIC;
          
          if ($cflags === ($iflags ^ SYM_FLAG_ABSTRACT) ||
              $cflags === $iflags /* <- declared abstract */)
            goto nxt;
        }
        
        Logger::error_at($cmem->loc, 'modifiers of \\');
        Logger::error('%s are incompatible with %s', $cmem, $isym);
        Logger::info_at($isym->loc, 'declaration of %s was here', $isym);
        Logger::info('modifiers of %s: %s', $cmem, sym_flags_to_str($cmem->flags));
        Logger::info('modifiers of %s: %s', $isym, sym_flags_to_str($isym->flags));
        return false;
      }
      
      // check params
      nxt:
        
      $cparam = null;
      $iparam = null;
      $passed = true;
      
      if (count($cmem->params) !== count($isym->params)) {
        Logger::error_at($cmem->loc, 'wrong parameter-count');
        Logger::info_at($isym->loc, 'declaration was here');
      }
        
      foreach ($cmem->params as $pidx => $cparam) {
        if (!isset ($isym->params[$pidx])) {
          Logger::error_at($cparam->loc, '%s is not \\', $cparam);
          Logger::error('declared in %s', $isym);
          $passed = false;
          break;
        }
        
        // fetch interface param at the same position
        $iparam = $isym->params[$pidx];
                
        if ($cparam->flags !== $iparam->flags ||
            $cparam->rest !== $iparam->rest ||
            $cparam->opt !== $iparam->opt) {
          Logger::error_at($cparam->loc, '%s \\', $cparam);
          Logger::error('must be compatible with the declaration in %s', $isym);
          Logger::info_at($iparam->loc, 'declaration was here');
          $passed = false;
        }
        
        if ($cparam->hint || $iparam->hint) {
          if (!$iparam->hint) {
            Logger::error_at($cparam->loc, 'cannot augment \\');
            Logger::error('%s with an type-hint', $cparam);
            Logger::info_at($iparam->loc, 'declaration was here');
            $passed = false;
          } elseif (!$cparam->hint) {
            Logger::error_at($cparam->loc, 'missing type-hint');
            Logger::info_at($iparam->loc, 'declaration was here');
          } else {
            // both hints must be the same (type-id or symbol)
            if (!$this->verify_hint($cparam->hint, $iparam->hint))
              $passed = false;
          }
        }
      }
      
      return $passed;
    }
    
    return true;
  }
  
  /**
   * verifies two parameter type-hints
   *
   * @param  TypeId|Name $hint1
   * @param  TypeId|Name $hint2
   * @return boolean
   */
  public function verify_hint($hint1, $hint2) 
  {
    $h1_is_type = $hint1 instanceof TypeId;
    $h2_is_type = $hint2 instanceof TypeId;
    
    if ($h1_is_type) {
      if (!$h2_is_type || $hint1->type !== $hint2->type) 
        goto err;
    } else {
      if ($h2_is_type || $hint1->symbol !== $hint2->symbol)
        goto err;
    }
    
    return true;
    
    err:
    Logger::error_at($hint1->loc, 'incompatible \\');
    Logger::error('parameter type-hint');
    Logger::info_at($hint2->loc, 'declaration was here');
    return false;
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
  
  /**
   * Visitor#visit_block()
   *
   * @param  Node  $node
   */
  public function visit_block($node)
  {
    assert($node->scope);
    $this->enter($node->scope, $node->body);
  }  

  /**
   * Visitor#visit_class_decl()
   *
   * @param  Node  $node
   */
  public function visit_class_decl($node)
  {
    assert($node->scope !== null);
    assert($node->symbol !== null);
    
    $prev = $this->scope;
    $this->scope = $node->scope;
    
    $this->cclass = $node->symbol;
    
    $this->resolve_members($node->symbol);
    
    $this->cclass = null;
    $this->scope = $prev;
  }  

  /**
   * Visitor#visit_trait_decl()
   *
   * @param  Node  $node
   */
  public function visit_trait_decl($node)
  {
    // noop
  }
  
  /**
   * Visitor#visit_iface_decl()
   *
   * @param  Node  $node
   */
  public function visit_iface_decl($node)
  {
    assert($node->scope !== null);
    assert($node->symbol !== null);
    
    $prev = $this->scope;
    $this->scope = $node->scope;
    
    $this->resolve_members($node->symbol);
    
    $this->scope = $prev;
  }  

  /**
   * Visitor#visit_fn_decl()
   *
   * @param  Node  $node
   */
  public function visit_fn_decl($node)
  {
    assert($node->scope !== null);
    assert($node->symbol !== null);
    
    $this->enter_fn($node);
  }  

  /**
   * Visitor#visit_fn_expr()
   *
   * @param  Node  $node
   */
  public function visit_fn_expr($node)
  {
    assert($node->scope !== null);
    $this->enter_fn($node);
  }  

  /**
   * Visitor#visit_fn_params()
   *
   * @param  Node  $node
   */
  public function visit_fn_params($params)
  {
    if (!$params) return;
    
    foreach ($params as $param) {
      if ($param->hint)
        $this->resolve_type($param->hint, TYC_HINT);
      
      $param->symbol->reachable = true;
    }
  }  

  /**
   * Visitor#visit_var_decl()
   *
   * @param  Node  $node
   */
  public function visit_var_decl($node)
  {
    foreach ($node->vars as $var) {
      if ($var->init) {
        $this->visit($var->init);
        $var->symbol->assign = true;
        
        // for optimizations
        if ($var->init->value &&
            $var->init->value->is_const() && 
            $var->symbol->flags & SYM_FLAG_CONST)
          $var->symbol->value = $var->init->value;
      }
      
      // mark variable as reachable
      $var->symbol->reachable = true;
    }
  }
  
  /**
   * Visitor#visit_var_list()
   *
   * @param  Node  $node
   */
  public function visit_var_list($node)
  {
    foreach ($node->vars as $var)
      // mark variable as reachable
      $var->symbol->reachable = true;
    
    $this->visit($node->expr);
  }

  /**
   * Visitor#visit_enum_decl()
   *
   * @param  Node  $node
   */
  public function visit_enum_decl($node)
  {
    foreach ($node->vars as $var) {
      if ($var->init)
        $this->visit($var->init);
      
      // mark variable as reachable
      $var->symbol->reachable = true;
      $var->symbol->assign = true;
    }
  }  

  /**
   * Visitor#visit_require_decl()
   *
   * @param  Node  $node
   */
  public function visit_require_decl($node)
  {
    $this->visit($node->expr);
    $path = $node->expr->value;
    
    if (!$path || $path->kind !== VAL_KIND_STR) {
      Logger::error_at($node->loc, 'require path does not reduce \\');
      Logger::error('to a constant string');
    } else
      $this->process_require($node, $path->data, $node->php, $node->loc);
  }
  
  /**
   * Visitor#visit_label_decl()
   *
   * @param  Node $node
   */
  public function visit_label_decl($node) 
  {
    $stmt = $node->stmt;
    
    if ($stmt instanceof DoStmt ||
        $stmt instanceof ForStmt ||
        $stmt instanceof ForInStmt ||
        $stmt instanceof WhileStmt ||
        $stmt instanceof SwitchStmt) {
      $lid = ident_to_str($node->id);
      $this->labels[$lid] = 0;
    }
    
    $this->visit($stmt);
  }
  
  /**
   * Visitpr#visit_do_stmt()
   *
   * @param  Node $node
   */
  public function visit_do_stmt($node) 
  {
    $this->inc_labels();
    parent::visit_do_stmt($node);
    $this->dec_labels();
  }

  /**
   * Visitor#visit_for_in_stmt()
   *
   * @param  Node  $node
   */
  public function visit_for_in_stmt($node)
  {
    $this->inc_labels();
    
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
    
    $this->dec_labels();
  }
  
  /**
   * Visitor#visit_for_stmt()
   *
   * @param  Node  $node
   */
  public function visit_for_stmt($node)
  {
    $this->inc_labels();
    
    $prev = $this->scope;
    $this->scope = $node->scope;
    $this->scope->enter();
    
    $this->visit($node->init);
    $this->visit($node->test);
    $this->visit($node->each);
    $this->visit($node->stmt);
    
    $this->scope->leave();
    $this->scope = $prev;
    
    $this->dec_labels();
  }
  
  /**
   * Visitor#visit_break_stmt()
   *
   * @param  Node  $node
   */
  public function visit_break_stmt($node) 
  {
    if ($node->id)
      $this->verify_label($node);
  }
  
  /**
   * Visitor#visit_continue_stmt()
   *
   * @param  Node  $node
   */
  public function visit_continue_stmt($node) 
  {
    if ($node->id)
      $this->verify_label($node);
  }
  
  /**
   * Visitor#visit_while_stmt()
   *
   * @param  Node $node
   */
  public function visit_while_stmt($node) 
  {
    $this->inc_labels();
    parent::visit_while_stmt($node);
    $this->dec_labels();  
  }
  
  /**
   * Visitor#visit_switch_stmt()
   *
   * @param  Node $node
   */
  public function visit_switch_stmt($node) 
  {
    $this->inc_labels();
    parent::visit_switch_stmt($node);
    $this->dec_labels();
  }
  
  /**
   * Visitor#visit_php_stmt()
   *
   * @param  Node $node
   */
  public function visit_php_stmt($node)
  {
    if ($node->usage)
      foreach ($node->usage as $usage)
        foreach ($usage->items as $item) {
          // lookup ident
          $res = $this->lookup_ident($item->id);
          if ($this->process_lookup($item->id, $res)) {
            $sym = &$res->unwrap();
            if (!($sym instanceof VarSymbol) &&
                !($sym instanceof FnSymbol && $sym->nested)) {
              Logger::error_at($item->loc, 'php-statements can only \\');
              Logger::error('use variables or local functions');
              Logger::info_at($item->loc, 'attempt to use %s', $sym);
              Logger::info('it would be possible to use all kinds of \\');
              Logger::info('symbols in a php-use statement, but \\');
              Logger::info('it is currently not possible to generate \\');
              Logger::info('php-code with the correct symbol-names in place');
            }
          }
        }
  }
  
  /**
   * Visitor#visit_assign_expr()
   *
   * @param  Node  $node
   */
  public function visit_assign_expr($node)
  {
    $this->visit($node->left);
    $this->visit($node->right);
    
    if ($node->left->symbol) {
      $lsym = $node->left->symbol;
      
      if (!($lsym instanceof VarSymbol))
        Logger::error_at($node->loc, 'cannot assign a value to %s', $lsym);
      else {
        if (($lsym->flags & SYM_FLAG_CONST) && $lsym->assign) {
          if ($node->op->type === T_ASSIGN)
            Logger::error_at($node->loc, 'cannot re-assign to constant %s', $lsym);
          else
            Logger::error_at($node->loc, 'cannot update constant %s', $lsym);
        }
      
        $lsym->assign = true;
      }
    }
  }    

  /**
   * Visitor#visit_new_expr()
   *
   * @param  Node  $node
   */
  public function visit_new_expr($node)
  {
    $name = $node->name;
    $this->visit($name);
    
    if ((($name instanceof Name ||
          $name instanceof Ident) &&
         !($name->symbol instanceof VarSymbol)) ||
        $name instanceof TypeId ||
        $name instanceof SelfExpr)
      $this->resolve_type($name, TYC_NEW);
    
    // else: name is a expression
    
    $this->visit_fn_args($node->args);
  }  

  /**
   * Visitor#visit_cast_expr()
   *
   * @param  Node  $node
   */
  public function visit_cast_expr($node) 
  {
    $this->visit($node->expr);
    $this->resolve_type($node->type, TYC_CAST);
  }  

  /**
   * Visitor#visit_call_expr()
   *
   * @param  Node  $node
   */
  public function visit_call_expr($node)
  {
    $this->visit($node->callee);
    $this->visit_fn_args($node->args);
    
    if ($node->callee instanceof SuperExpr) {
      if (!$this->cclass) return; // already seen an error
      
      $curfn = end($this->fnstk);
      if ($curfn === null || $curfn->id !== '<ctor>') {
        Logger::error_at($node->loc, 'super() can only be called \\');
        Logger::error('directly in constructors');
      } else {
        
        // TODO: implement default-constructors?
        
        $super = $this->cclass->members->super;
        $found = false;
        $sctor = null;
        
        while ($super) {
          if ($super->ctor) {
            $found = true;
            $sctor = $super->ctor;
            break;
          }
          
          $super = $super->super;
        }
        
        if (!$found) {
          Logger::error_at($node->loc, 'cannot forward constructor-call \\');
          Logger::error('via super(), because no parent-class has a own \\');
          Logger::error('constructor');
        } elseif ($sctor->flags & SYM_FLAG_PRIVATE) {
          Logger::error_at($node->loc, 'cannot forward constructor-call \\');
          Logger::error('via super() to %s, because \\', $super->symbol);
          Logger::error('is was declared private');
          Logger::info_at($sctor->loc, 'resolved parent-constructor is here');
        } else
          $node->callee->symbol = $sctor;
      }
    }
  }  

  /**
   * Visitor#visit_member_expr()
   *
   * @param  Node  $node
   */
  public function visit_member_expr($node)
  {
    $this->visit($node->object);
        
    // cast member to string
    if ($node->computed) {
      $this->visit($node->member);
      
      if (!$node->member->value ||
          $node->member->value->is_none() ||
          $node->member->value->is_undef())
        return; // must be resolved at runtime
      
      if (!$node->member->value->as_str()) 
        return; // error
    }
    
    $mid = null;
    
    if ($node->computed)        
      $mid = $node->member->value->data;
    else
      $mid = ident_to_str($node->member);
    
    if ($this->cclass) {
      if ($node->object instanceof SelfExpr)
        $this->resolve_self_member($node, $mid);
      elseif ($node->object instanceof ThisExpr)
        $this->resolve_this_member($node, $mid);
      elseif ($node->object instanceof SuperExpr)
        $this->resolve_super_member($node, $mid);
      else
        goto smc;
      
      goto out;
    }
    
    smc:
    if (($node->object instanceof Name || 
         $node->object instanceof Ident) && 
        $node->object->symbol !== null) {
      
      $osm = $node->object->symbol;
      
      if ($osm->kind === SYM_KIND_TRAIT ||
          $osm->kind === SYM_KIND_IFACE) {
        Logger::error_at($node->loc, 'cannot access members of \\');
        Logger::error('%s directly', $osm);
      } elseif ($osm->kind === SYM_KIND_CLASS) {
        // skip incomplete classes
        if ($osm->flags & SYM_FLAG_INCOMPLETE)
          goto out;
        
        // static member check
        $res = $osm->members->get($mid);
        $ref = $osm->id . '.' . $mid;
        
        if ($this->resolve_lookup($node, $res, $ref)) {
          $sym = &$res->unwrap();
          
          if (!($sym->flags & SYM_FLAG_STATIC)) {
            Logger::error_at($node->loc, 'access to non-static %s \\', $sym);
            Logger::error('from invalid context');
          }
        }
      } else {
        // TODO: implement flow-based member lookups
      }
    }
    
    out:
    return;
  }
  
  /**
   * Visitor#visit_offset_expr()
   *
   * @param  Node  $node
   */
  public function visit_offset_expr($node)
  {
    $this->visit($node->object);
    $this->visit($node->offset);
  }  

  /**
   * Visitor#visit_this_expr()
   *
   * @param  Node  $node
   */
  public function visit_this_expr($node)
  {
    if (!$this->cclass)
      Logger::error_at($node->loc, '`this` outside of class or trait');
    
    $node->symbol = $this->cclass;
  }  

  /**
   * Visitor#visit_super_expr()
   *
   * @param  Node  $node
   */
  public function visit_super_expr($node)
  {
    if (!$this->cclass)
      Logger::error_at($node->loc, '`super` outside of class or trait');
    
    if ($this->cclass && $this->cclass->super)
      $node->symbol = $this->cclass->super->symbol;
    else
      $node->symbol = $this->cclass;
  }  

  /**
   * Visitor#visit_self_expr()
   *
   * @param  Node  $node
   */
  public function visit_self_expr($node)
  {
    if (!$this->cclass)
      Logger::error_at($node->loc, '`self` outside of class or trait');
    
    $node->symbol = $this->cclass;
  }
  
  /**
   * Visitor#visit_name()
   *
   * @param  Node  $node
   */
  public function visit_name($node)
  {
    $res = $this->lookup_name($node);
    $this->process_lookup($node, $res);
  }
  
  /**
   * Visitor#visit_ident()
   *
   * @param  Node  $node
   */
  public function visit_ident($node)
  {
    $res = $this->lookup_ident($node);
    $this->process_lookup($node, $res);
  }
  
  /**
   * Visitor#visit_engine_const()
   *
   * @param  Node  $node
   */
  public function visit_engine_const($node)
  {
    if ($node->value && $node->value->is_some())
      return;
    
    // late-bindings of __class__ or __method__
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
