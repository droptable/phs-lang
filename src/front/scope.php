<?php

namespace phs\front;

use phs\Logger;
use phs\Session;

require_once 'symbols.php';

/** scope */
class Scope extends SymbolMap
{
  // @var int  unique id
  public $uid;
  
  // @var boolean
  public $root;
  
  // @var Scope  parent scope
  public $prev;
  
  // unique id counter
  private static $uidcnt = 0;
  
  // access constants for the assigment-handler
  const MADD = 1, MPUT = 2;
  
  /**
   * constructor
   * 
   * @param Scope $prev
   */
  public function __construct(Scope $prev = null)
  {
    parent::__construct();
    $this->uid = self::$uidcnt++;
    $this->prev = $prev;
    $this->root = $prev === null; // <- no parent scope
  }
  
  /**
   * returns a symbol
   * 
   * @param  string $key
   * @return Symbol
   */
  public function get($key, $ns = -1)
  {
    $sym = parent::get($key, $ns);
    
    if ($sym === null && $this->prev)
      $sym = $this->prev->get($key, $ns);
    
    return $sym;
  }
  
  /**
   * add symbol
   * 
   * @param Symbol $sym
   * @return boolean
   */
  public function add(Symbol $sym)
  {
    Logger::debug_at($sym->loc, 'adding symbol "%s"', $sym->id);
    $prv = $this->get($sym->id, $sym->ns);
    
    if ($prv !== null) {
      // incomplete
      if ($prv->flags & SYM_FLAG_INCOMPLETE) {
        // must be the same kind of symbol
        if ($sym->kind !== $prv->kind) {
          Logger::error_at($sym->loc, 'refinement type mismatch');
          Logger::error_at($prv->loc, 'incomplete declaration was here');
          return false;
        }
        
        // a incomplete symbol must not replace an 
        // existing incomplete symbol in the same scope-chain
        if ($sym->flags & SYM_FLAG_INCOMPLETE)
          // do nothing in this case
          return true;
        
        // check flags/modifiers
        if ($sym->flags !== SYM_FLAG_NONE && 
            $sym->flags !== ($prv->flags ^ SYM_FLAG_INCOMPLETE)) {
          Logger::error_at($sym->loc, 'refinement modifier(s) mismatch');
          Logger::error_at($prv->loc, 'incomplete declaration was here');
          return false;
        }

        // merge flags
        $sym->flags |= $prv->flags;
        $sym->flags &= ~SYM_FLAG_INCOMPLETE;
        
        // replace previous symbol
        $prv->scope->put($sym);
        return true;
      }
      
      // shadowing of final smbol
      if ($prv->flags & SYM_FLAG_FINAL) {
        Logger::error_at($sym->loc, 'override of final symbol `%s`', $sym->id);
        Logger::error_at($prv->loc, 'previous symbol was here');
        return false;
      }
      
      // override check
      if ($prv->scope === $this) {
        Logger::error_at($sym->loc, 'redefinition of symbol `%s`', $sym->id);
        Logger::error_at($prv->loc, 'previous symbol was here');
        return false;
      }
    }
    
    $res = parent::add($sym);
    assert($res); // must be true, see "override check"
    
    $sym->scope = $this;
    return true;
  }
  
  /**
   * put a symbol.
   * warning: does not check anything, always prefer add() !!!
   * 
   * @param Symbol $sym
   * @return Symbol  the previous symbol
   */
  public function put(Symbol $sym)
  {
    $prv = parent::put($sym);
    $sym->scope = $this;
      
    if ($prv !== null) 
      $prv->scope = null;
      
    return $prv;
  }
}

// class 'Module' requires class 'Scope' to be defined
require_once 'module.php';

/** unit scope */
class UnitScope extends Scope
{
  // @var ModuleMap  modules
  public $mmap;
  
  /**
   * constructor
   *    
   */
  public function __construct()
  {
    parent::__construct(null);
    $this->mmap = new ModuleMap;
  }
}
