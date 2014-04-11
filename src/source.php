<?php

namespace phs;

interface Source
{
  /**
   * should return the name of this source
   * 
   * @return string
   */
  public function get_name();
  
  /**
   * should return the text/data of this source
   * 
   * @return string
   */
  public function get_text();
  
  /**
   * should return a destination path for this source
   * 
   * @return string
   */
  public function get_dest();
}

/** file source */
class FileSource implements Source
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
    return $this->path;
  }
  
  public function get_text()
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
