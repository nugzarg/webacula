<?php
/**
 *
 * Copyright 2007, 2008, 2009 Yuri Timofeev tim4dev@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see http://www.gnu.org/licenses/
 *
 * @author Yuri Timofeev <tim4dev@gmail.com>
 * @package webacula
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU Public License
 *
 */

define('WEBACULA_VERSION', '3.3' . ', build 2009.10.14');

define('ROOT_DIR', dirname(dirname(__FILE__)) );
error_reporting(E_ALL|E_STRICT);

// PATH_SEPARATOR  ":"
set_include_path('.' . PATH_SEPARATOR . '../library' . PATH_SEPARATOR . '../application/models/' .
    PATH_SEPARATOR . get_include_path() );

include "Zend/Loader.php";

Zend_Loader::loadClass('Zend_Controller_Front');
Zend_Loader::loadClass('Zend_Session');
Zend_Loader::loadClass('Zend_Config_Ini');
Zend_Loader::loadClass('Zend_Registry');
Zend_Loader::loadClass('Zend_Db');
Zend_Loader::loadClass('Zend_Db_Table');
Zend_Loader::loadClass('Zend_View');
Zend_Loader::loadClass('Zend_Json');
Zend_Loader::loadClass('Zend_Translate');
Zend_Loader::loadClass('Zend_Locale');
Zend_Loader::loadClass('Zend_Exception');
Zend_Loader::loadClass('Zend_Paginator');
Zend_Loader::loadClass('Zend_Layout');

// load my class
Zend_Loader::loadClass('MyClass_ControllerAction');
Zend_Loader::loadClass('MyClass_HomebrewBase64');
Zend_Loader::loadClass('MyClass_GaugeTime');

// load configuration
$config = new Zend_Config_Ini('../application/config.ini', 'general');
$config_webacula = new Zend_Config_Ini('../application/config.ini', 'webacula');
$config_layout   = new Zend_Config_Ini('../application/config.ini', 'layout');

$registry = Zend_Registry::getInstance();

// assign the $config object to the registry so that it can be retrieved elsewhere in the application
$registry->set('config', $config);
$registry->set('config_webacula', $config_webacula);
// set timezone
date_default_timezone_set($config->def->timezone);

// set self version
Zend_Registry::set('webacula_version', WEBACULA_VERSION);

// set global const
Zend_Registry::set('UNKNOWN_VOLUME_CAPACITY', -200); // tape drive
Zend_Registry::set('NEW_VOLUME', -100);
Zend_Registry::set('ERR_VOLUME', -1);

/**
 *
 * Database, table, field and columns names in PostgreSQL are case-independent, unless you created them with double-quotes
 * around their name, in which case they are case-sensitive.
 * Note: that PostgreSQL actively converts all non-quoted names to lower case and so returns lower case in query results.
 * In MySQL, table names can be case-sensitive or not, depending on which operating system you are using.
 *
 */

// setup database bacula
Zend_Registry::set('DB_ADAPTER', strtoupper($config->db->adapter) );
$params = $config->db->config->toArray();
// for cross database compatibility with PDO, MySQL, PostgreSQL
$params['options'] = array(Zend_Db::CASE_FOLDING => Zend_Db::CASE_LOWER, Zend_DB::AUTO_QUOTE_IDENTIFIERS => FALSE);
$db_bacula = Zend_Db::factory($config->db->adapter, $params);
Zend_Db_Table::setDefaultAdapter($db_bacula);
Zend_Registry::set('db_bacula', $db_bacula);

unset($params);
// setup database WEbacula
Zend_Registry::set('DB_ADAPTER_WEBACULA', strtoupper($config_webacula->db->adapter) );
$params = $config_webacula->db->config->toArray();
$params['options'] = array(Zend_Db::CASE_FOLDING => Zend_Db::CASE_LOWER, Zend_DB::AUTO_QUOTE_IDENTIFIERS => FALSE);
$db_webacula = Zend_Db::factory($config_webacula->db->adapter, $params);
Zend_Registry::set('db_webacula', $db_webacula);

// setup controller, exceptions handler
$frontController = Zend_Controller_Front::getInstance();
$frontController->setControllerDirectory('../application/controllers');
if ( $config->debug == 1 ) {
	$frontController->throwExceptions(true);
} else {
	$frontController->throwExceptions(false);
}
//$frontController->setParam('useDefaultControllerAlways', true); // handle 404 errors

// translate
//auto scan lang files may be have bug in ZF ? $translate = new Zend_Translate('gettext', '../languages', null, array('scan' => Zend_Translate::LOCALE_DIRECTORY));
$translate = new Zend_Translate('gettext', '../languages/en/webacula_en.mo', 'en');
// additional languages
// see also http://framework.zend.com/manual/en/zend.locale.appendix.html
$translate->addTranslation('../languages/en/webacula_en.mo', 'en_US');
$translate->addTranslation('../languages/de/webacula_de.mo', 'de');
$translate->addTranslation('../languages/fr/webacula_fr.mo', 'fr');
$translate->addTranslation('../languages/ru/webacula_ru.mo', 'ru');
$translate->addTranslation('../languages/ru/webacula_ru.mo', 'ru_RU');
$translate->addTranslation('../languages/pt/webacula_pt_BR.mo', 'pt_BR');

if ( isset($config->locale) ) {
    // locale is user defined
    $locale = trim($config->locale);
} else {
    // autodetect locale
    // Search order is: given Locale, HTTP Client, Server Environment, Framework Standard
    try {
        $locale = new Zend_Locale('auto');
    } catch (Zend_Locale_Exception $e) {
        $locale = new Zend_Locale('en');
    }
}
if ( $translate->isTranslated('Desktop', false, $locale) ) {
    // can be translated (есть перевод)
    $translate->setLocale($locale);
} else {
    // can't translated (нет перевода)
    // set to English by default
    $translate->setLocale('en');
    $locale = new Zend_Locale('en');
}
// assign the $translate object to the registry so that it can be retrieved elsewhere in the application
$registry->set('translate', $translate);
$registry->set('locale',    $locale);
$registry->set('language',  $locale->getLanguage());

Zend_Layout::startMvc(array(
    'layoutPath' => '../application/layouts/' . $config_layout->path,
    'layout' => 'main'
));

try {
    $db = Zend_Db_Table::getDefaultAdapter();
    $db->getConnection();
} catch (Zend_Db_Adapter_Exception $e) {
    // возможно СУБД не запущена
    //throw new Zend_Exception("Fatal error: Can't connect to SQL server");
}

// run
$frontController->dispatch();
