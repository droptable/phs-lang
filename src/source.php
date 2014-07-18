<?php

namespace phs;

abstract class Source
{
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
  
  public function __construct($path, $dest, $data, $php = false)
  {
    $this->php = $php;
    $this->path = $path;
    $this->dest = $dest;
    $this->data = $data;
  }
  
  public function get_name()
  {
    TRIGGER_ERROR(__CLASS__ . '::get_name(): use get_path() instead', E_USER_DEPRECATED);
    return $this->path;
  }
  
  public function get_path() 
  {
    return $this->path;
  }
  
  public function get_dest() 
  {
    return $this->dest;
  }
  
  public function get_text()
  {
    TRIGGER_ERROR(__CLASS__ . '::get_text(): use get_data() instead', E_USER_DEPRECATED);
    return $this->data;
  }
  
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
  
  public function __construct($path, $dest = null, $php = false)
  {
    $this->path = realpath($path) ?: $path;
    $this->dest = $dest;
    $this->php = $php;
  }
  
  public function get_name()
  {
    TRIGGER_ERROR(__CLASS__ . '::get_name(): use get_path() instead', E_USER_DEPRECATED);
    return $this->path;
  }
  
  public function get_path() 
  {
    return $this->path;
  }
  
  public function get_text()
  {
    TRIGGER_ERROR(__CLASS__ . '::get_text(): use get_data() instead', E_USER_DEPRECATED);
    return $this->get_data();
  }
  
  public function get_data()
  {
    // no error-checking here!
    return file_get_contents($this->path);
  }
  
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
      
      return $this->dest;
      
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
    
    return $this->dest;
  }
}
