<?php

namespace phs;

require_once 'glob.php';
require_once 'util/set.php';

use phs\ast\Unit;

use phs\util\Set;
use phs\util\LooseSet;

/** abstract base class */
abstract class Source
{
  // @var Location
  public $loc = null;
  
  // @var Unit  the parsed unit for this source
  public $unit;
  
  // @var string root-directory
  private $root;
  
  // @var string  temporary path
  private $temp;
  
  // @var string  relative destination in the bundle
  protected $dest;
  
  /**
   * should return all possible (sub-) sources.
   *
   * @return Iterable
   */
  public function iter()
  {
    // return self, no sub-sources
    yield $this;
  }
  
  /**
   * checks if this source can be used
   *
   * @return boolean
   */
  public function check()
  {
    return true;
  }
  
  /**
   * return the-root path generated from this source
   *
   * @return string
   */
  public function use_root()
  {
    return dirname($this->get_path());
  }
  
  /**
   * ets the root-path of this source
   *
   * @param string $root
   */
  public function set_root($root)
  {
    $this->root = $root;
  }
  
  /**
   * returns the root-path of this source
   * 
   * @return string
   */
  public function get_root()
  {
    return $this->root;
  }
  
  /**
   * returns the relative path (seen from its root)
   *
   * @return string
   */
  public function get_rpath()
  {
    $path = $this->get_path();
    
    if (strpos($path, $this->root) === 0)
      $path = substr($path, strlen($this->root) + 1);
    
    return $path;
  }
  
  /**
   * should return the name/path of this source
   * 
   * @return string
   */
  abstract public function get_path();
  
  /**
   * should return the text/data of this source
   * 
   * @return string
   */
  abstract public function get_data();
  
  /**
   * returns the destination path for this source
   * 
   * @return string
   */
  public function get_dest()
  {
    if ($this->dest === null) {
      $root = $this->get_root();
      $path = $this->get_path();
      
      // this must be true, if not = error in Session/Bundle
      assert(strpos($path, $root) === 0);
      
      $path = ltrim(substr($path, strlen($root)), '\\/');
      
      if (substr($path, -4) === '.phs')
        $path = substr($path, 0, -3) . 'php';
      
      $this->dest = $path;
    }
    
    return $this->dest;
  }
  
  /**
   * returns the temporary output path
   *
   * @return string
   */
  public function get_temp()
  {
    if ($this->temp === null)
      $this->temp = tempnam(getcwd(), '~phsc');
    
    return $this->temp;
  }
  
  /**
   * creates a file or text-source
   * 
   * @param  string $src
   * @return Source
   */
  public static function from($src)
  {
    static $re = '/^(?:(?:[A-Z]|file):[\\/]+|[/])[a-zA-Z_\\\/0-9.,\[\]-]+/';
    
    if (preg_match($re, $src) && is_file($src))
      return new FileSource($src);
    
    return new TextSource($src);
  }
}

/** text source */
class TextSource extends Source
{
  // this text is php-code
  public $php;
  
  // path: can be empty
  private $path;
  
  // the data to compile/inject
  private $data;
  
  /**
   * constructor
   * @param string  $path
   * @param string  $dest
   * @param string  $data
   * @param boolean $php  in case this source is already php-code
   */
  public function __construct($path, $dest, $data, $php = false, Location $loc = null)
  {
    $this->loc = $loc;
    $this->php = $php;
    $this->path = $path;
    $this->dest = $dest;
    $this->data = $data;
  }
  
  /**
   * @see Source#get_path()
   * @return string
   */
  public function get_path() 
  {
    return $this->path;
  }
  
  /**
   * @see Source#get_data()
   * @return string
   */
  public function get_data() 
  {
    return $this->data;
  }
}

/** file source */
class FileSource extends Source
{
  // the file is a php-file
  public $php;
  
  // the path
  private $path;
  
  /**
   * constructor
   * @param string  $path
   * @param string  $dest
   * @param boolean $php
   */
  public function __construct($path, $dest = null, $php = false, Location $loc = null)
  {
    $this->path = realpath($path) ?: $path;
    $this->dest = $dest;
    $this->php = $php;
    $this->loc = $loc;
  }
  
  /**
   * @see Source#check()
   *
   * @return boolean
   */
  public function check()
  {
    if (!is_file($this->path)) {
      Logger::error_at($this->loc, 'file not found: %s', $this->path);
      return false;
    }
    
    return true;
  }
  
  /**
   * @see Source#get_path()
   * @return string
   */
  public function get_path() 
  {
    return $this->path;
  }
  
  /**
   * @see Source#get_data()
   * @return string
   */
  public function get_data()
  {
    if (!is_file($this->path))
      return null;
    
    return file_get_contents($this->path);
  }
}

/* ------------------------------------ */

/** source-set */
class SourceSet extends LooseSet 
{
  /**
   * constructor
   */
  public function __construct()
  {
    parent::__construct();
  }
  
  /**
   * @see Set#check()
   * @param  mixed $val
   * @return boolean
   */
  protected function check($val)
  {
    return $val instanceof Source;
  }
  
  /**
   * @see LooseSet#compare()
   * @param  mixed $a
   * @param  mixed $b
   * @return boolean
   */
  protected function compare($a, $b)
  {
    if ($a === $b) return true;
      
    // TextSource never match
    if ($a instanceof TextSource ||
        $b instanceof TextSource)
      return false;
    
    // compare path
    return $a->get_path() === $b->get_path();
  }
}
