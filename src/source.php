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

/** text source */
class TextSource implements Source
{
  // the text
  private $text;
  
  // the name
  private $name;
  
  // the destination
  private $dest;
  
  public function __construct($text, $name = '<unknown source>', $dest = null)
  {
    $this->text = $text;
    $this->name = $name;  
    $this->dest = $dest;
  }
  
  public function get_name() 
  {
    return $this->name;
  }
  
  public function get_text()
  {
    return $this->text;
  }
  
  public function get_dest()
  {
    if (!$this->dest)
      $this->dest = tempnam(getcwd(), 'out');
    
    return $this->dest;
  }
}

/** file source */
class FileSource implements Source
{
  // the path
  private $path;
  
  // the destination
  private $dest;
  
  public function __construct($path, $dest = null)
  {
    $this->path = realpath($path) ?: $path;
    $this->dest = $dest;
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
      $dir = dirname($this->path);
      $nam = basename($this->path, '.phs');
      
      do {
        if ($try > 10) {
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
        $this->dest = "$dir/out-$cnt.php";
        
      } while (is_file($this->dest));
    }
    
    return $this->dest;
  }
}
