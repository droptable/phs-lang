<?php

namespace phs;

require_once 'glob.php';
require_once 'ast.php';
require_once 'scope.php';

// front-end tasks
require_once 'lexer.php';
#require_once 'parser.php';
require_once 'parser-v2.php';
require_once 'validate.php';
require_once 'desugar.php';
require_once 'collect.php';
require_once 'export.php';
require_once 'reduce.php';
require_once 'resolve.php';
require_once 'mangle.php';

require_once 'format.php';
require_once 'codegen.php';

use phs\ast\Unit;

/** a compiler-task */
interface Task 
{
  /**
   * should start the task
   *
   * @param  Unit   $unit
   */
  public function run(Unit $unit);
}

class Compiler
{
  // @var Session
  private $sess;
  
  // @var Parser
  private $parser;
  
  /**
   * constructor
   *
   * @param Session $sess [description]
   */
  public function __construct(Session $sess)
  {
    $this->sess = $sess;
    $this->parser = new Parser($sess);
  }
  
  /**
   * analyzes a source
   * 
   * @param  Source $src
   * @return UnitScope
   */
  public function analyze(Source $src)
  {        
    // 1. parse source
    $unit = $this->parser->parse($src);
    
    if ($this->sess->aborted)
      goto err;
    
    $tasks = [
      // 2. validate unit
      new ValidateTask($this->sess),
      
      // 3. desugar unit
      new DesugarTask($this->sess),
      
      // 4. collect traits
      // 5. collect classes and interfaces
      // 6. collect functions and variables
      // 7. collect usage
      #new CollectTask($this->sess),
      
      // 8. export global symbols
      #new ExportTask($this->sess),
      
      // 9. reduce constant expressions
      new ReduceTask($this->sess),
      
      // 10. resolve usage and imports
      new ResolveTask($this->sess)
    ];
    
    foreach ($tasks as $task) {
      $task->run($unit);
      
      if ($this->sess->aborted)
        goto err;
    }
    
    #!dbg $unit->scope->dump('');
    
    // no error
    goto out;
    
    err:
    unset ($unit);
    $unit = null;
    gc_collect_cycles();
    
    out:
    return $unit;
  }
  
  /**
   * compiles a source
   *
   * @param  Source $src
   */
  public function compile(Source $src)
  {
    if ($src->unit) {
      $unit = $src->unit;
      
      // pre-codegen tasks
      $tasks = [
        // 1. constant propagation
        // new PropagateTask($this->sess),
        
        // 2. mangle symbol names
        new MangleTask($this->sess), 
      ];
      
      foreach ($tasks as $task)
        // no error-handling, all errors should be caught by the analysis-pass
        $task->run($unit);
    }
    
    $cgen = new CodeGenerator($this->sess);
    $cgen->process($src);
  }
}
