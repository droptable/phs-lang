#!/usr/bin/env php
<?php

foreach (glob(realpath(__DIR__ . '/../src') . '/~*.tmp') as $file)
  unlink($file);
