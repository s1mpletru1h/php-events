<?php
require 'vendor/autoload.php';

function recurseRmdir(string $dir) {
  $files = array_diff(scandir($dir), array('.','..'));
  foreach ($files as $file) {
    (is_dir("$dir/$file")) ? recurseRmdir("$dir/$file") : unlink("$dir/$file");
  }
  return rmdir($dir);
}

if (file_exists('./tmp/logs'))
{
    recurseRmdir('./tmp/logs');
}

