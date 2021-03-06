#!/usr/bin/env php
<?php

namespace phs;

if (PHP_SAPI !== 'cli')
  exit(__FILE__ . ' is a cli application');

const PHS_DEBUG = true;

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../logger.php';
require_once __DIR__ . '/../source.php';
require_once __DIR__ . '/../session.php';

const FLIBS = 0;
const FSRCS = 1;

const EXIT_SUCCESS = 0;
const EXIT_FAILURE = 1;

/**
 * entry-point
 *
 * @param  int $argc
 * @param  array $argv
 */
function main($argc, $argv) {
  $conf = new Config;
  $conf->set_defaults();
  
  $files = parse_args($conf, $argc, $argv);
  
  if (!check_conf($conf))
    exit("use `phsc -?` for help");
  
  // show version and exit
  if ($conf->version) {
    if ($conf->quiet)
      echo VERSION;
    else
      logo();
    
    exit;
  }
  
  if (empty ($files[FSRCS]))
    exit('nothing to do');
  
  init($conf);
  
  $sess = new Session($conf);
     
  // runtime library
  if (!$conf->nort)
    $sess->add_library_from('run');
  
  // standard library
  if (!$conf->nostd)
    $sess->add_library_from('std');
  
  // user-defined libraries
  foreach ($files[FLIBS] as $lib)
    $sess->add_library_from($lib);
  
  // user-defined sources
  foreach ($files[FSRCS] as $src)
    $sess->add_source_from($src);
  
  if (!$sess->process() && $conf->err)
    exit(EXIT_FAILURE);
  
  if ($conf->run) {
    if (!$conf->quiet) echo "\n";
    if ($conf->pack === 'zip')
      echo "run: cannot execute zip";
    elseif ($conf->stub === 'phar-web')
      echo "run: cannot execute a web phar";
    else {
      $xphp = PHP_BINARY;    
      $main = $conf->dir . DIRECTORY_SEPARATOR . $conf->out;
      switch (strrchr($main, '.')) {
        case '.php':
        case '.phar':
          break;
        default:
          $main .= '.php';
      }
      
      echo `$xphp $main`;
    }
  }
}

main($_SERVER['argc'], $_SERVER['argv']);

/**
 * initializes the compiler-runtime
 *
 * @param  Config $conf
 */
function init(Config $conf) {
  if (PHS_DEBUG === true) {
    // debug setup
    assert_options(ASSERT_ACTIVE, true);
    assert_options(ASSERT_BAIL, true);
    assert_options(ASSERT_CALLBACK, function($s, $l, $c, $m = null) {
      echo "\nassertion failed", $m ? " with message: $m" : '!', "\n";
      echo "\nfile: $s\nline: $l\n", $c ? "code: $c\n" : ''; 
      echo "\nTHIS IS A BUG, PLEASE REPORT IT!\n\n";
      debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
      exit(EXIT_FAILURE);
    });

    set_error_handler(function($n, $s, $f, $l) {
      echo "\nerror: $s ($n)\nfile: $f\nline: $l\n";
      echo "\nTHIS IS A BUG, PLEASE REPORT IT!\n\n";
      debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
      exit(EXIT_FAILURE);
    });
  }
  
  if (!$conf->quiet) 
    logo();
}

/**
 * shows the phs-logo
 *
 */
function logo() {
  $ver = VERSION;
  echo <<<END_LOGO
     ___  __ ______
    / _ \/ // / __/
   / ___/ _  /\ \  
  /_/  /_//_/___/   Version $ver
  
  Copyright (C) 2014 - The PHS Team.
  Report bugs to https://github.com/droptable/phs-lang/issues
    
END_LOGO;
}

/**
 * shows command-line options end exit
 *
 */
function usage($logo = true) {
  if ($logo === true)
    logo();
    
  echo <<<'DOC'

usage: phsc [ options ] file ...
 
options:
 
 -?  --help         Shows this info.
 
 -v  --version      Shows the compiler version.
 
 --nort             Excludes the PHS runtime library.
                    (Use with caution)
 
 --nostd            Excludes the PHS standard library.
 
 -q  --quiet        Supress unnecessary output produced by the compiler.
                    Note: This option gets forwarded to PHP if --run is set.
                          Error-reporting will be set to E_ERROR | E_PARSE
 
 -d                 Output dir. (default=current working dir)
 
 -o                 Output file. (default=a)
                    Note: This option is ignored if --pack is set to 'none'
 
 -m                 Excludes all dependencies from the bundle and does not
                    generate a stub.
 
                    Use this option if you use an external dependency-manager
                    like composer or for analysis or similar purposes.
 
 -s  --strict       Strict-mode:
                      - Disables inline-php code (__php__).
                      - Disables codegen-hacks.
                      - All warnings are treated as errors (see -w option).
 
 -e                 By default, the compiler exits with EXIT_SUCCESS even
                    if the compilation was not successful.
 
                    EXIT_FAILURE is only used for non-script related
                    errors or assertions in the compiler-code itself.
 
                    This option lets the compiler bail-out with EXIT_FAILURE
                    on compilation errors as well.
 
 -w                 Treats all warnings as errors.
 
 -i  --inc          Adds an include-path for libraries.
 
 -l  --lib          Adds a library.
 
 -r  --run          Executes the application after compilation.
                    Note: A PHP >= 5.6 application must be available
                          via `php` command or argv[0].
 
 -c  --check        Checks the syntax of all main-files only.
                    The compiler stops in this state, no bundle will be
                    generated.
 
 -f  --fmt          Parses and re-formats the main-file and dumps it to stdout.
                    The compiler stops in this state, no bundle will be
                    generated.
                    Note: This option accepts only one file at a time.
 
 --log-ansi         Force-enables ANSI console outputs (colors).
                    Note: The logger detects ANSI compatible shells
                          automatically.
 
                          This option is for unknown shells only where YOU know
                          for sure that ANSI colors can be displayed.
 
                    Note: It is not recommended to use this option at
                          the native windows cli.
 
 --log-dest         Sets the log destination. (default=stderr)
                    Possible values: stdout, stderr, {path}
 
 --log-time         Outputs timing-informations for each logging-output.
                    Useful for compiler benchmarks.
 
 --log-width        Sets the maximum line-width for logging-outputs.
 
 --log-level        Sets the log level. (default=warning)
                    Possible values: all, debug, info, warn, error, none
 
 --pack             Sets the bundle-packer. (default=zip)
                    Possible values: none, zip, phar
 
 --stub             Sets the bundle-stub. (default=none)
                    Possible values: none, run, phar-web, phar-run, {path}
                    Note: 'phar-web' and 'phar-run' override --pack to 'phar'
 
                    Option description:
 
                      'none'       Generates no stub at all.
                                   You have to setup the compilation by hand.
 
                      'run'        Generates a small script with all libraries
                                   and the main-files included.
                                   This file will bootstrap your application.
 
                      'cli'        Same as 'run' but with an shebang-line:
                                   #!/usr/bin/env php
 
                      'phar-run'   Generates a phar-stub just like 'run'.
                                   Uses Phar::mapPhar().
 
                      'phar-web'   Generates a phar-stub for the web.
                                   Uses Phar::webPhar().
                                   The main-file will be used as index.php
 
                      {path}       A custom script as stub.
                                   The packer enables two placeholders
                                   for your script:
 
                                     '%libs%'   include libraries.
                                     '%main%'   path to the main-file.
 
                                   Note: The given script must be a PHP-File.
 
                    More info:
                    http://php.net/manual/de/phar.fileformat.stub.php
 
 
examples:
 
  phsc -o my_own_app.phar -l my_own_lib  my_own_app.phs
  php ./my_own_app.phar

DOC;
  exit;
}

/**
 * parses arguments
 * 
 * @param  Config $conf
 * @param  int $argc
 * @param  array $argv
 * @return array
 */
function parse_args(Config $conf, $argc, $argv) {
  $libs = [];
  $srcs = [];
  
  if (in_array('-?', $argv) ||
      in_array('--help', $argv))
    usage();
  
  for ($i = 1; $i < $argc; ++$i) {
    switch ($argv[$i]) {
      case '--nort':
        $conf->nort = true;
        break;
      case '--nostd':
        $conf->nostd = true;
        break;
      case '-q':
      case '-quiet':
        $conf->quiet = true;
        $conf->nologo = true;
        $conf->log_level = LOG_LEVEL_ERROR;
        break;
      case '-m':
        $conf->mod = true;
        break;
      case '-e':
        $conf->err = true;
        break;
      case '-w':
        $conf->werror = true;
        break;
      case '-r':
      case '--run':
        $conf->run = true;
        break;
      case '-f':
      case '--fmt':
        $conf->format = true;
        break;
      case '-c':
        $conf->check = true;
        break;
      case '-v':
      case '--version':
        $conf->version = true;
        break;
      case '--log-ansi':
        $conf->log_ansi = true;
        break;
      case '--log-time':
        $conf->log_time = true;
        break;
      case '-o': case '--out':
      case '-d': case '--dir':
      case '-i': case '--inc':
      case '-l': case '--lib':
      case '--log-dest':
      case '--log-width':
      case '--log-level':
      case '--pack':
      case '--stub':
        if ($i + 1 >= $argc)
          goto verr;
        
        $val = $argv[$i + 1];
        if (substr($val, 0, 1) === '-') {
          verr:
          echo "option error: ", $argv[$i], " requires a value\n";
          exit;
        }
        
        switch ($argv[$i++]) {
          case '-o': case '--out':
            $conf->out = $val;
            break;
          case '-d': case '--dir':
            $conf->dir = $val;
            break;
          case '-l': case '--lib':
            $libs[] = $val;
            break;
          case '-i': case '--inc': 
            $conf->lib_paths[] = $val;
            break;
          case '--log-dest':
            $conf->log_dest = $val;
            break;
          case '--log-width':
            $conf->log_width = $val;
            break;
          case '--log-level':
            $conf->log_level = $val;
            break;
          case '--pack':
            $conf->pack = $val;
            break;
          case '--stub':
            $conf->stub = $val;
            break;
        }
        
        break;
      default:
        if (substr($argv[$i], 0, 1) === '-') {
          echo "unknown option: ", $argv[$i], "\n";
          usage(false);
        }
        
        $srcs[] = $argv[$i];
        break;
    }
  }
  
  return [ $libs, $srcs ];
}

/**
 * checks the configuration
 *
 * @return bool
 */
function check_conf($conf) {
  $res = true;
  
  $flags = [ 
    'err', 'nort', 'nostd', 'quiet', 'werror', 'run', 
    'format', 'check', 'version', 'log_time'
  ];
  
  foreach ($flags as $flag)
    if (!is_bool($conf->{$flag})) {
      echo "invalid option: `", $flag, "`\n";
      $res = false;
    }
  
  if ($conf->dir === NULL || 
      $conf->dir === '' || 
      $conf->dir === '.' ||
      $conf->dir === './' ||
      $conf->dir === '.\\')
    $conf->dir = getcwd();
  else
    if ($conf->dir === '..' ||
        $conf->dir === '../' ||
        $conf->dir === '..\\')
      $conf->dir = dirname(getcwd());
    else {
      $dir = trim($conf->dir);
      if (preg_match('/^([.]{1,2}[\\\\\/])/', $dir, $sub)) {
        $sub = $sub[0];
        $dir = substr($dir, strlen($sub));
        $cwd = getcwd();
        
        if (substr($sub, 0, 2) === '..')
          $cwd = dirname($cwd);
        
        $dir = $cwd . DIRECTORY_SEPARATOR . $dir;
      }
      
      if ((!is_dir($dir) && !mkdir($dir, 0777, true)) || !is_writable($dir)) {
        echo "invalid option: `dir` - ", $dir, "\n";
        echo "unable to access or create directory\n";
        $res = false;
      } else
        $conf->dir = $dir;
    }
    
  if (!is_string($conf->out)) {
    echo "invalid option: `out` - ", $conf->out, "\n";
    $res = false;
  }
  
  if (!is_array($conf->lib_paths)) {
    echo "invalid option: `lib_paths`\n";
    $res = false;
  }
  
  switch ($conf->log_dest) {
    case null:
      $conf->log_dest = 'stderr';
    case 'stderr':
    case 'stdout':
      break;
      
    default:
      if (!is_string($conf->log_dest)) {
        echo "invalid option: `log_dest` - ", $conf->log_dest, "\n";
        $res = false;
      } else {
        $path = realpath($conf->log_dest);
        
        if (is_file($conf->log_dest) && !is_writable($conf->log_dest)) {
          echo "option `log_dest` - file is not writable: ", $path, "\n";
          $res = false;
        } else
          $conf->log_dest = $path;
      }
  }
  
  $conf->log_width |= 0;
  
  if ($conf->log_width <= 0) {
    echo "invalid option: `log_width` - expected a number >= 0\n";
    $res = false;
  }
  
  switch ($conf->log_level) {
    case 'all':
    case 'ALL':
      $conf->log_level = LOG_LEVEL_ALL;
      break;
    case 'debug':
    case 'DEBUG':
      $conf->log_level = LOG_LEVEL_DEBUG;
      break;
    case 'info':
    case 'INFO':
      $conf->log_level = LOG_LEVEL_INFO;
      break;
    case 'warn':
    case 'WARN':
    case 'warning':
    case 'WARNING':
      $conf->log_level = LOG_LEVEL_WARNING;
      break;
    case 'error':
    case 'ERROR':
      $conf->log_level = LOG_LEVEL_ERROR;
      break;
    case LOG_LEVEL_ALL:
    case LOG_LEVEL_DEBUG:
    case LOG_LEVEL_INFO:
    case LOG_LEVEL_WARNING:
    case LOG_LEVEL_ERROR:
      break;
    default:
      echo "invalid option: `log_level` - ", $conf->log_level, "\n";
      $res = false;
  }
  
  $out_ext = strtolower(strrchr($conf->out, '.'));
  
  if ($conf->pack === null) {
    switch ($out_ext) {
      case '.phar':
        $conf->pack = 'phar';
        break;
      case '.php':
        $conf->pack = 'none';
        break;
      default:
        $conf->pack = 'zip';
    }
  } else
    switch ($conf->pack) {
      case 'none':
      case 'NONE':
      case 'zip':
      case 'ZIP':
      case 'phar':
      case 'PHAR':
        $conf->pack = strtolower($conf->pack);
        break;
      default:
        echo "invalid option: `pack` - ", $conf->pack, "\n";
        $res = false;
    }
  
  if ($conf->mod === true)
    $conf->stub = 'none';
  elseif ($conf->stub === null) {
    switch ($out_ext) {
      case '.phar':
        $conf->stub = 'phar-run';
        break;
      case '.php':
        $conf->stub = 'run';
        break;
      default:
        $conf->stub = 'none';
    }
  } else 
    switch ($conf->stub) {
      case 'phar-run':
      case 'PHAR-RUN':
      case 'PHAR_RUN':
      case 'phar-web':
      case 'PHAR-WEB':
      case 'PHAR_WEB':
        $conf->pack = 'phar';
      case 'none':
      case 'NONE':
      case 'run':
      case 'RUN':
      case 'cli':
      case 'CLI':
        $conf->stub = strtolower(strtr($conf->stub, [ '_' => '-' ]));
        break;
      default:
        echo "invalid option: `stub` - ", $conf->stub, "\n";
        $res = false;
    }
  
  return $res;
}
