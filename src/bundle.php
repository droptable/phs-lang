<?php

namespace phs;

use Phar;
use ZipArchive;

const DS = \DIRECTORY_SEPARATOR;

/**
 * joins a path and replaces "\" to "\\"
 *
 * @param  ...
 * @return string
 */
function join_path() {
  return strtr(implode(DS, func_get_args()), [ '\\' => '\\\\' ]);
}

/** bundle wrapper */
class Bundle
{
  // @var Session
  private $sess;
  
  // @var array  sources for the lib-folder
  public $libs;
  
  // @var array  sources for the src-folder
  public $srcs;
  
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
    switch ($this->sess->conf->pack) {
      default:
      case 'none':
        $pack = new DirPacker($this->sess);
        break;
      case 'zip':
        $pack = new ZipPacker($this->sess);
        break;
      case 'phar':
        $pack = new PharPacker($this->sess);
        break;
    }
    
    $pack->pack($this);
    $pack->save();
  }
  
  /**
   * deletes all temporary files
   *
   */
  public function cleanup()
  {
    foreach ($this->libs as $lib) {
      $temp = $lib->get_temp();
      
      // DirPacker removes temp-files
      if (file_exists($temp))
        unlink($temp);
    }
    
    foreach ($this->srcs as $src) {
      $temp = $src->get_temp();
      
      // DirPacker removes temp-files
      if (file_exists($temp))
        unlink($temp);
    }
  }
}

abstract class Packer 
{
  /**
   * should add a bundle to the packet
   * 
   * @param Bundle $bnd
   */
  abstract public function pack(Bundle $bnd);
  
  /**
   * should save the result
   * 
   */
  abstract public function save();
  
  /**
   * creates a stub-file
   *
   * @param  array $libs
   * @param  Source $main
   * @return string
   */
  public function create_stub($libs, $main) 
  {
    $stub = '<?php';
            
    foreach ($libs as $lib) {
      $dest = join_path('lib', $lib->get_dest());
      $stub .= "\nrequire_once '$dest';";
    }
    
    $main = join_path('src', $main->get_dest());
    $stub .= "\nrequire_once '$main';";
    
    return $stub;
  }
}

/** default packer, --pack 'none'
    moves files to the output-dir, does not pack anything */
class DirPacker extends Packer
{
  // @var Session
  private $sess;
  
  // @var Config
  private $conf;
  
  /**
   * constructor
   *
   * @param Session $sess
   */
  public function __construct(Session $sess)
  {
    $this->sess = $sess;
    $this->conf = $sess->conf;
  }
  
  /**
   * @see Packer#pack()
   *
   * @param  Bundle $bnd
   */
  public function pack(Bundle $bnd)
  {
    $path = $this->conf->dir;
    $mout = $path . DS . $this->conf->out;
    $ldir = $path . DS . 'lib';
    
    if (strtolower(strrchr($mout, '.')) !== '.php')
      $mout .= '.php';
    
    if (!empty ($bnd->libs) && !$this->conf->mod) {
      self::mkdir($ldir);
      
      $dabs = $ldir . DS;
      foreach ($bnd->libs as $lib) {
        $temp = $lib->get_temp();
        $dest = $dabs . $lib->get_dest();
        self::rename($temp, $dest);
      }
    }
    
    $path .= DS;
    
    if (!$this->conf->mod)
      $path .= 'src' . DS;
    
    self::mkdir($path);
    
    // just one file and no stub is required
    // use the -o option here
    if ($this->conf->mod && count($bnd->srcs) === 1) {
      $temp = $bnd->srcs[0]->get_temp();
      self::rename($temp, $mout);
    } else {
      // more files or a stub is required
      foreach ($bnd->srcs as $src) {
        $temp = $src->get_temp();
        $dest = $path . $src->get_dest();
        self::rename($temp, $dest);
      }
      
      if ($this->conf->stub !== 'none') {
        $stub = $this->create_stub($bnd->libs, $this->sess->main);
        file_put_contents($mout, $stub);
      }
    }
  }
  
  /**
   * @see Packer#save()
   * 
   */
  public function save()
  {
    // noop
  }
  
  /**
   * mkdir utility
   *
   * @param  string $dir
   */
  private static function mkdir($dir)
  {
    if (!is_dir($dir))
      mkdir($dir, 0777, true);
  }
  
  /**
   * rename utility
   *
   * @param  string $src
   * @param  string $dst
   */
  private static function rename($src, $dst)
  {
    self::mkdir(dirname($dst));
    rename($src, $dst);
  }
}

/** packer for --pack 'zip' */
class ZipPacker extends Packer
{
  // @var Session
  private $sess;
  
  // @var Config
  private $conf;
  
  // @var ZipArchive
  private $ziph;
  
  /**
   * constructor
   *
   * @param Session $sess
   */
  public function __construct(Session $sess)
  {
    $this->sess = $sess;
    $this->conf = $sess->conf;
    
    // create destination
    $dest = $this->conf->dir . DS . $this->conf->out;
    
    if (strtolower(strrchr($dest, '.')) !== '.zip')
      $dest .= '.zip';
    
    if (is_file($dest)) unlink($dest);
    
    $this->ziph = new ZipArchive;
    $this->ziph->open($dest, ZipArchive::CREATE);
  }
  
  /**
   * @see Packer#pack()
   *
   * @param  Bundle $bnd
   */
  public function pack(Bundle $bnd)
  {
    $mout = $this->conf->out;
    
    if (strtolower(strrchr($mout, '.')) !== '.php')
      $mout .= '.php';
    
    if (!empty ($bnd->libs) && !$this->conf->mod) {
      $this->ziph->addEmptyDir('lib');
      
      foreach ($bnd->libs as $lib) {
        $temp = $lib->get_temp();
        $dest = 'lib/' . $lib->get_dest();
        $this->ziph->addFile($temp, $dest);
      }
    }
    
    $path = '';
    
    if (!$this->conf->mod)
      $path .= 'src/'; 
    
    // just one file and no stub is required
    // use the -o option here
    if ($this->conf->mod && count($bnd->srcs) === 1) {
      $temp = $bnd->srcs[0]->get_temp();
      $this->ziph->addFile($temp, $mout);
    } else {
      // more files or a stub is required
      foreach ($bnd->srcs as $src) {
        $temp = $src->get_temp();
        $dest = $path . $src->get_dest();
        $this->ziph->addFile($temp, $dest);
      }
      
      if ($this->conf->stub !== 'none') {
        $stub = $this->create_stub($bnd->libs, $this->sess->main);
        $this->ziph->addFromString($mout, $stub);
      }
    }
  }
  
  /**
   * @see Packer#save()
   * 
   */
  public function save()
  {
    $this->ziph->close();
  }
}

/** packer for --pack 'phar' */
class PharPacker extends Packer
{
  // @var Session
  private $sess;
  
  // @var Config
  private $conf;
  
  // @var ZipArchive
  private $phar;
  
  /**
   * constructor
   *
   * @param Session $sess
   */
  public function __construct(Session $sess)
  {
    $this->sess = $sess;
    $this->conf = $sess->conf;
    
    // create destination
    $dest = $this->conf->dir . DS . $this->conf->out;
    
    if (strtolower(strrchr($dest, '.')) !== '.phar')
      $dest .= '.phar';
    
    if (is_file($dest)) unlink($dest);
    
    $this->phar = new Phar($dest);
  }
  
  /**
   * @see Packer#pack()
   *
   * @param  Bundle $bnd
   */
  public function pack(Bundle $bnd)
  {
    if (!empty ($bnd->libs) && !$this->conf->mod) {
      $this->phar->addEmptyDir('lib');
      
      foreach ($bnd->libs as $lib) {
        $temp = $lib->get_temp();
        $dest = 'lib/' . $lib->get_dest();
        $this->phar->addFile($temp, $dest);
      }
    }
    
    $path = '';
    
    if (!$this->conf->mod)
      $path .= 'src/'; 
    
    // just one file and no stub is required
    // use the -o option here
    if ($this->conf->mod && count($bnd->srcs) === 1) {
      $temp = $bnd->srcs[0]->get_temp();
      $stub = file_get_contents($temp) . "\n__HALT_COMPILER();";
      $this->phar->setStub($stub);
    } else {
      // more files or a stub is required
      foreach ($bnd->srcs as $src) {
        $temp = $src->get_temp();
        $dest = $path . $src->get_dest();
        $this->phar->addFile($temp, $dest);
      }
      
      if ($this->conf->stub !== 'none') {
        $stub = $this->create_stub($bnd->libs, $this->sess->main);
        $this->phar->setStub($stub);
      }
    }
  }
  
  /**
   * @see Packer#save()
   * 
   */
  public function save()
  {
    unset ($this->phar);
  }
  
  /**
   * creates a stub-file
   *
   * @param  array $libs
   * @param  Source $main
   * @return string
   */
  public function create_stub($libs, $main, $pfx = '')
  {
    $stub = '<?php';
    $stub .= "\nPhar::";
    
    if ($this->conf->stub === 'phar-web') {
      $main = join_path('src', $main->get_dest());
      $stub .= "webPhar('phs', '$main');";
    } else
      $stub .= "mapPhar('phs');";
    
    foreach ($libs as $lib) {
      $dest = 'phs/lib/' . strtr($lib->get_dest(), [ '\\' => '/' ]);
      $stub .= "\nrequire_once 'phar://$dest';";
    }
    
    if ($this->conf->stub === 'phar-run') {
      $main = 'phs/src/' . strtr($main->get_dest(), [ '\\' => '/' ]);
      $stub .= "\nrequire_once 'phar://$main';";
    }
    
    $stub .= "\n__HALT_COMPILER();";
    
    return $stub;
  }
}
