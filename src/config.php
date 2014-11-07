<?php

namespace phs;

require_once 'util/dict.php';

use phs\util\Dict;

/** config */
final class Config extends Dict
{
  // @var string  output-dir
  public $dir;
  
  // @var string  output-file
  public $out;
  
  // @var string  the unit is a module
  public $mod;
  
  // @var bool  no-runtime flag
  public $nort;
  
  // @var bool  no-stdlib flag
  public $nostd;
  
  // @var bool  shutup flag
  public $quiet;
  
  // @var bool  report warnings as errors
  public $werror;
  
  // @var bool  run program after compilation
  public $run;
  
  // @var bool  re-format source
  public $format;
  
  // @var bool  check sources only
  public $check;
  
  // @var bool  show version and exit
  public $version;
    
  // @var string  mangle-method
  public $mangle;
  
  // @var array  library include-paths
  public $lib_paths;
  
  // @var bool  force ansi-logger
  public $log_ansi;
  
  // @var string  log-output
  public $log_dest;
  
  // @var bool  log timings
  public $log_time;
  
  // @var int  log-line-width
  public $log_width;
  
  // @var int  log-level
  public $log_level;
  
  // @var string  pack-method
  public $pack;
  
  // @var string  stub-method
  public $stub;
  
  /**
   * constructor
   *
   */
  public function __construct()
  {
    // super
    parent::__construct();
  }
  
  /**
   * sets some default config-values
   *
   */
  public function set_defaults()
  {
    $this->dir = null;
    $this->out = 'a.zip';
    $this->mod = false;
    $this->nort = false;
    $this->nostd = false;
    $this->quiet = false;
    $this->werror = false;
    $this->run = false;
    $this->format = false;
    $this->check = false;
    $this->version = false;
    $this->mangle = 'all';
    $this->lib_paths = [ realpath(__DIR__ . '/../lib') ]; // install dir
    $this->log_dest = null; // stderr
    $this->log_time = false;
    $this->log_width = 80;
    $this->log_level = LOG_LEVEL_WARNING;
    $this->pack = null; // defaults to "zip"
    $this->stub = null; // defaults to "none"
  }
}
