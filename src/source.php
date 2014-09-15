<?php

namespace phs;

require_once 'front/glob.php';
require_once 'util/set.php';

use phs\util\Set;
use phs\util\LooseSet;

use phs\front\Location;

interface Origin
{}

/** abstract base class */
abstract class Source
{
  // @var Location
  public $loc = null;
  
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
   * should return a destination path for this source
   * 
   * @return string
   */
  abstract public function get_dest();
  
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
  
  // the destination
  private $dest;
  
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
   * @see Source#get_dest()
   * @return string
   */
  public function get_dest() 
  {
    return $this->dest;
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
  
  // the destination
  private $dest;
  
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
  
  /**
   * @see Source#get_dest()
   * @return string
   */
  public function get_dest()
  {
    if (!$this->dest) {
      $try = 0;
      $stt = 0;
      $nam = basename($this->path, '.phs');
      $dir = dirname($nam);
      
      $this->dest = "$dir/$nam.php";
      
      #if (is_file($this->dest))
        #unlink($this->dest);
      
      goto out;
      
      do {
        if ($try > 1000) {
          if ($stt === 1)
            // TODO: this should be reported via Context#error
            exit('unable to create a temp destination for file ' . $this->path);
          
          // switch state, try current working dir
          $dir = getcwd();
          $stt = 1;
          $try = 0;
        }
        
        $cnt = $nam;
        if ($try > 0) $cnt .= "-$try";
        $this->dest = "$dir/$cnt.php";
        
        ++$try;
      } while (is_file($this->dest));
    }
    
    out:
    return $this->dest;
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
