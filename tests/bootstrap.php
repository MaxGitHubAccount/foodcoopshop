<?php
/**
 * Test runner bootstrap.
 *
 * Add additional configuration/setup your application needs when running
 * unit tests in this file.
 */
use Cake\Core\Configure;
use Cake\Utility\Security;

require dirname(__DIR__) . '/vendor/autoload.php';

require dirname(__DIR__) . '/config/bootstrap.php';

Security::setSalt(Configure::read('Security.salt_for_unit_tests'));

// always set to app.customerMainNamePart to firstname for unit tests even if different in custom_config.php
Configure::write('app.customerMainNamePart', 'firstname');


$_SERVER['PHP_SELF'] = '/';

// phpunit with enabled processIsolation sends headers before output
// https://github.com/cakephp/docs/pull/6988
session_id('cli');
