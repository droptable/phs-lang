<?php

namespace phs;

require_once 'utils.php';
require_once 'walker.php';
require_once 'symbol.php';
require_once 'reducer.php';

use phs\ast\Unit;
use phs\ast\Name;

class Analyzer extends Walker 
{
  // context
  private $ctx;
  
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
  
  /**
   * constructor
   * 
   * @param Context $ctx
   */
  public function __construct(Context $ctx)
  {
    parent::__construct($ctx);
    $this->ctx = $ctx;
    $this->rdc = new Reducer($this->ctx);
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
    $base = $this->ctx->get_module();
    return $this->handle_import($base, $node->item);
  }
  
  protected function visit_enum_decl($node) 
  {
    $flags = SYM_FLAG_CONST | SYM_FLAG_FINAL;
    
    if ($node->mods) {
      if (!$this->check_mods($node->mods, false, false))
        return $this->drop();
      
      $flags = mods_to_symflags($node->mods, $flags);
    }
    
    foreach ($node->members as $member) {
      if ($member->dest->kind() !== 'ident') {
        $this->error_at($member->loc, ERR_ERROR, 'destructors are not allowed in enum-declarations');
        continue; 
      }
      
      $id = ident_to_str($member->dest);
      
      if ($member->init !== null) {
        $init = $member->init;
        
        // if its a simple value, create a ValueSym
        // otherwise create a VarSym
        switch ($init->kind()) {
          case 'lnum_lit':
          case 'dnum_lit':
          case 'snum_lit':
          case 'null_lit':
          case 'true_lit':
          case 'false_lit':
          case 'str_lit':
          case 'engine_const':
            $sym = new ValueSym($id, $flags, $init, $member->loc);
            break;
          default:
            // must be reducible in a later state
            $sym = new VarSym($id, $flags, $init, $member->loc);
        }
      } else
        $sym = new EmptySym($id, $flags, $member->loc);
      
      $this->add_symbol($id, $sym);
    }
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
      
    $base = null;
    
    if ($node->ext !== null)
      if (!$this->check_class_ext($node->ext))
        return $this->drop();
      
    if ($node->impl !== null)
      if (!$this->check_class_impl($node->impl))
        return $this->drop();
        
    $cid = ident_to_str($node->id);
    $sym = new ClassSym($cid, $flags, $node->loc);
    
    if (!$this->add_symbol($cid, $sym))
      return $this->drop();
    
    array_push($this->sstack, $this->scope);
    $node->scope = new ClassScope($sym, $this->scope);
    $this->scope = $node->scope;
  }
  
  protected function leave_class_decl($node)
  {
    // back to prev scope
    $this->scope = array_pop($this->sstack);
  }
  
  protected function enter_nested_mods($node)
  {
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
    $apppf = $this->scope instanceof ClassScope;
    
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
    
    if (!($flags & SYM_FLAG_EXTERN) && $node->body === null)
      $flags |= SYM_FLAG_INCOMPLETE;
    
    if ($node->params !== null)
      if (!$this->check_params($node->params, false))
        return $this->drop();
    
    $fid = ident_to_str($node->id);
    $sym = new FnSym($fid, $flags, $node->loc);
    
    if (!$this->add_symbol($fid, $sym))
      return $this->drop();
    
    array_push($this->sstack, $this->scope);
    $node->scope = new FnScope($sym, $this->scope);
    $this->scope = $node->scope;
    
    // backup flags
    array_push($this->fstack, $this->flags);
    $this->flags = SYM_FLAG_NONE;
    
    // just for debugging
    $sym->fn_scope = $this->scope;
    
    if ($node->params !== null)
      $this->handle_params($node->params);
  }
  
  protected function leave_fn_decl($node)
  {
    // back to prev scope
    $this->scope = array_pop($this->sstack);
    
    // restore flags
    $this->flags = array_pop($this->fstack);
  }
  
  protected function visit_let_decl($node)
  {
    return $this->visit_var_decl($node);
  }
  
  protected function visit_var_decl($node)
  {
    $flags = SYM_FLAG_NONE;
    $apppf = $this->scope instanceof ClassScope;
    
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
    $path = $this->reduce($node->expr);
    
    if ($path === null)
      exit("not a constant expression");
    
    exit("require {$path->value}");
  }
  
  /* ------------------------------------ */
    
  /**
   * adds the given parameters to the current scope
   * 
   * @param  array $params
   */
  protected function handle_params($params)
  {
    foreach ($params as $param) {
      $kind = $param->kind();
      
      if ($kind === 'param' || $kind === 'rest_param') {
        $flags = SYM_FLAG_NONE;
        
        if ($param->mods !== null) {
          if (!$this->check_mods($param->mods))
            continue;
          
          $flags = mods_to_symflags($param->mods, $flags);
        }
        
        $pid = ident_to_str($param->id);
        $sym = new VarSym($pid, $flags, $param->loc);
        
        $this->add_symbol($pid, $sym);  
      }
    }
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
        $val = $this->reduce($init);
        $sym = new VarSym($vid, $val, $flags, $var->loc);
        return $this->add_symbol($vid, $sym);
      case 'obj_destr':
        return $this->handle_var_obj($var, $flags);
      case 'arr_destr':
        return $this->handle_var_arr($var, $flags);
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
   * checks a class-extend
   * 
   * @param  Name $ext
   * @return boolean
   */
  protected function check_class_ext($ext)
  {    
    $eid = ident_to_str($ext->base);
    $scp = $ext->root ? $this->ctx->get_scope() : $this->scope;
    $sym = $scp->get($eid, false, null, true);
    
    if ($sym === null) {
      // the referenced symbol could be a module without a use-import
      // check again
      $scp = $ext->root || !$this->module ? $this->ctx->get_module() : $this->module;
      $arr = name_to_stra($ext);
      $eid = array_pop($arr);
      $mod = $scp->fetch($arr);
      
      if ($mod->has_child($eid)) {
        $this->error_at($ext->loc, ERR_ERROR, 'module %s::%s can not be used as a class', $mod->path(true), $eid);
        return false;
      }
      
      $sym = $mod->get($eid, false, null, false);
      
      if ($sym === null) {
        $this->error_at($ext->loc, ERR_ERROR, 'undefined reference to %s (used as class)', path_to_str($ext));
        return false;
      }
    }
    
    if ($sym->kind === REF_KIND_MODULE && $sym->flags & SYM_FLAG_WEAK) {
      // modifiy symbol to class-ref
      $this->error_at($ext->loc, ERR_INFO, 'weak module-ref transformed to fixed class-ref');
      $sym->kind = SYM_KIND_CLASS;
      $sym->flags &= ~SYM_FLAG_WEAK;
    }
    
    if ($sym->kind !== REF_KIND_CLASS && $sym->kind !== SYM_KIND_CLASS) {
      $kind = $sym instanceof SymRef ? refkind_to_str($sym->kind) : symkind_to_str($sym->kind);
      $this->error_at($ext->loc, ERR_ERROR, '%s used as class', $kind);
      return $this->drop();
    }
    
    return true;
  }
  
  /**
   * checks a class-implement
   * 
   * @param  array $impl
   * @return boolean
   */
  protected function check_class_impl($impl)
  {
    foreach ($impl as $imp) {
      
    }
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
        return $this->check_class_members($mem->members, $flags, $ext);
      
      elseif ($ext) {
        $this->error_at($mem->loc, ERR_ERROR, 'invalid member in extern class');
        $this->error_at($mem->loc, ERR_INFO, 'only abstract functions are allowed');
        $error = true;
      }
    }
    
    return !$error;
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
    
  private function reduce($expr = null)
  {
    if ($expr === null)
      return new Value(VAL_KIND_NULL);
      
    return $this->rdc->reduce($expr, $this->scope, $this->module);
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
