<?php

namespace phs\front;

require_once 'utils.php';
require_once 'visitor.php';
require_once 'symbols.php';
require_once 'scope.php';

// analyzer components
require_once 'lexer.php';
require_once 'parser.php';
require_once 'validate.php';
require_once 'desugar.php';
require_once 'collect.php';
require_once 'reduce.php';
require_once 'resolve.php';

use phs\Config;
use phs\Logger;
use phs\Source;
use phs\Session;
use phs\FileSource;

use phs\util\Set;
use phs\util\Map;
use phs\util\Cell;
use phs\util\Entry;

use phs\front\ast\Node;
use phs\front\ast\Unit;

/** analyzer */
class Analyzer
{
  // @var Session  session
  private $sess;
  
  // ---------------------------------------
  // components
  
  // @var Parser
  private $psr;
  
  // @var UnitDesugarer
  private $udes;
  
  // @var UnitValidator
  private $uval;
  
  // @var UnitCollector
  private $ucol;
  
  // @var UnitReducer
  private $ured;
  
  // @var UnitResolver
  private $ures;
  
  // @var UnitExporter
  private $uexp;
  
  // ---------------------------------------
  
  /**
   * constructor
   * 
   * @param Session $sess
   */
  public function __construct(Session $sess)
  {
    //
    $this->sess = $sess;
    
    // components
    $this->psr = new Parser($this->sess);
    $this->udes = new UnitDesugarer($this->sess);
    $this->uval = new UnitValidator($this->sess);
    $this->ucol = new UnitCollector($this->sess);
    $this->ured = new UnitReducer($this->sess);
    $this->ures = new UnitResolver($this->sess);
    #$this->uexp = new UnitExporter($this->sess);
  }
  
  /**
   * starts the analyzer
   * 
   * @param  Source $src
   * @return UnitScope
   */
  public function analyze(Source $src)
  {    
    Logger::debug('analyze file %s', $src->get_path());
    
    // 1. parse source
    $unit = $this->parse_src($src);
    
    if ($this->sess->aborted)
      goto err;
    
    $tasks = [
      // 2. desugar unit
      function($unit) { $this->desugar_unit($unit); },
      
      // 3. validate unit
      function($unit) { $this->validate_unit($unit); },
      
      // 4. collect classes, interfaces and traits
      // 5. collect functions and variables
      // 6. collect usage
      function($unit) { $this->collect_unit($unit); },
      
      // 7. reduce constant expressions
      function($unit) { $this->reduce_unit($unit); },
            
      // 8. resolve usage and imports
      function($unit) { $this->resolve_unit($unit); },
      
      // 9. optimize unit
      // function($unit) { $this->optimize_unit($unit); },
      
      // 10. export global symbols
      #function($unit) { $this->export_unit($unit); },
    ];
    
    foreach ($tasks as $task) {
      $task($unit);
      
      if ($this->sess->aborted)
        goto err;
    }
    
    // no error
    return $unit;
    
    err:
    unset ($unit);
    gc_collect_cycles();
    return null;
  }
  
  /**
   * parses a source
   *
   * @param  Source $src
   * @return Unit
   */
  protected function parse_src(Source $src)
  {
    return $this->psr->parse($src);
  }
  
  /**
   * validates a unit
   *
   * @param  Unit   $unit
   * @return void
   */
  protected function validate_unit(Unit $unit)
  {
    $this->uval->validate($unit);
  }
  
  /**
   * desugars the unit
   *
   * @param  Unit $unit
   * @return void
   */
  protected function desugar_unit(Unit $unit)
  {
    $this->udes->desugar($unit);
  }
  
  /**
   * collects the unit-scope
   *
   * @param  Unit $unit
   * @return UnitScope
   */
  protected function collect_unit(Unit $unit)
  {
    $this->ucol->collect($unit);
  }
  
  /**
   * resolves the unit
   *
   * @param  Unit   $unit
   * @return void
   */
  protected function resolve_unit(Unit $unit)
  {
    $this->ures->resolve($unit);
  }
  
  /**
   * exports the unit
   *
   * @param  Unit   $unit
   * @return void
   */
  protected function export_unit(Unit $unit)
  {
    $this->uexp->export($unit);
  }
  
  /**
   * reduces the unit 
   *
   * @param  Unit   $unit
   * @return void
   */
  protected function reduce_unit(Unit $unit)
  {
    $this->ured->reduce($unit);
  }
}
