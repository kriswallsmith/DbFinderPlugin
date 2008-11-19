<?php

// Autofind the first available app environment
$sf_root_dir = realpath(dirname(__FILE__).'/../../../');
$apps_dir = glob($sf_root_dir.'/apps/*', GLOB_ONLYDIR);
$app = substr($apps_dir[0], 
              strrpos($apps_dir[0], DIRECTORY_SEPARATOR) + 1, 
              strlen($apps_dir[0]));
if (!$app)
{
  throw new Exception('No app has been detected in this project');
}

// -- path to the symfony project where the plugin resides
$sf_path = dirname(__FILE__).'/../../..';
 
// bootstrap
include($sf_path . '/test/bootstrap/functional.php');

// create a new test browser
$browser = new sfTestBrowser();
$browser->initialize();

// initialize database manager
$databaseManager = new sfDatabaseManager();
$databaseManager->initialize();