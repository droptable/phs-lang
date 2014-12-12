#!/usr/bin/env php
<?php

foreach (glob(realpath(__DIR__ . '/../src') . '/~*.tmp') as $file)
  unlink($file);

foreach (glob(__DIR__ . '/~*.tmp') as $file)
  unlink($file);
