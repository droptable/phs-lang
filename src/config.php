<?php

namespace phs;

require_once 'util/dict.php';

use phs\util\Dict;

/** config */
final class Config extends Dict
{
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
    $this->set('log_dest', null);
    $this->set('log_level', LOG_LEVEL_DEBUG);
    $this->set('log_time', true);
    $this->set('werror', false);
    $this->set('nologo', false);
    $this->set('nostd', false);
  }
}
