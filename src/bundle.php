<?php

namespace phs;

use Phar;
use ZipArchive;

/** bundle wrapper for phar files */
class Bundle
{
  // @var Session
  private $sess;
  
  // @var array  sources for the lib-folder
  private $libs;
  
  // @var array  sources for the src-folder
  private $srcs;
  
  /**
   * constructor
   *
   * @param Session $sess
   */
  public function __construct(Session $sess)
  {
    $this->sess = $sess;
    $this->libs = [];
    $this->srcs = [];
  }
  
  /**
   * adds a file for the src-folder
   *
   * @param Source $src
   */
  public function add_source(Source $src)
  {
    $this->srcs[] = $src;
  }
  
  /**
   * adds a file for the lib-folder
   *
   * @param Source $lib
   */
  public function add_library(Source $lib)
  {
    $this->libs[] = $lib;
  }
  
  /**
   * generates the phar
   *
   */
  public function deploy()
  {
    if (file_exists('out.phar')) unlink('out.phar');
    if (file_exists('out.zip')) unlink('out.zip');
    
    $phar = new Phar('out.phar');
    $pzip = new ZipArchive;
    $pzip->open('out.zip', ZipArchive::CREATE);
    
    // setup directories
    $phar->addEmptyDir('lib');
    $phar->addEmptyDir('src');
    
    $pzip->addEmptyDir('lib');
    $pzip->addEmptyDir('src');
    
    // add libraries
    foreach ($this->libs as $lib) {
      $path = $lib->get_dest();
      $temp = $lib->get_temp();
      
      $phar->addFile($temp, "lib/$path");
      $pzip->addFile($temp, "lib/$path");
    }
    
    // add sources
    foreach ($this->srcs as $src) {
      $path = $src->get_dest();
      $temp = $src->get_temp();
      
      $phar->addFile($temp, "src/$path");
      $pzip->addFile($temp, "src/$path");
    }
        
    // generate phar stub
    $main = $this->sess->main->get_dest();
    $stub = "#!/usr/bin/env php\n";
    $stub .= "<?php\n";
    $stub .= "Phar::mapPhar('phs');\n";
    
    // include libraries
    foreach ($this->libs as $lib)
      if ($lib->import === false) {
        $path = $lib->get_dest();
        $stub .= "require_once 'phar://phs/lib/$path';\n";
      }
      
    $stub .= "require_once 'phar://phs/src/$main';\n";
    $stub .= "__HALT_COMPILER();";
    $phar->setStub($stub);
    
    $pzip->addFromString('stub.php', $stub);
    $pzip->close();
  }
  
  /**
   * deletes all temporary files
   *
   */
  public function cleanup()
  {
    foreach ($this->libs as $lib) {
      $temp = $lib->get_temp();
      
      if (file_exists($temp))
        unlink($temp);
    }
    
    foreach ($this->srcs as $src) {
      $temp = $src->get_temp();
      
      if (file_exists($temp))
        unlink($temp);
    }
  }
}
