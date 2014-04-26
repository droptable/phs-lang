<?php

namespace phs\front;

require_once 'utils.php';
require_once 'walker.php';
require_once 'scope.php';
require_once 'symbols.php';

use phs\Config;
use phs\Logger;
use phs\Session;

use phs\util\Map;
use phs\util\Set;

use phs\front\ast\Node;
use phs\front\ast\Unit;

use phs\front\ast\Name;
use phs\front\ast\Ident;
use phs\front\ast\UseAlias;
use phs\front\ast\UseUnpack;

const DS = DIRECTORY_SEPARATOR;

class Import
{
  // the location where this import was found
  // -> the location of the given name (or item) is used here
  public $loc;
  
  // the name of the imported symbol
  public $item;
  
  // the original name of the imported symbol
  public $orig;
  
  // the path of the imported symbol
  public $path;
  
  /**
   * constructor
   * 
   * @param Name      $name
   * @param UseImport $base a other imported symbol for a relative import
   * @param Ident     $item a user-defined name (alias)
   */
  public function __construct(Name $name, Import $base = null, Ident $item = null)
  {        
    $narr = name_to_arr($name);
        
    // replace alias to get the real path
    if ($base && $base->orig !== $base->item)
      array_splice($narr, 0, 1, $base->orig);
        
    $this->loc = $item ? $item->loc : $name->loc;
    $this->orig = array_pop($narr);
    $this->item = $item ? ident_to_str($item) : $this->orig;
    $this->path = $base ? $base->path : [];
    
    // remove symbol-name from base-path
    if ($base) array_pop($this->path);
    
    foreach ($narr as $npth)
      $this->path[] = $npth;
    
    // push symbol-name to get the complete path
    $this->path[] = $this->orig;
  }
}

class ImportMap extends Map
{
  /**
   * constructor
   */
  public function __construct()
  {
    parent::__construct();
  }
  
  /**
   * adds a imported symbol
   * 
   * @see Map#add()
   */
  public function add($key, $val)
  {
    assert($val instanceof Import);
    return parent::add($key, $val);
  }
  
  /**
   * assignes or overrides a imported symbol
   * 
   * @param string    $item
   * @param UseImport $imp
   */
  public function set($key, $val)
  {
    assert($val instanceof Import);
    parent::set($key, $val);
  }
}

/**
 * this walker collects imports
 */
class ImportCollector extends Walker
{
  // session
  private $sess;
  
  // import-map
  private $uimap;
  
  // import map in nested imports
  private $uinst = null;
  
  // import nested stack
  private $nstst = [];
  
  /**
   * constructor
   * 
   * @param Session $sess
   */
  public function __construct(Session $sess)
  {
    // init walker
    parent::__construct([
      'enter' => [ 'unit', 'module', 'program' ],
      'visit' => [ 'use_decl' ]  
    ]);
    
    $this->sess = $sess;
  }
  
  /**
   * collector entry-point
   * 
   * @param  Unit    $unit
   * @return UseImportMap
   */
  public function collect(Unit $unit)
  {    
    $this->uimap = new ImportMap;
    $this->walk($unit);
    return $this->uimap;
  }
  
  /**
   * adds a use-import to the map
   * 
   * @param Import $uimp
   */
  protected function add_use_import(Import $uimp)
  {
    $item = $uimp->item;
    
    if ($this->uimap->add($item, $uimp)) {
      Logger::debug_at($uimp->loc, 'import %s as `%s`', implode('::', $uimp->path), $item);
      
      // add it to the nested map too
      if ($this->uinst) $this->uinst->add($item, $uimp);
      
      return true;
    }
    
    Logger::error_at($uimp->loc, 'duplicate import of a symbol named `%s`', $item);
    Logger::error_at($this->uimap->get($item)->loc, 'previous import was here');
    
    // mark the session itself as invalid and abort as soon as possible
    $this->sess->abort = true;
    
    return false;
  }
  
  /* ------------------------------------ */
  
  /**
   * fetches the base import for a name
   * 
   * @param  Name $name
   * @return Import or null
   */
  protected function fetch_use_base(Name $name, Import $base = null)
  {
    $root = ident_to_str($name->base);
    
    // check nested imports first
    if ($this->uinst !== null) {
      if ($this->uinst->has($root))
        $base = $this->uinst->get($root);
    
    // check global imports
    } elseif ($this->uimap->has($root))
      $base = $this->uimap->get($root);
    
    return $base;
  }
  
  /* ------------------------------------ */
  
  /**
   * walker-method
   * 
   * @see Walker#visit_use_decl
   * @param  Node $node
   */
  protected function visit_use_decl($node)
  {
    $this->handle_import(null, $node->item);
  }
  
  /* ------------------------------------ */
  
  /**
   * handles a import
   * 
   * @param  UseImport $base (optional)
   * @param  Name|UseAlias|UseUnpack $item
   */
  protected function handle_import($base, $item)
  {
    if ($item instanceof Name) 
      $this->handle_use_name($base, $item);
    elseif ($item instanceof UseAlias)
      $this->handle_use_alias($base, $item);
    elseif ($item instanceof UseUnpack)
      $this->handle_use_unpack($base, $item);
    else
      assert(0);
  }
  
  /* ------------------------------------ */
  
  /**
   * handles a simple use-import `use foo::bar;`
   * 
   * @param  UseImport $base (optional)
   * @param  Name $item
   * @param  boolean $sealed do not lookup aliases
   */
  protected function handle_use_name($base, $item)
  {    
    $base = $this->fetch_use_base($item, $base);    
    $uimp = new Import($item, $base);
    
    $this->add_use_import($uimp);
  }
  
  /**
   * handles a simple use-import with alias `use foo::bar as baz;`
   * 
   * @param  UseImport $base (optional)
   * @param  UseAlias $item
   */
  protected function handle_use_alias($base, $item)
  {    
    $base = $this->fetch_use_base($item->name, $base);
    $uimp = new Import($item->name, $base, $item->alias);
    
    $this->add_use_import($uimp);
  }
  
  /**
   * handles complex use-imports 
   * 
   * @param  UseImport $base (optional)
   * @param  UseAlias $item
   */
  protected function handle_use_unpack($base, $item)
  {
    if ($item->base !== null) {
      $base = $this->fetch_use_base($item->base, $base);
      $base = new Import($item->base, $base);
      
      // TODO: this is a workaround...
      // 
      // push $base->orig again because UseImport()
      // will pop it off assuming that the base-path was from a actual 
      // imported symbol.
      // 
      // it would be better to segment UseImport using a 'UsePath',
      // but it works for now ...
      $base->path[] = $base->orig;
    }
    
    // push nested imports onto the stack and create a new map
    array_push($this->nstst, $this->uinst);
    $this->uinst = new ImportMap;
    
    foreach ($item->items as $nimp)
      $this->handle_import($base, $nimp);
    
    // pop previous nested imports of the stack
    $this->uinst = array_pop($this->nstst);
  }
}

/* ------------------------------------ */

/** common interface fpr exports */
interface Export {}

/** module-export */
class ModuleExport implements Export
{
  // location
  public $loc;
  
  // symbol name (ident)
  public $name;
  
  // @var ExportMap
  public $emap;
  
  /**
   * constructor
   * 
   * @param Location $loc
   * @param Ident   $name
   */
  public function __construct(Location $loc, Ident $name)
  {
    $this->loc = $loc;
    $this->name = ident_to_str($name);
    $this->emap = new ExportMap;
  }
}

/** symbol export (function/class/iface/trait/variable) */
class SymbolExport implements Export
{
  // location
  public $loc;
  
  // symbol name
  public $name;
  
  /**
   * constructor
   * 
   * @param Location $loc
   * @param Ident   $name
   */
  public function __construct(Location $loc, Ident $name)
  {
    $this->loc = $loc;
    $this->name = ident_to_str($name);
  }
}

/** export map */
class ExportMap extends Map
{
  /**
   * constructor
   */
  public function __construct()
  {
    parent::__construct();
  }
  
  /**
   * add a export
   * 
   * @see Map#add()
   */
  public function add($key, $val)
  {
    assert($val instanceof Export);
    return parent::add($key, $val);
  }
  
  /**
   * add/set a export
   * 
   * @see Map#set()
   */
  public function set($key, $val)
  {
    assert($val instanceof Export);
    parent::set($key, $val);
  }
}

class ExportCollector extends Walker
{
  // session
  private $sess;
  
  // export map
  private $emap;
  
  // global export map
  private $gmap;
  
  // export stack used for nested modules
  private $emst;
  
  /**
   * constructor
   * 
   * @param Session $sess
   */
  public function __construct(Session $sess)
  {
    // init walker
    parent::__construct([
      'skip' => [ 'block' ], // do not enter blocks
      'enter' => [ // toplevel only
        'unit', 'module', 'program', 'fn_decl', 
        'class_decl', 'iface_decl', 'trait_decl' 
      ],
      'visit' => [ 'enum_decl' ] // only enums are related
    ]);
    
    $this->sess = $sess;
  }
  
  /**
   * starts the collection
   * 
   * @param  Unit   $unit
   * @return ExportMap
   */
  public function collect(Unit $unit)
  {
    $this->emap = new ExportMap;
    $this->gmap = $this->emap; 
    $this->emst = [];
    $this->walk($unit);
    return $this->emap;
  }
  
  /* ------------------------------------ */
  
  /**
   * adds a export-symbol to the current export-map
   * 
   * @param Export $exp
   */
  protected function add_export(Export $exp)
  {
    // do not report errors, because different 
    // symbol-namespaces are not yet used here. 
    
    // two or more exports with the same name can be legal 
    // (gets checked in a later state)
    
    if ($this->emap->add($exp->name, $exp))
      Logger::debug_at($exp->loc, 'export %s', $exp->name);
    
    return true;
  }
  
  /**
   * simple check if `private` was used as modifier.
   * 
   * @param  Node  $node
   * @return boolean
   */
  protected function is_public($node)
  {
    // return early if no modifiers are set
    if (!$node->mods) return true;
    
    foreach ($node->mods as $mod)
      if ($mod->type === T_PRIVATE)
        return false;
      
    // no `private` modifier
    return true;
  }
  
  /* ------------------------------------ */
  
  protected function enter_module($node)
  {
    // handle nested modules
    if ($node->name) {
      // each part of the module-name is a own export
      $name = $node->name;
      $root = ident_to_str($name->base);
      
      $mod = null;
      $loc = $node->loc;
      
      // check if the module already exists in the current export-map
      if ($this->emap->has($root))
        // reuse it
        $mod = $this->emap->get($root);
      else {
        // create a new module-export
        $mod = new ModuleExport($loc, $name->base);
        // add the root-module to the current export-map
        $this->add_export($mod);
      }
      
      // push the current export-map
      array_push($this->emst, $this->emap);
      $this->exmap = $mod->exmap;
      
      if ($name->parts) {
        // the last generated module-export will be our new export-map
        foreach ($name->parts as $part) {
          // check again if this sub-module already exists
          $mid = ident_to_str($part);
          $mod = null;
          
          if ($this->emap->has($mid))
            $mod = $this->emap->get($mid);
          else {
            // create a new module-export
            $mod = new ModuleExport($loc, $part);
            // add it to the previous module
            $this->add_export($mod);
          }
          
          // we do not push the previous export-map here,
          // just update the reference. the previous map is not longer needed.
          $this->emap = $mod->emap;
        }
      }   
    } else {
      // this is not a real module
      // just switch back to the global export-map
      array_push($this->emst, $this->emap);
      $this->emap = $this->gmap;
    }
  }
  
  protected function leave_module($node)
  {
    // switch back to previous export-map
    $this->emap = array_pop($this->emst);
  }
  
  protected function enter_fn_decl($node)
  {
    if ($this->is_public($node))
      // simply add it
      $this->add_export(new SymbolExport($node->loc, $node->id));
    
    // do not enter the function-body
    return $this->drop();  
  }
  
  // no leave_fn_decl() needed
  
  protected function enter_class_decl($node)
  {
    if ($this->is_public($node))
      // simply add it
      $this->add_export(new SymbolExport($node->loc, $node->id));
    
    // do not enter
    return $this->drop();
  }
  
  // no leave_class_decl() needed
  
  protected function enter_iface_decl($node)
  {
    if ($this->is_public($node))
      // simply add it
      $this->add_export(new SymbolExport($node->loc, $node->id));
    
    // do not enter
    return $this->drop();
  }
  
  // no leave_iface_decl() needed
  
  protected function enter_trait_decl($node)
  {
    if ($this->is_public($node))
      // simply add it
      $this->add_export(new SymbolExport($node->loc, $node->id));
    
    // do not enter
    return $this->drop();
  } 
  
  // no leave_trait_decl() needed
  
  protected function visit_enum_decl($node)
  {
    if ($this->is_public($node) && $node->members)
      // symbol is public and has members
      foreach ($node->members as $em)
        // add member
        $this->add_export(new SymbolExport($em->loc, $em->id));
  }
}

/* ------------------------------------ */

/** unit analysis */
class Analysis
{
  // @Var ImportMap
  public $imap;
  
  // @var ExportMap
  public $emap;
  
  /**
   * constructor
   * 
   * @param ImportMap $imap
   * @param ExportMap $emap
   */
  public function __construct(ImportMap $imap, 
                              ExportMap $emap)
  {
    $this->imap = $imap;
    $this->emap = $emap;
  }
}

/** analyzer */
class Analyzer
{
  // session
  private $sess;
  
  // import collector
  private $icol;
  
  // export collector
  private $ecol;
  
  /**
   * constructor
   * 
   * @param Session $sess
   */
  public function __construct(Session $sess)
  {
    $this->sess = $sess;
    $this->icol = new ImportCollector($this->sess);
    $this->ecol = new ExportCollector($this->sess);
  }
  
  /**
   * starts the analyzer
   * 
   * @param  Unit   $unit
   * @return Harbour
   */
  public function analyze(Unit $unit)
  {
    // collect imports
    $imap = $this->icol->collect($unit);
    // collect exports
    $emap = $this->ecol->collect($unit);    
    
    // return the import/export maps
    return new Analysis($imap, $emap);
  }
}
