<?php

namespace phs;

require_once 'utils.php';
require_once 'value.php';
require_once 'walker.php';
require_once 'symbol.php';

use phs\ast\Unit;
use phs\ast\Name;

class Analyzer extends Walker 
{
  // context
  private $ctx;
  
  // the compiler
  private $com;
  
  // current scope
  private $scope;
  
  // scope stack
  private $sstack;
  
  // module (if any)
  private $module;
  
  // module stack
  private $mstack;
  
  // flags from nested-mods
  private $flags;
  
  // flag stack
  private $fstack;
  
  // reducer used to handle constant-expressions
  private $rdc;
  
  // validation state
  private $valid;
  
  // a value carried around to reduce constant-expressions
  private $value;
  
  // pass 1/2 for class members
  private $pass;
  
  // for validation of return/break/continue
  private $infn = 0;
  private $inloop = 0;
  
  // access flags
  const
    ACC_READ = 1,
    ACC_WRITE = 2
  ;
  
  // access flag for assignments
  private $access;
  
  // access location
  private $accloc;
  
  // access stack
  private $astack;
  
  // anonymus function id-counter
  private static $anon_fid = 0;
  
  // require'd paths
  private static $require_paths = [];
  
  /**
   * constructor
   * 
   * @param Context $ctx
   */
  public function __construct(Context $ctx, Compiler $com)
  {
    parent::__construct($ctx);
    $this->ctx = $ctx;
    $this->com = $com;
  }
  
  /**
   * analyze a unit
   * 
   * @param  Unit   $unit
   */
  public function analyze(Unit $unit)
  {
    $this->scope = $this->ctx->get_scope();
    $this->sstack = [];
    $this->module = null;
    $this->mstack = [];
    $this->flags = SYM_FLAG_NONE;
    $this->fstack = [];
    $this->valid = true;
    $this->pass = 0;
    $this->access = self::ACC_READ;
    $this->accloc = $unit->loc;
    $this->astack = [];
    
    $this->walk($unit);
  }
  
  /* ------------------------------------ */
  
  /**
   * enter a new scope
   * 
   * @param  boolean $ext extend (make current scope the parent)
   */
  protected function enter_scope($ext = true)
  {
    array_push($this->sstack, $this->scope);
    $new_scope = new Scope($ext ? $this->scope : null);
    $this->scope = $new_scope;
  }
  
  /**
   * leave the current scope
   * 
   */
  protected function leave_scope()
  {
    $this->scope = array_pop($this->sstack);
    assert($this->scope instanceof Scope);
  }
  
  /* ------------------------------------ */
  
  /**
   * handles a import (use)
   * 
   * @param  Node $item
   * @return boolean
   */
  protected function handle_import(Module $base, $item)
  {    
    switch ($item->kind()) {
      case 'name':
        return $this->fetch_import($base, $item, true);
      case 'use_alias':
        return $this->fetch_import($base, $item->name, true, ident_to_str($item->alias));
      case 'use_unpack':
        if (!$this->fetch_import($item->base, false))
          return false;
        
        $base = $base->fetch(name_to_stra($item->base));
        
        foreach ($item->items as $item)
          if (!$this->handle_import($base, $item))
            return false;
          
        return true;
    }
  }
  
  /**
   * fetches a import (use)
   * 
   * @param  Module $base the base module where to look at
   * @param  Node $name the name of the imported lib
   * @param  boolean $add  add symbol to the symtable
   * @param  string $id a name for the symtable
   * @return boolean
   */
  protected function fetch_import(Module $base, Name $name, $add, $id = null) 
  {
    $parts = name_to_stra($name);
    $last = array_pop($parts);
    
    if ($add && !$id)
      $id = $last;
    
    if ($parts)
      $base = $base->fetch($parts);
    
    if ($base->has_child($last)) {
      // import a module
      if (!$add) return true; 
      
      // add symbol to scope
      $sym = new ModuleRef($id, $base->get_child($last), $name, $name->loc);
      return $this->add_symbol($id, $sym);
    }
    
    if (!$base->has($last)) {
      // unknown import, this is a job for the resolver
      $this->error_at($name->loc, ERR_ERROR, 'unknown import `%s` from module %s', $last, $base->path());
      
      // TODO: allow unknown imports?
      return false;
      
      /*
      // import a module
      if (!$add) return true;
      
      $this->error_at($name->loc, ERR_INFO, 'assuming <module> (default assumption)');
      
      // add symbol to scope
      $sym = new ModuleRef($id, $base->get_child($last), $name, $name->loc, REF_WEAK);      
      return $this->add_symbol($id, $sym);
      */
    }
    
    if (!$add) return true;
    
    $sym = $base->get($last);
    $ref = SymbolRef::from($id, $sym, $name, $name->loc);
    
    // TODO: remove const/final flags?
    
    return $this->add_symbol($id, $ref);
  }
  
  /* ------------------------------------ */
  
  protected function add_symbol($id, $sym)
  {
    // apply current flags
    $sym->flags |= $this->flags;
    
    // get kind for error-messages
    $kind = symkind_to_str($sym->kind);
    
    # print "adding symbol $id\n";
    $cur = $this->scope->get($id, false, null, true);
    
    if (!$cur) {
      # print "no previous entry, adding it!\n";
      // simply add it
      $this->scope->add($id, $sym);
      return true;
    }
    
    // prev symbol was weak and can be replaced.
    // ignore all other flags
    if ($cur->flags & SYM_FLAG_WEAK) {
      // the prev symbol must not be dropped in this case,
      // just forget about it
      $this->scope->set($id, $sym);
      return true;
    }
    
    assert(!($sym->flags & SYM_FLAG_WEAK));
    
    if ($cur instanceof SymRef) {
      $this->error_at($sym->loc, ERR_ERROR, '%s %s collides with a referenced symbol', $kind, $sym->name);
      $this->error_at($cur->loc, ERR_ERROR, 'reference was here');
      return false;
    }
    
    $ninc = !!($sym->flags & SYM_FLAG_INCOMPLETE);
    $pinc = !!($cur->flags & SYM_FLAG_INCOMPLETE);
    
    if (($ninc || $pinc) && $cur->kind !== $sym->kind) {
      // incomplete symbols can only replace symbols of the same kind
      $this->error_at($sym->loc, ERR_ERROR, 'type mismatch (incomplete symbol)');
      return false;
    }
    
    if (($ninc && !$pinc) || ($ninc && $pinc)) {
      // no need to add it again
      # print "skipping incomplete symbol\n";
      return false;
    }
    
    if ($pinc && !$ninc) {
      // replace symbol
      # print "replacing previous incomplete symbol\n";
      $this->scope->set($id, $sym);
      return true;
    }
     
    // TODO: if kind is a class, check base class and interfaces as well   
    if ($cur->flags & SYM_FLAG_FINAL) {
      // whops, not allowed
      $this->error_at($sym->loc, ERR_ERROR, '%s `%s` hides a final symbol in this scope-chain', $kind, $sym->name);
      $this->error_at($cur->loc, ERR_ERROR, 'previous declaration was here');
      return false;
    }
    
    // check again, but now only in the same scope
    $cur = $this->scope->get($id, false, null, false); // TODO: bad api
    # print "checking same scope\n";
    
    if (!$cur) {
      // prev symbol is not directly in the same scope
      # print "symbol is not in the same scope, adding it!\n";
      $this->scope->add($id, $sym);
      return true;
    }
    
    if ($cur->flags & SYM_FLAG_CONST) {
      // same as final, but only applies in the same scope
      $this->error_at($sym->loc, ERR_ERROR, '%s `%s` overrides a constant symbol in the same scope', $kind, $sym->name);
      $this->error_at($cur->loc, ERR_ERROR, 'previous declaration was here');
      return false;
    }
    
    # print "replacing previous symbol\n";
    
    // mark the previous symbol as unreachable
    $this->scope->drop($id, $cur);
    
    // replace previous symbol
    $this->scope->set($id, $sym);
    return true;
  }
  
  /* ------------------------------------ */
  
  protected function enter_module($node)
  {
    $name = $node->name;
    $rmod = null;
    
    if ($name === null) {
      // switch to global scope and root module
      array_push($this->mstack, $this->module);
      array_push($this->sstack, $this->scope);
      $this->module = $this->ctx->get_module();
      $this->scope = $this->ctx->get_scope();
    } else {
      if ($name->root || !$this->module)
        // use root module
        $rmod = $this->ctx->get_module();
      else
        // use current module
        $rmod = $this->module;
            
      $rmod = $rmod->fetch(name_to_stra($name)); 
      
      array_push($this->mstack, $this->module);
      array_push($this->sstack, $this->scope);
      $this->module = $rmod;
      $this->scope = $rmod;         
    }
  }
  
  protected function leave_module($node) 
  {
    // back to prev module
    $this->module = array_pop($this->mstack);
    
    // back to prev scope
    $this->scope = array_pop($this->sstack);
  }
  
  protected function visit_use_decl($node)
  {
    // this function is recursive
    // TODO: base must be checked, because the import could
    // be relative to a already imported module
    // e.g.: use foo::bar; use bar::baz;
    $base = $this->ctx->get_module();
    return $this->handle_import($base, $node->item);
  }
  
  protected function visit_enum_decl($node) 
  {
    if ($node->members === null)
      return; // ignore it
    
    $flags = SYM_FLAG_CONST | SYM_FLAG_FINAL;
    
    if ($node->mods) {
      if (!$this->check_mods($node->mods, false, false))
        return $this->drop();
      
      $flags = mods_to_symflags($node->mods, $flags);
    }
    
    $base = 0;
    
    foreach ($node->members as $member) {
      if ($member->dest->kind() !== 'ident') {
        $this->error_at($member->loc, ERR_ERROR, 'destructors are not allowed in enum-declarations');
        continue; 
      }
      
      $id = ident_to_str($member->dest);
      $val = null;
      
      if ($member->init !== null) {
        $val = $this->handle_expr($member->init);
        
        if ($val->kind === VAL_KIND_UNKNOWN) {
          $this->error_at($member->init->loc, ERR_ERROR, 'enum member-initalizer must be reducible to constant value');
          continue;
        }
        
        if ($val->kind !== VAL_KIND_LNUM) {
          $this->error_at($member->init->loc, ERR_ERROR, 'enum members must be integers');
          continue;
        }
        
        $base = $val->value + 1;
      } else
        $val = new Value(VAL_KIND_LNUM, $base++);
        
      $sym = new VarSym($id, $val, $flags, $member->loc);      
      $this->add_symbol($id, $sym);
    }
  }
  
  protected function enter_iface_decl($node)
  {
    $flags = SYM_FLAG_CONST;
        
    if ($node->members === null)
      $flags |= SYM_FLAG_INCOMPLETE;
    else
      // check all members (each must be incomplete or without values)
      if (!$this->check_iface_members($node->members))
        return $this->drop();
    
    $iid = ident_to_str($node->id);
    $sym = new IfaceSym($iid, $flags, $node->loc);
    
    if (!$this->add_symbol($iid, $sym))
      return $this->drop();
    
    array_push($this->sstack, $this->scope);
    $node->scope = new ClassScope($sym, $this->scope);
    $this->scope = $node->scope;
    
    // we don't need pass 1 here
    $this->pass = 2;
  }
  
  protected function leave_iface_decl($node)
  {
    $this->scope = array_pop($this->sstack);
    $this->pass = 0;
  }
  
  protected function enter_class_decl($node)
  {    
    $flags = SYM_FLAG_CONST;
        
    if ($node->mods) {
      if (!$this->check_mods($node->mods, false, true))
        return $this->drop();
      
      $flags = mods_to_symflags($node->mods, $flags);
    }
    
    if ($node->members === null)
      $flags |= SYM_FLAG_INCOMPLETE;
    else
      // simple test to detect abstract-members
      if (!$this->check_class($node, $flags))
        return $this->drop();    
    
    $super = null;
    if ($node->ext !== null) {
      $super = $this->handle_class_ext($node->ext);
      if (!$super) return $this->drop();
    }
    
    $impls = null;
    if ($node->impl !== null) {
      $impls = $this->handle_class_impl($node->impl);
      if (!$impls) return $this->drop();
    }
          
    $cid = ident_to_str($node->id);
    $sym = new ClassSym($cid, $flags, $node->loc);
    $sym->super = $super;
    $sym->impls = $impls;
          
    if (!$this->add_symbol($cid, $sym))
      return $this->drop();
    
    array_push($this->sstack, $this->scope);
    $node->scope = new ClassScope($sym, $this->scope);
    $this->scope = $node->scope;
        
    // pass 1: add all member-symbols without entering functions
    // pass 2: improve symbols and enter functions ...
    $this->handle_class_members($node->members); 
    
    // TODO: inspect ctor here
    // because the analyzer will complain about uninitialized symbols
  }
  
  protected function leave_class_decl($node)
  {    
    assert($this->scope instanceof ClassScope);
    $sym = $this->scope->symbol;
    
    if (!($sym->flags & SYM_FLAG_ABSTRACT))
      $this->check_class_final($sym);
    
    // back to prev scope
    $this->scope = array_pop($this->sstack);
    $this->pass = 0;
  }
  
  protected function enter_nested_mods($node)
  {
    // print "nested mods!\n";
    
    if (!$this->check_mods($node->mods, true, false))
      return $this->drop();
    
    $flags = mods_to_symflags($node->mods);
    
    array_push($this->fstack, $this->flags);
    $this->flags |= $flags;
  }
  
  protected function leave_nested_mods($node)
  {
    $this->flags = array_pop($this->fstack);
  }
  
  protected function enter_fn_decl($node)
  {    
    $flags = SYM_FLAG_NONE;
    $apppf = $this->pass > 0;
    
    if ($node->mods) {
      if (!$this->check_mods($node->mods, $apppf, !$apppf))
        return $this->drop();
      
      $flags = mods_to_symflags($node->mods, $flags);
    }
    
    if ($flags & SYM_FLAG_EXTERN && $node->body !== null) {
      $this->error_at($node->loc, ERR_ERROR, 'extern function can not have a body');
      return $this->drop();
    }
    
    // if (in class) and (has modifier static) and (has no modifier extern) and (has no body)
    if ($apppf && $flags & SYM_FLAG_STATIC && !($flags & SYM_FLAG_EXTERN) && $node->body === null) {
      $this->error_at($node->loc, ERR_ERROR, 'static function can not be abstract');
      return $this->drop();
    }
    
    // if (not in class) and (has modifier static) and (has modifier extern)
    if (!$apppf && $flags & SYM_FLAG_STATIC && $flags & SYM_FLAG_EXTERN) {
      $this->error_at($node->loc, ERR_ERROR, 'static function can not be extern');
      return $this->drop();
    }
    
    if (!($flags & SYM_FLAG_EXTERN) && $node->body === null)
      $flags |= SYM_FLAG_INCOMPLETE;
    
    if ($node->params !== null)
      if (!$this->check_params($node->params, false))
        return $this->drop();
    
    $fid = ident_to_str($node->id);
    $sym = new FnSym($fid, $flags, $node->loc);
    
    if (!$this->add_symbol($fid, $sym))
      return $this->drop();
    
    if ($this->pass === 1)
      return $this->drop(); // skip params and do not enter ...
    
    array_push($this->sstack, $this->scope);
    $node->scope = new FnScope($sym, $this->scope);
    $this->scope = $node->scope;
    
    // backup flags
    array_push($this->fstack, $this->flags);
    $this->flags = SYM_FLAG_NONE;
    
    // just for debugging
    $sym->fn_scope = $this->scope;
    
    if ($node->params !== null)
      $this->handle_params($sym, $node->params);
    
    ++$this->infn;
  }
  
  protected function leave_fn_decl($node)
  {
    // back to prev scope
    $this->scope = array_pop($this->sstack);
    
    // restore flags
    $this->flags = array_pop($this->fstack);
    
    --$this->infn;
  }
    
  protected function visit_let_decl($node)
  {
    return $this->visit_var_decl($node);
  }
  
  protected function visit_var_decl($node)
  {        
    $flags = SYM_FLAG_NONE;
    $apppf = $this->pass > 0;
    
    if ($node->mods) {
      if (!$this->check_mods($node->mods, $apppf, !$apppf))
        return $this->drop();
      
      $flags = mods_to_symflags($node->mods, $flags);
    }
    
    foreach ($node->vars as $var)
      $this->handle_var($var->dest, $var->init, $flags);
  }
  
  protected function visit_require_decl($node)
  {
    // require must act as require_once
    $path = $this->handle_expr($node->expr);
    
    if ($path->kind !== VAL_KIND_STR) {
      $this->error_at($node->loc, ERR_ERROR, 'require from unknown source');
      return $this->drop();
    }
    
    $path = $path->value;
    $user = $path;
    
    // require is always relative, except if the path starts with an '/'
    // or on windows [letter]:/ or [letter]:\
    static $abs_re_nix = '/^\//';
    static $abs_re_win = '/^(?:[a-z]:)?(?:\/|\\\\)/i';
    
    /*
    print "path: $path\n";
    var_dump(preg_match($abs_re_nix, $path));
    var_dump(preg_match($abs_re_win, $path));
    */
    
    if (!preg_match((PHP_OS === 'WINNT') ? $abs_re_win : $abs_re_nix, $path))
      // make realtive path
      $path = dirname($node->loc->file) . DIRECTORY_SEPARATOR . $path;
    
    switch (substr(strrchr($path, '.'), 1)) {
      case 'phs': case 'phm';
        break;
      
      default:
        // try 'phm', then fallback to 'phs'
        if (!is_file($path . '.phm'))
          $path .= '.phs';
        else
          $path .= '.phm';
    }
    
    if (!is_file($path)) {
      $this->error_at($node->loc, ERR_ERROR, 'unable to import file "%s"', $user);
      return;
    }
    
    if (!in_array($path, self::$require_paths)) {
      self::$require_paths[] = $path;
      
      require_once 'parser.php';
      require_once 'source.php';
      
      // cache parser?
      $psr = new Parser($this->ctx);
      $src = new FileSource($path);
      
      $ast = $psr->parse_source($src);
      
      if ($ast) {
        $ast->dest = $src->get_dest();
        $anl = new Analyzer($this->ctx, $this->com);
        
        // analyze unit
        $anl->analyze($ast);
            
        // add it to the compiler
        $this->com->add_unit($ast);
      }
    }
  }
  
  protected function enter_block($node) {}
  protected function leave_block($node) {}
  
  protected function visit_do_stmt($node) 
  {
    $this->walk_some($node->stmt);
    $this->handle_expr($node->expr);
  }
  
  protected function visit_if_stmt($node) 
  {
    $this->handle_expr($node->expr);
    $this->walk_some($node->stmt);
    
    if ($node->elsifs !== null) {
      foreach ($node->elsifs as $elsif) {
        $this->handle_expr($elsif->expr);
        $this->walk_some($elsif->stmt);
      }
    }
    
    if ($node->els !== null)
      $this->walk_some($node->els->stmt);
  }
  
  protected function visit_for_stmt($n) {}
  protected function visit_for_in_stmt($n) {}
  protected function visit_try_stmt($n) {}
  protected function visit_php_stmt($n) {}
  protected function visit_goto_stmt($n) {} // super extra fun!
  protected function visit_test_stmt($n) {}
  protected function visit_break_stmt($n) {} // extra fun!
  protected function visit_continue_stmt($n) {} // extra fun!
  
  protected function visit_throw_stmt($node) 
  {
    $this->handle_expr($node->expr);
  }
  
  protected function visit_while_stmt($node) 
  {
    $this->handle_expr($node->test);
    $this->walk_some($node->stmt);
  }
  
  protected function visit_yield_stmt($n) {}
  protected function visit_assert_stmt($n) {}
  protected function visit_switch_stmt($n) {}
  
  protected function visit_return_stmt($node) 
  {
    $this->handle_expr($node->expr);
        
    if ($this->infn < 1) {
      $this->error_at($node->loc, ERR_ERROR, 'return outside of function');
      return $this->drop();
    }
  }
  
  protected function visit_labeled_stmt($n) {}
  
  /* ------------------------------------ */
    
  /**
   * adds the given parameters to the current scope
   * 
   * @param  FnSym $fnsym
   * @param  array $params
   */
  protected function handle_params($fnsym, $params)
  {
    $error = false;
    
    foreach ($params as $param) {
      $kind = $param->kind();
      
      if ($kind === 'param' || $kind === 'rest_param') {        
        $flags = SYM_FLAG_NONE;
        
        if ($kind === 'param' && $param->mods !== null) {
          if (!$this->check_mods($param->mods)) {
            $error = true;
            continue;
          }
          
          $flags = mods_to_symflags($param->mods, $flags);
        }
        
        $pid = ident_to_str($param->id);
        $sym = new ParamSym($pid, new Value(VAL_KIND_UNKNOWN), $flags, $param->loc);
        $sym->rest = $kind === 'rest_param';
        
        if ($param->hint !== null) {
          $hint = $this->handle_expr($param->hint);
          
          if ($hint->kind !== VAL_KIND_TYPE) {
            switch ($hint->kind) {
              case VAL_KIND_CLASS:
              case VAL_KIND_IFACE:
                break;
              default:
                if ($hint->symbol !== null) {
                  $this->error_at($param->hint->loc, ERR_ERROR, '`%s` can not be a type-hint', $hint->symbol->name);
                  $this->error_at($hint->symbol->loc, ERR_INFO, 'declaration was here');
                  $this->error_at($param->hint->loc, ERR_INFO, 'only type-ids, classes and interfaces can be used as hints');
                } else
                  $this->error_at($param->hint->loc, ERR_ERROR, 'invalid type-hint');
                
                $error = true;
                continue 2;
            }
            
            $hint = $hint->symbol;
          } else
            $hint = $hint->value; // use type-id
          
          $sym->hint = $hint;
        }
        
        if (!$this->add_symbol($pid, $sym))
          $error = true;
          
        if (!$error)
          $fnsym->params[] = $sym; 
      }
    }
    
    return !$error;
  }
  
  /**
   * check params
   * 
   * @param  array $params
   * @param  bool $athis allow this-params
   * @return boolean
   */
  protected function check_params($params, $athis)
  {
    $seen_rest = false;
    $rest_loc = null;
    $seen_error = false;
    
    foreach ($params as $param) {
      $kind = $param->kind();
      
      if ($kind === 'rest_param') {
        if ($seen_rest) {
          $seen_error = true;
          $this->error_at($param->loc, ERR_ERROR, 'duplicate rest-parameter');
          $this->error_at($rest_loc, ERR_INFO, 'previous rest-parameter was here');
        } else {
          $seen_rest = true;
          $rest_loc = $param->loc;
        }
      } 
      
      elseif ($kind === 'this_param' && !$athis) {
        $seen_error = true;
        $this->error_at($param->loc, ERR_ERROR, 'this-parameter not allowed here');
        $this->error_at($param->loc, ERR_INFO, 'only contructor-methods can handle this-parameters');
      }
      
      elseif ($seen_rest) {
        $seen_error = true;
        $this->error_at($param->loc, 'parameter after rest-parameter');
        $this->error_at($rest_loc, ERR_INIO, 'rest-parameter was here');
      }
    }
    
    return !$seen_error;
  }
  
  /**
   * handles a variable declaration
   * 
   * @param  Ident|ArrDestr|ObjDestr $var
   * @param  int $flags
   * @return boolean
   */
  protected function handle_var($var, $init, $flags)
  {    
    switch ($var->kind()) {
      case 'ident':
        $vid = ident_to_str($var);
        
        if ($this->pass === 1)
          // do not handle expressions
          $val = new Value(VAL_KIND_EMPTY); 
        else
          $val = $this->handle_expr($init);
        
        if ($val->kind === VAL_KIND_UNKNOWN && $flags & SYM_FLAG_CONST) {
          $this->error_at($var->loc, ERR_ERROR, 'constant variables must be reducible at compile-time');
          return false;
        }
        
        $sym = new VarSym($vid, $val, $flags, $var->loc);
        return $this->add_symbol($vid, $sym);
      case 'obj_destr':
        return $this->handle_var_obj($var, $flags);
      case 'arr_destr':
        return $this->handle_var_arr($var, $flags);
      default:
        print "what? " . $var->kind() . "\n";
        assert(0);
    }
  }
  
  /**
   * handles a variable-object-destructor
   * 
   * @param  ObjDestr $dest
   * @param  int $flags
   * @return boolean
   */
  protected function handle_var_obj($dest, $flags)
  {
    foreach ($dest->items as $item) {
      $kind = $item->kind();
      
      if ($kind !== 'ident') {
        $this->error_at($item->loc, ERR_ERROR, 'invalid property id');
        continue;   
      }
      
      $this->handle_var($item, null, $flags);
    }
    
    return true;
  }
  
  /**
   * handles a variable-array-destructor
   * 
   * @param  ArrDestr $dest
   * @param  int $flags
   * @return boolean
   */
  protected function handle_var_arr($dest, $flags)
  {
    foreach ($dest->items as $item)
      $this->handle_var($item, null, $flags);
    
    return true;
  }
  
  /**
   * check interface-members
   * 
   * @param  array $members
   * @return boolean
   */
  protected function check_iface_members($members)
  {
    $error = false;
    
    foreach ($members as $mem) {
      $kind = $mem->kind();
      
      if ($kind === 'fn_decl') {
        // TODO: allow static-methods with body here?
        if ($mem->body !== null) {
          $this->error_at($mem->body->loc, ERR_ERROR, 'interface-method can not have a body');
          $error = true;
        }        
      } 
      
      elseif ($kind === 'nested_mods') {
        if (!$this->check_iface_members($mem->members))
          $error = true;
      }
       
      elseif ($kind === 'let_decl' || $kind === 'var_decl') {
        foreach ($mem->vars as $var)
          if ($var->init !== null) {
            // TODO: allow static-varaiables with initializers here?
            $this->error_at($var->loc, ERR_ERROR, 'interface-variables can not be initialized');
            $error = true;
          }
      }
      
    }
    
    return !$error;
  }
  
  /**
   * check a class and its members
   * 
   * @param  Node $node 
   * @param  int $flags
   * @return boolean
   */
  protected function check_class($node, &$flags)
  {
    $ext = !!($flags & SYM_FLAG_EXTERN);
    return $this->check_class_members($node->members, $flags, $ext);
  }
  
  /**
   * checks a class in its final state (the class is not abstract)
   * 
   * @param  ClassSym $sym
   * @return bool
   */
  protected function check_class_final($sym)
  {
    assert(!($sym->flags & SYM_FLAG_ABSTRACT));
    
    $need = new SymTable; // members which need a implementation
    $nloc = new SymTable; // where incomplete members have been defined
    $done = new SymTable; // members which are implemented
    
    // 1. collect abstract members from interfaces
    if ($sym->impls !== null) 
      $this->collect_iface_impls($sym->impls, $need, $nloc);
   
    // 2. collect members from super class(es)
    $this->collect_class_impls($sym->super, $need, $nloc, $done);
    
    // 3. collect members from current class
    foreach ($sym->members as $mem) 
      $done->add($mem->name, $mem);
    
    // 4. validate
    $okay = true;
    
    foreach ($need as $nam) {
      $inh = $nloc->get($nam->name);
      
      if (!$done->has($nam->name)) {
        $this->error_at($sym->loc, ERR_ERROR, 'method `%s` derived from `%s` needs an implementation', $nam->name, $inh->name);
        $this->error_at($nam->loc, ERR_INFO, 'defined abstract here');
        $okay = false;
      } else {
        $imp = $done->get($nam->name);
        
        if (!$this->check_member_impl($imp, $nam)) {
          $this->error_at($imp->loc, ERR_ERROR, 'implemention of method `%s` must match the declaration in `%s`', $imp->name, $inh->name);
          $this->error_at($nam->loc, ERR_INFO, 'declaration was here');
          $okay = false;
        }
      }
    }
    
    return $okay;
  }
  
  protected function collect_class_impls($base, $need, $nloc, $done)
  {
    while ($base !== null) {
      $csym = $base->symbol;
      
      if ($csym->flags & SYM_FLAG_INCOMPLETE) {
        // check if a completed symbol is available
        $rsym = $this->handle_expr($base->path);
        
        if ($rsym->kind !== VAL_KIND_CLASS || ($rsym->symbol->flags & SYM_FLAG_INCOMPLETE)) {
          $this->error_at($sym->loc, ERR_ERROR, '`%s` must be fully defined before it can be used', $csym->name);
          $this->error_at($csym->loc, ERR_INFO, 'declaration was here');
          continue;
        }
        
        $csym = $rsym->symbol;
      }
      
      foreach ($csym->members as $mem)
        // which means abstract in this context
        if ($mem->flags & SYM_FLAG_INCOMPLETE) {
          $need->set($mem->name, $mem);
          $nloc->set($mem->name, $csym);
        } else
          $done->add($mem->name, $mem);
      
      if ($csym->impls !== null)
        $this->collect_iface_impls($csym->impls, $need, $nloc, $csym);
      
      $base = $csym->super;
    }
  }
  
  protected function collect_iface_impls($impls, $need, $nloc, $deri = null)
  {
    foreach ($impls as $idx => $impl) {
      $isym = $impl->symbol;
      
      if ($isym->flags & SYM_FLAG_INCOMPLETE) { 
        // check if a completed symbol is available
        $rsym = $this->handle_expr($impl->path);
        
        if ($rsym->kind !== VAL_KIND_IFACE || ($rsym->symbol->flags & SYM_FLAG_INCOMPLETE)) {
          $this->error_at($impl->loc, ERR_ERROR, '`%s` must be fully defined before it can be used', $isym->name);
          
          if ($deri !== null)
            $this->error_at($deri->loc, ERR_INFO, 'derived from `%s`', $deri->name);
          
          $this->error_at($isym->loc, ERR_INFO, 'declaration was here');
          continue;
        }
        
        $isym = $rsym->symbol;
      }
      
      foreach ($isym->members as $memb) {
        $need->set($memb->name, $memb);
        $nloc->set($memb->name, $isym);
      }
    }
  }
  
  /**
   * checks if both given members are compatible to each other
   * 
   * @param  Symbol $imp the implemented member
   * @param  Symbol $inh the abstract member
   * @return boolean
   */
  protected function check_member_impl($imp, $inh) 
  {
    // 1. check related flags
    static $rflags = [
      SYM_FLAG_CONST,
      SYM_FLAG_FINAL,
      SYM_FLAG_PUBLIC,
      SYM_FLAG_PRIVATE,
      SYM_FLAG_PROTECTED,
      SYM_FLAG_SEALED,
      SYM_FLAG_INLINE
    ];
    
    foreach ($rflags as $flag)
      if (($inh->flags & $flag && !($imp->flags & $flag)) ||
          ($imp->flags & $flag && !($inh->flags & $flag))) {
        #$this->error_at($imp->loc, ERR_ERROR, 'wrong modifiers');
        return false;
      }
      
    // 2. check parameter count
    if (count($imp->params) !== count($inh->params)) {
      #$this->error_at($imp->loc, ERR_ERROR, 'wrong parameter count %d <-> %d', count($imp->params), count($inh->params));
      return false;
    }
    
    // 3. check parameter hint/flags        
    foreach ($imp->params as $idx => $imp_param) {
      $inh_param = $inh->params[$idx];
      
      if ($imp_param->hint !== null) {
        if ($inh_param->hint === null) {
          #$this->error_at($imp_param->loc, ERR_ERROR, 'missing type-hint');
          return false;
        }
        
        // note: the hint is the same reference
        if ($imp_param->hint !== $inh_param->hint) {
          // TODO: check if the hints share the same base-class or interface
          #$this->error_at($imp_param->loc, ERR_ERROR, 'different type-hint');
          return false;
        }
      }
      
      if ($imp_param->flags !== $inh_param->flags) {
        #$this->error_at($imp_param->loc, ERR_ERROR, 'different flags');
        return false;
      }
      
      if ($imp_param->rest !== $inh_param->rest) {
        #$this->error_at($imp_param->loc, ERR_ERROR, 'rest-parameter mismatch');
        return false;
      }
      
      // the name does not matter
    }
    
    return true;
  }
  
  /**
   * checks a class-extend
   * 
   * @param  Name $ext
   * @return Symbol
   */
  protected function handle_class_ext($name)
  {    
    $val = $this->handle_expr($name);
    
    if ($val->kind !== VAL_KIND_CLASS) {
      $this->error_at($name->loc, ERR_ERROR, '`%s` can not be extended (used as class)', name_to_str($name));
      return null;
    }
    
    return SymbolRef::from(null, $val->symbol, $name, $name->loc);
  }
  
  /**
   * checks a class-implement
   * 
   * @param  array $impl
   * @return array
   */
  protected function handle_class_impl($impl)
  {
    $ifaces = [];
    $fail = false;
    
    foreach ($impl as $imp) {
      $val = $this->handle_expr($imp);
      
      if ($val->kind !== VAL_KIND_IFACE) {
        $this->error_at($imp->loc, ERR_ERROR, '`%s` can not be implemented (used as interface)', name_to_str($imp));
        $fail = true;
        continue;
      }  
      
      $ifaces[] = SymbolRef::from(null, $val->symbol, $imp, $imp->loc);
    }
    
    return $fail ? null : $ifaces;
  }
  
  /**
   * check class-members
   * 
   * @param  array $members
   * @param  int $flags
   * @param  boolean $ext
   * @return boolean
   */
  protected function check_class_members($members, &$flags, $ext)
  {
    $error = false;
    
    foreach ($members as $mem) {
      $kind = $mem->kind();
      
      if ($kind === 'fn_decl') {
        // extern + function-body is a no-no
        if ($ext && $mem->body !== null) {
          $this->error_at($mem->body->loc, ERR_ERROR, 'extern class-method can not have a body');
          $error = true;
        }
        
        // mark the class itself as abstract
        if (!$ext && $mem->body === null)
          $flags |= SYM_FLAG_ABSTRACT;
        
      } 
      
      elseif ($kind === 'nested_mods')
        if (!$this->check_class_members($mem->members, $flags, $ext))
          $error = true;
      
      elseif ($ext) {
        $this->error_at($mem->loc, ERR_ERROR, 'invalid member in extern class');
        $this->error_at($mem->loc, ERR_INFO, 'only abstract functions are allowed');
        $error = true;
      }
    }
    
    return !$error;
  }
  
  protected function handle_class_members($members)
  {
    array_push($this->fstack, $this->flags);
    $this->flags |= SYM_FLAG_WEAK;
    
    $this->pass = 1; // do not enter functions
    $this->walk_some($members);
    
    $this->flags = array_pop($this->fstack);
    $this->pass = 2; // go berserk now!
  }
  
  /**
   * checks mods
   * 
   * @param  array  $mods
   * @param  boolean $ppp  allow p(ublic|rivate|rotected)
   * @param  boolean $ext  allow extern
   * @return boolean
   */
  protected function check_mods($mods, $ppp = false, $ext = true) 
  {
    $seen = [];
    static $pppa = [ T_PUBLIC, T_PRIVATE, T_PROTECTED ];
    
    foreach ($mods as $mod) {
      $type = $mod->type;
      
      if (isset ($seen[$type])) {
        $this->error_at($mod->loc, ERR_WARN, 'duplicate modifier `%s`', $mod->value);
        $this->error_at($seen[$type], ERR_INFO, 'previous modifier was here');
      }
      
      if ((!$ext && $type === T_EXTERN) || 
          (!$ppp && in_array($type, $pppa))) {
        $this->error_at($mod->loc, ERR_ERROR, 'modifier `%s` is not allowed here', $mod->value);
        return false;
      }
      
      $seen[$type] = $mod->loc;
    }  
    
    return true;
  }
  
  /* ------------------------------------ */
  
  protected function handle_expr($expr)
  {
    if ($expr === null)
      $this->value = new Value(VAL_KIND_EMPTY);  
    else    
      $this->walk_some($expr);
    
    return $this->value;
  }
  
  /* ------------------------------------ */
  
  protected function visit_expr_stmt($node)
  {
    $this->handle_expr($node->expr);
  }
  
  protected function visit_fn_expr($node)
  {    
    if ($node->params !== null)
      if (!$this->check_params($node->params, false))
        return $this->drop();
        
    if ($node->id !== null)
      $fid = ident_to_str($node->id);
    else
      $fid = '#anonymus~' . (self::$anon_fid++);
    
    $sym = new FnSym($fid, SYM_FLAG_NONE, $node->loc);  
      
    array_push($this->sstack, $this->scope);
    $node->scope = new FnScope($sym, $this->scope);
    $this->scope = $node->scope;
    
    // the symbol gets added after a new scope was entered
    if (!$this->add_symbol($fid, $sym))
      return $this->drop();
      
    // just for debugging
    $sym->fn_scope = $this->scope;
    
    // backup flags
    array_push($this->fstack, $this->flags);
    $this->flags = SYM_FLAG_NONE;
        
    if ($node->params !== null)
      $this->handle_params($node->params);
    
    ++$this->infn;
    $this->walk_some($node->body);
    --$this->infn;
    
    $this->scope = array_pop($this->sstack);
    $this->flags = array_pop($this->fstack);
    
    $this->value = new FnValue($sym);
  }
  
  protected function visit_bin_expr($node) 
  {
    $lhs = $this->handle_expr($node->left);
    $rhs = $this->handle_expr($node->right);
    
    if ($lhs->kind !== VAL_KIND_UNKNOWN) {
      // optimize for the translator
      $node->left->value = $lhs;
      
      if ($rhs->kind !== VAL_KIND_UNKNOWN) {
        // store the value anyway
        $node->right->value = $rhs;
        // reduce
        $this->reduce_bin_expr($lhs, $node->op, $rhs, $node->loc);
        goto out;
      }
    }
    
    $this->value = new Value(VAL_KIND_UNKNOWN);
    out:
  }
  
  protected function reduce_bin_expr($lhs, $op, $rhs, $loc) 
  {
    // 1. string-concat
    if ($op->type === T_CONCAT)
      // string-concat
      $this->value = new Value(VAL_KIND_STR, $lhs->value . $rhs->value);
    
    // 2. arithmetic operators
    elseif ($op->type === T_PLUS ||
            $op->type === T_MINUS ||
            $op->type === T_MUL ||
            $op->type === T_DIV ||
            $op->type === T_MOD ||
            $op->type === T_POW) 
    {
      $lval = $rval = $cval = 0;
    
      // cast left-hand-side to INT if necessary
      if ($lhs->kind !== VAL_KIND_DNUM &&
          $lhs->kind !== VAL_KIND_LNUM)
        $lval = (int) $lhs->value;
      else
        $lval = $lhs->value;
      
      // cast right-hand-side to INT if necessary
      if ($rhs->kind !== VAL_KIND_DNUM &&
          $rhs->kind !== VAL_KIND_LNUM)
        $rval = (int) $rhs->value;
      else
        $rval = $rhs->value;
      
      // result is a dnum, otherwise lnum (ignored if T_MOD -> php "bug")
      $kind = $op->type !== T_MOD && (
                $lhs->kind === VAL_KIND_DNUM ||
                $rhs->kind === VAL_KIND_DNUM
              ) ? VAL_KIND_DNUM : VAL_KIND_LNUM;
      
      switch ($op->type) {
        case T_PLUS:
          $cval = $lval + $rval;
          break;
        case T_MINUS:
          $cval = $lval - $rval;
          break;
        case T_MUL:
          $cval = $lval * $rval;
          break;
        case T_DIV:
        case T_MOD:
          if ($rval == 0) { // compare by value-only
            $this->error_at($loc, ERR_ERROR, 'division by zero');  
            // this is php-thing
            $kind = VAL_KIND_BOOL;
            $cval = false;
          } else
            if ($op->type === T_MOD)
              $cval = $lval % $rval;
            else
              $cval = $lcal / $rval;
          
          break;
        case T_POW:
          $cval = pow($lval, $rval);
      }
      
      $this->value = new Value($kind, $cval);
    }
          
    // 3. bitwise operators
    elseif ($op->type === T_BIT_AND ||
            $op->type === T_BIT_OR ||
            $op->type === T_BIT_XOR)
    {
      $lval = $rval = $cval = 0;
      
      // cast left-hand-side to INT
      if ($lhs->kind !== VAL_KIND_LNUM)
        $lval = (int) $lhs->value;
      else
        $lval = $lhs->value;
      
      // cast right-hand-side to INT
      if ($rhs->kind !== VAL_KIND_LNUM)
        $rval = (int) $rhs->value;
      else
        $rval = $rhs->value;
      
      // result is always a lnum
      switch ($op->type) {
        case T_BIT_AND:
          $cval = $lval & $rval;
          break;
        case T_BIT_OR:
          $cval = $lval | $rval;
          break;
        case T_BIT_XOR:
          $cval = $lval ^ $rval;
          break;
      }
      
      $this->value = new Value(VAL_KIND_LNUM, $cval);
    }
    
    // 4. boolean/equal operators
    elseif ($op->type === T_BOOL_AND ||
            $op->type === T_BOOL_OR ||
            $op->type === T_BOOL_XOR ||
            $op->type === T_EQ ||
            $op->type === T_NEQ) 
    {
      $lval = $lhs->value;
      $rval = $rhs->value;
      $cval = false;
      
      switch ($op->type) {
        case T_BOOL_AND:
          $cval = $lval && $rval;
          break;
        case T_BOOL_OR:
          $cval = $lval || $rval;
          break;
        case T_BOOL_XOR:
          $cval = $lval xor $rval;
          break;
        case T_EQ:
          $cval = $lval === $rval;
          break;
        case T_NEQ:
          $cval = $lval !== $rval;
          break;
      }
      
      $this->value = new Value(VAL_KIND_BOOL, $cval);
    }
    
    // 5. conditional operators
    elseif ($op->type === T_LT ||
            $op->type === T_GT ||
            $op->type === T_LTE ||
            $op->type === T_GTE)
    {
      $lval = $rval = $cval = 0;
      
      // cast left-hand-side to INT if necessary
      if ($lhs->kind !== VAL_KIND_DNUM &&
          $lhs->kind !== VAL_KIND_LNUM)
        $lval = (int) $lhs->value;
      else
        $lval = $lhs->value;
      
      // cast right-hand-side to INT if necessary
      if ($rhs->kind !== VAL_KIND_DNUM &&
          $rhs->kind !== VAL_KIND_LNUM)
        $rval = (int) $rhs->value;
      else
        $rval = $rhs->value;
      
      switch ($op->type) {
        case T_LT:
          $cval = $lval < $rval;
          break;
        case T_GT:
          $cval = $lval > $rval;
          break;
        case T_LTE:
          $cval = $lval <= $rval;
          break;
        case T_GTE:
          $cval = $lval >= $rval;
          break;
      }
      
      $this->value = new Value(VAL_KIND_BOOL, $cval);
    }
    
    // 6. shift-operators
    elseif ($op->type === T_SL ||
            $op->type === T_SR) 
    {
      $lval = $rval = $cval = 0;
      
      // cast left-hand-side to INT
      if ($lhs->kind !== VAL_KIND_LNUM)
        $lval = (int) $lhs->value;
      else
        $lval = $lhs->value;
      
      // cast right-hand-side to INT
      if ($rhs->kind !== VAL_KIND_LNUM)
        $rval = (int) $rhs->value;
      else
        $rval = $rhs->value;
      
      switch ($op->type) {
        case T_SL:
          $cval = $lval << $rval;
          break;
        case T_SR:
          $cval = $lval >> $rval;
          break;
      }
      
      $this->value = new Value(VAL_KIND_LNUM, $cval);
    }
    
    // 7. unknown
    else {
      print "unknown binary operator {$op->value} ({$op->type})";
      assert(0);
    }
  }
  
  protected function visit_check_expr($node) 
  {
    $lhs = $this->handle_expr($node->left);
    $rhs = $this->handle_expr($node->right);
    
    if ($lhs->kind !== VAL_KIND_UNKNOWN) {
      // optimize for the translator
      $node->left->value = $lhs;
      
      if ($rhs->kind !== VAL_KIND_UNKNOWN) {
        // store value anyway
        $node->right->value = $rhs;
        // reduce
        $this->reduce_check_expr($lhs, $node->op, $rhs, $node->loc);
        goto out;
      }
    }
    
    $this->value = new Value(VAL_KIND_UNKNOWN);
    out:
  }
  
  protected function reduce_check_expr($lhs, $op, $rhs, $loc)
  {
    if ($rhs->kind === VAL_KIND_TYPE) {
      $res = false;
      
      switch ($rhs->value) {
        case T_TINT:
          $res = $lhs->kind === VAL_KIND_LNUM;
          break;
        case T_TBOOL:
          $res = $lhs->kind === VAL_KIND_BOOL;
          break;
        case T_TFLOAT:
          $res = $lhs->kind === VAL_KIND_DNUM;
          break;
        case T_TSTRING:
          $res = $lhs->kind === VAL_KIND_STR;
          break;
        case T_TREGEXP:
          $res = $lhs->kind === VAL_KIND_REGEXP;
          break;
      }
      
      $this->value = new Value(VAL_KIND_BOOL, $res);
    } else {
      // TODO: implement instanceof?
      $this->value = new Value(VAL_KIND_UNKNOWN);
    }
  }
  
  protected function visit_cast_expr($node) 
  {
    $lhs = $this->handle_expr($node->expr);
    $rhs = $this->handle_expr($node->type);
    
    if ($lhs->kind !== VAL_KIND_UNKNOWN) {
      // store value
      $node->expr->value = $lhs;
      
      if ($rhs->kind !== VAL_KIND_UNKNOWN) {
        // store value
        $node->type->value = $rhs;
        // reduce
        $this->reduce_cast_expr($lhs, $rhs, $node->loc);
        goto out;
      }
    }  
    
    $this->value = new Value(VAL_KIND_UNKNOWN);
    out:
  }
  
  protected function reduce_cast_expr($lhs, $rhs, $loc)
  {
    if ($rhs->kind === VAL_KIND_TYPE) {
      $kind = $cast = 0;
      
      switch ($rhs->value) {
        case T_TINT:
          $kind = VAL_KIND_LNUM;
          $cast = (int) $lhs->value;
          break;
        case T_TBOOL:
          $kind = VAL_KIND_BOOL;
          $cast = (bool) $lhs->value;
          break;
        case T_TFLOAT:
          $kind = VAL_KIND_DNUM;
          $cast = (float) $lhs->value;
          break;
        case T_TSTRING:
          $kind = VAL_KIND_STR;
          $cast = (string) $lhs->value;
          break;
        case T_TREGEXP:
          $kind = VAL_KIND_REGEXP;
          $cast = (string) $lhs->value;
          break;
      }
      
      $this->value = new Value(VAL_KIND_BOOL, $cast);
    } else {
      $sym = $rhs->symbol;
      
      if ($sym->kind !== SYM_KIND_CLASS) {
        $this->error_at($loc, ERR_ERROR, 'can not cast to incomplete type `%s`', $sym->name);
        $this->error_at($sym->loc, ERR_INFO, 'definition was here');
      } else {
        $cst = $sym->members->get('from');
        $stc = false;
        
        if (!$cst || $stc = !($cst->flags & SYM_FLAG_STATIC)) {
          $this->error_at($loc, ERR_ERROR, 'symbol `%s` does not allow casts', $sym->name);
          
          if ($stc === true)
            $this->error_at($cst->loc, ERR_INFO, '^ due to missing `static` modifier here');
        }
        
        // a cast does not necessary yield a instance of the given type.
        // it depends on the return-value of Type.from() 
        // 
        // TODO: revisit after function-inlining?
        $this->value = new Value(VAL_KIND_UNKNOWN);
      }
    }
  }
  
  protected function visit_update_expr($node) 
  {
    $kind = $node->expr->kind();
    $fail = false;
    
    if ($kind !== 'name' && $kind !== 'member_expr') {
      $this->error_at($node->loc, ERR_ERROR, 'invalid increment/decrement operand');
      $fail = true;
    }
    
    $lhs = $this->handle_expr($node->expr);
    
    if (!$fail && $lhs->kind !== VAL_KIND_UNKNOWN) {
      // store value
      $node->expr->value = $lhs;
      // reduce prefix-usage, postfix is not posible here (implement?)
      if ($node->prefix === true) {
        $this->reduce_update_expr($lhs, $node->op, $node->loc);
        goto out;
      }
    }  
    
    $this->value = new Value(VAL_KIND_UNKNOWN);
    out:
  }
  
  protected function reduce_update_expr($lhs, $op, $loc)
  {
    $cval = 0;
    $kind = $lhs->kind;
    
    if ($kind !== VAL_KIND_DNUM &&
        $kind !== VAL_KIND_LNUM) {
      $cval = (int) $lhs->value;
      $kind = VAL_KIND_LNUM;
    } else
      $cval = $lhs->value;
      
    if ($op->type === T_INC)
      $cval += 1;
    else
      $cval -= 1;
    
    $this->value = new Value($kind, $cval);
  }
  
  protected function visit_assign_expr($node) 
  {
    $kind = $node->left->kind();
    $fail = false;
    
    if ($kind !== 'name' && $kind !== 'member_expr') {
      $this->error_at($node->loc, ERR_ERROR, 'invalid assigment left-hand-side');
      $fail = true;
    }
    
    array_push($this->astack, [ $this->access, $this->accloc ]);
    $this->access = self::ACC_WRITE;
    $this->accloc = $node->loc;
    
    $lhs = $this->handle_expr($node->left);
    $rhs = $this->handle_expr($node->right);
    
    if (!$fail && $lhs->kind !== VAL_KIND_UNKNOWN) {
      if ($lhs->symbol !== null) {
        if ($lhs->symbol->value->kind !== VAL_KIND_EMPTY) {
          $this->error_at($node->loc, ERR_ERROR, 're-assignment of read-only symbol `%s`', $lhs->symbol->name);
          goto unk;  
        }
        
        // assign it!
        $sym = $lhs->symbol;
        $this->value = $sym->value = $rhs;
        $sym->writes++;       
        goto out;
      } else {
        // the left-hand-side gets thrown-away anyway
        // possible something like { foo: 1 }.foo = 2 
        $this->value = $node->value = $rhs;
        goto out;
      }
    }
    
    unk:
    $this->value = new Value(VAL_KIND_UNKNOWN);
    
    out:
    list ($this->access, $this->accloc) = array_pop($this->astack);
  }
  
  protected function visit_member_expr($node) 
  {
    array_push($this->astack, [ $this->access, $this->accloc ]);
    $this->access = self::ACC_READ;
    $this->accloc = $node->loc;
    
    $lhs = $this->handle_expr($node->obj);
    $rhs = null;
    
    list ($this->access, $this->accloc) = array_pop($this->astack);
    
    if ($node->computed)
      $rhs = $this->handle_expr($node->member);
    
    if ($lhs->kind !== VAL_KIND_UNKNOWN) {
      // try to reduce it
      $this->reduce_member_expr($lhs, $rhs, $node->member, $node->prop, $node->loc);
      goto out;    
    }
    
    $this->value = new Value(VAL_KIND_UNKNOWN);
    out:
  }
  
  protected function reduce_member_expr($lhs, $rhs, $member, $prop, $loc)
  {       
    if ($rhs !== null) {
      // the right-hand-side was handled before
      // accessing it direclty
      $key = $rhs->value;  
    } else
      // use the member
      $key = $member->value;
      
    if ($lhs->kind === VAL_KIND_EMPTY) {
      $ref = $lhs->symbol->name;
      $this->error_at($loc, ERR_WARN, 'access to (maybe) uninitialized symbol `%s`', $ref);      
      goto unk;
    }
    
    if ($this->access === self::ACC_WRITE) {
      $this->error_at($this->accloc, ERR_ERROR, 'write-access to constant member `%s`', $key);
      goto unk;
    }
    
    if ($prop) {
      // property-access
      $sym = null;
      
      if ($lhs->kind === VAL_KIND_CLASS || 
          $lhs->kind === VAL_KIND_TRAIT ||
          $lhs->kind === VAL_KIND_IFACE) {
        // values like this must be bound to a symbol
        assert($lhs->symbol !== null);
        
        // fetch member
        $sym = $lhs->symbol->mst->get((string) $key, false);
        
        if (!$sym) {
          // symbol not found
          $this->error_at($loc, ERR_ERROR, 'access to undefined property `%s` of `%s`', $key, $lhs->symbol->name);
          goto unk;  
        }
        
        // if symbol is constant and it has a value
        // the value can be unknown though
        // TODO: implement static access
        if (($sym->flags & SYM_FLAG_CONST) && $sym->value->kind !== VAL_KIND_EMPTY) {
          $this->value = Value::from($sym);
          $sym->reads++;
        } else {
          // accessing non-static/non-const or empty property
          if (!($sym->flags & SYM_FLAG_STATIC))
            $this->error_at($loc, ERR_ERROR, 'access to non-static/const property `%s` of `%s` in invalid context', $key, $lhs->symbol->name);
          goto unk;
        }        
      } else {
        $key = (string) $key;
        
        if ($lhs->kind !== VAL_KIND_OBJ) {
          $ref = $lhs->symbol ? "symbol `{$lhs->symbol->name}`" : '(unknown)';
          $this->error_at($loc, ERR_ERROR, 'trying to get property "%s" of non-object %s', $key, $ref);
          $this->error_at($loc, ERR_INFO, 'left-hand-side is %s', $lhs);          
          goto unk;
        }
        
        if (!isset ($lhs->value[$key])) {
          $this->error_at($loc, ERR_WARN, 'access to undefined property "%s"', $key);
          $lhs->value[$key] = new Value(VAL_KIND_UNKNOWN);
        }
        
        $this->value = $lhs->value[$key];
      }
    } else {
      // array-access
      if (!is_int($key) && !ctype_digit($key)) {
        $this->error_at($loc, ERR_ERROR, 'invalid array-index %s', $key);
        goto unk;
      }
      
      $key = (int) $key;
      
      if ($lhs->kind !== VAL_KIND_ARR) {
        $this->error_at($loc, ERR_ERROR, 'trying to access index %s of non-array', $key);
        goto unk;
      }
            
      if (!isset ($lhs->value[$key])) {
        $this->error_at($loc, ERR_ERROR, 'access to undefined index %s', $key);
        goto unk;
      }
        
      $this->value = $lhs->value[$key];     
    }
    
    goto out;
    
    unk:
    $this->value = new Value(VAL_KIND_UNKNOWN);
    
    out:
  }
  
  protected function visit_cond_expr($node) 
  {
    $cnd = $this->handle_expr($node->test);
    $lhs = $node->then !== null ? $this->handle_expr($node->then) : null;
    $rhs = $this->handle_expr($node->els);
    
    if ($cnd->kind !== VAL_KIND_UNKNOWN) {
      $node->test->value = $cnd;
      
      if ($lhs === null || $lhs->kind !== VAL_KIND_UNKNOWN) {
        if ($lhs !== null) $node->then->value = $lhs;
        
        if ($rhs->kind !== VAL_KIND_UNKNOWN) {
          $node->els->value = $rhs;
          
          $this->reduce_cond_expr($cnd, $lhs, $rhs, $node->loc);
          goto out;
        }
      }
    }
    
    $this->value = new Value(VAL_KIND_UNKNOWN);
    
    out:
    $node->value = $this->value;
  }
  
  protected function reduce_cond_expr($cnd, $lhs, $rhs, $loc)
  {
    if ((bool) $cnd->value)
      $cval = $lhs === null ? $cnd : $lhs;
    else
      $cval = $rhs;
    
    $this->value = $cval;
  }
  
  protected function visit_call_expr($node) 
  {
    $lhs = $this->handle_expr($node->callee);
    
    if ($lhs->kind !== VAL_KIND_UNKNOWN) {
      if ($lhs->kind !== VAL_KIND_FN) {
        if ($lhs->symbol !== null)
          $this->error_at($node->callee->loc, ERR_ERROR, '%s is not callable', $lhs->symbol->name);
        else
          $this->error_at($node->callee->loc, ERR_ERROR, 'value is not callable');
      }
      
      $node->callee->value = $lhs;
    }
    
    if ($node->args !== null) {
      foreach ($node->args as $arg) {
        if ($arg->kind() === 'rest_arg')
          $this->handle_expr($arg->expr); 
        else
          $this->handle_expr($arg);
      }
    }
    
    $this->value = new Value(VAL_KIND_UNKNOWN);
  }
  
  protected function visit_yield_expr($node) 
  {
    $rhs = $this->handle_expr($node->expr);
    
    if ($rhs->kind !== VAL_KIND_UNKNOWN)
      $node->expr->value = $rhs;  
  }
  
  protected function visit_unary_expr($node) 
  {
    $rhs = $this->handle_expr($node->expr);
    
    if ($rhs->kind !== VAL_KIND_UNKNOWN) {
      $node->expr->value = $rhs;
      $this->reduce_unary_expr($rhs, $node->op, $node->loc);
      goto out;
    }  
    
    $this->value = new Value(VAL_KIND_UNKNOWN);
    out:
  }
  
  protected function reduce_unary_expr($rhs, $op, $loc)
  {
    if ($op->type === T_PLUS ||
        $op->type === T_MINUS) 
    {
      $rval = $cval = 0;
      $kind = 0;
      
      // cast to float
      if ($rhs->kind !== VAL_KIND_DNUM && 
          $rhs->kind !== VAL_KIND_LNUM) {
        $rval = (float) $rhs->value;
        $kind = VAL_KIND_DNUM;
      } else {
        $rval = $rhs->value;
        $kind = $rhs->kind;
      }
      
      switch ($op->type) {
        case T_PLUS:
          $cval = +$rval;
          break;
        case T_MINUS:
          $cval = -$rval;
          break;
      }
      
      $this->value = new Value($kind, $cval);
    }
    
    elseif ($op->type === T_BIT_NOT) {
      $rval = $cval = 0;
      
      if ($rhs->kind !== VAL_KIND_LNUM)
        $rval = (int) $rhs->value;
      else
        $rval = $rhs->value;
      
      $cval = ~$rval;
      $this->value = new Value(VAL_KIND_LNUM, $cval);
    }
    
    elseif ($op->type === T_EXCL) {
      $rval = $cval = false;
      
      if ($rhs->kind !== VAL_KIND_BOOL)
        $rval = (bool) $rhs->value;
      else
        $rval = $rhs->value;
      
      $cval = !$rval;
      $this->value = new Value(VAL_KIND_BOOL, $cval);
    }
    
    else {
      print "unknown unary operator {$op->value} ({$op->type})";
      assert(0);
    }
  }
  
  protected function visit_new_expr($node) 
  {
    // TODO: implement reduce (value -> VAL_KIND_NEW)
    $this->handle_expr($node->name);
    
    if ($node->args !== null)
      $this->handle_expr($node->args);
    
    $this->value = new Value(VAL_KIND_UNKNOWN);
  }
  
  protected function visit_del_expr($node) 
  {
    // TODO: what does del .. return? int? depends on the php-version
    $this->handle_expr($node->id);
    
    $this->value = new Value(VAL_KIND_UNKNOWN);
  }
  
  protected function visit_lnum_lit($node) 
  {
    $this->value = new Value(VAL_KIND_LNUM, $node->value);  
  }
  
  protected function visit_dnum_lit($node) 
  {
    $this->value = new Value(VAL_KIND_DNUM, $node->value);  
  }
  
  protected function visit_snum_lit($node) 
  {
    // TODO: numbers with suffix MUST be reduced at compile-time!
    $this->value = new Value(VAL_KIND_UNKNOWN);
  }
  
  protected function visit_regexp_lit($node) 
  {
    $this->value = new Value(VAL_KIND_REGEXP, $node->value);
  }
  
  protected function visit_arr_lit($node) 
  {
    // arrays only get a value if all items are constant
    $arr = [];
    $unk = false;
    
    if ($node->items !== null) {
      foreach ($node->items as $item) {
        $val = $this->handle_expr($item);
        
        if ($val->kind === VAL_KIND_UNKNOWN)
          $unk = true;
        
        if (!$unk) $arr[] = $val;
      }  
    }
    
    if ($unk) 
      $this->value = new Value(VAL_KIND_UNKNOWN);
    else
      $this->value = new Value(VAL_KIND_ARR, $arr);
  }
  
  protected function visit_obj_lit($node) 
  {
    // objects only get a value of all pairs are constant
    $obj = []; // jep, assoc
    $unk = false;
    
    if ($node->pairs !== null) {
      foreach ($node->pairs as $pair) {
        $kind = $pair->key->kind();
              
        if ($kind === 'ident' || $kind === 'str_lit')
          $key = $pair->key->value;
        else {
          $val = $this->handle_expr($pair->key);

          if ($val->kind === VAL_KIND_UNKNOWN)
            $unk = true;
          else
            $key = (string) $val->value;
        }
        
        $val = $this->handle_expr($pair->value);
        
        if ($val->kind === VAL_KIND_UNKNOWN)
          $unk = true;
        
        if (!$unk) $res[$key] = $val;
      }
  }
    
    if ($unk)
      $this->value = new Value(VAL_KIND_UNKNOWN);
    else
      $this->value = new Value(VAL_KIND_OBJ, $res);
  }
  
  protected function visit_name($name) 
  {    
    $bid = ident_to_str($name->base);
    $sym = null;
    
    if ($name->root) goto gmd;
    
    $sym = $this->scope->get($bid, false, null, true);
    $mod = null;
    
    // its not a symbol in the current scope
    if ($sym === null) {
      gmd:
      // check if the $bid is a global module
      $mrt = $this->ctx->get_module();
        
      if ($mrt->has_child($bid)) {
        if (empty ($name->parts))
          // module can not be referenced
          goto mod;  
        
        $mod = $mrt->get_child($bid);
        goto lcm;
      }
      
      // check if the symbol is a global symbol
      if ($sym === null) {
        $srt = $this->ctx->get_scope();
        $sym = $srt->get($bid, false, null);
      }
      
      // still not found?
      if ($sym === null)
        goto err;
    }
    
    switch ($sym->kind) {
      // symbols
      case SYM_KIND_CLASS:
      case SYM_KIND_TRAIT:
      case SYM_KIND_IFACE:
      case SYM_KIND_VAR:
      case SYM_KIND_FN:
        break;
      
      // references
      case REF_KIND_MODULE:
        if (empty ($name->parts))
          // a module can not be referenced
          goto mod;
        
        $mod = $sym->module;
        goto lcm;
        
      case REF_KIND_CLASS:
      case REF_KIND_TRAIT:
      case REF_KIND_IFACE:
      case REF_KIND_VAR:
      case REF_KIND_FN:
        $sym = $sym->symbol;
        break;
        
      default:
        print 'what? ' . $sym->kind;
        exit;
    }
    
    // best case: no more parts
    if (empty ($name->parts))     
      goto sym;
    
    /* ------------------------------------ */
    /* symbol lookup */
    
    // lookup other parts
    /*
    if ($sym->kind === SYM_KIND_VAR) {
      // the var could be a reference to a module
      // TODO: is this allowed?
      if ($sym->value === null)
        return null;
      
      switch ($sym->value->kind) {
        case REF_KIND_MODULE:
          $sym = $sym->value;
          break;
          
        default:
          // a subname lookup is not possible
          // this is an error actually, but fail silent here
          return null;
      }
    }
    */
    
    if ($sym->kind !== REF_KIND_MODULE) {
      $this->error_at($name->loc, ERR_ERROR, 'symbol `%s` used as module', $sym->name);
      goto unk;
    }
    
    $mod = $sym->module;
    
    /* ------------------------------------ */
    /* symbol lookup in module */
    
    lcm:
    $arr = name_to_stra($name);
    $lst = array_pop($arr);
    
    // ignore base
    array_shift($arr);
    
    $res = $mod->fetch($arr);
    
    if ($res === null)
      goto err;
    
    if ($res->has_child($lst))
      // module can not be referenced
      goto mod;
    
    $sym = $res->get($lst);
    
    if ($sym === null)
      goto err;
      
    if ($sym->kind > SYM_REF_DIVIDER) {
      if ($sym->kind === REF_KIND_MODULE)
        // module can not be a referenced
        goto mod;
      
      $sym = $sym->symbol;
    }
    
    sym:
    if ($sym->flags & SYM_FLAG_CONST) {      
      // do not use values from non-const symbols
      $this->value = Value::from($sym);
      $sym->reads++;
      goto out;
    }
    
    goto unk;
    
    mod:
    $this->error_at($name->loc, ERR_ERROR, 'module used as value');
    goto unk;
    
    err:    
    $this->error_at($name->loc, ERR_ERROR, 'access to undefined symbol `%s`', name_to_str($name));
    
    unk:
    $this->value = new Value(VAL_KIND_UNKNOWN);
    $this->value->symbol = $sym;
    
    out:
  }
  
  protected function visit_ident($node) 
  {
    $sym = $this->scope->get($node->value, false, null, true);
    
    if (!$sym) {
      $this->error_at($node->loc, ERR_ERROR, 'access to undefined symbol `%s`', $node->name);
      goto unk;
    }
    
    // do not use values from non-const symbols
    if (!($sym->flags & SYM_FLAG_CONST))
      goto unk;
    
    $this->value = Value::from($sym);
    $sym->reads++;
    goto out;
    
    unk:
    $this->value = new Value(VAL_KIND_UNKNOWN);
    $this->value->symbol = $sym;
    
    out:
  }
  
  protected function visit_this_expr($node) 
  {
    // TODO: this must be reducible! check in wich class we are atm
    $this->value = new Value(VAL_KIND_UNKNOWN);
  }
  
  protected function visit_super_expr($n) 
  {
    // TODO: see <this>
    $this->value = new Value(VAL_KIND_UNKNOWN);
  }
  
  protected function visit_null_lit($node) 
  {
    $this->value = new Value(VAL_KIND_NULL);
  }
  
  protected function visit_true_lit($node) 
  {
    $this->value = new Value(VAL_KIND_BOOL, true);  
  }
  
  protected function visit_false_lit($node) 
  {
    $this->value = new Value(VAL_KIND_BOOL, false);  
  }
  
  protected function visit_engine_const($node) 
  {
    // TODO: reduce if possible
    $this->value = new Value(VAL_KIND_UNKNOWN);
  }
  
  protected function visit_str_lit($node) 
  {
    $this->value = new Value(VAL_KIND_STR, $node->value);
  }
  
  protected function visit_type_id($node)
  {
    $this->value = new Value(VAL_KIND_TYPE, $node->type); 
  }
    
  /* ------------------------------------ */
    
  /**
   * error handler
   * 
   */
  public function error_at()
  {
    $args = func_get_args();    
    $loc = array_shift($args);
    $lvl = array_shift($args);
    $msg = array_shift($args);
    
    $this->ctx->verror_at($loc, COM_ANL, $lvl, $msg, $args);
  }
}
