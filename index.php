<?php
/**
 * GitPHP
 *
 * Index
 *
 * @author Christopher Han <xiphux@gmail.com>
 * @copyright Copyright (c) 2010 Christopher Han
 * @package GitPHP
 */

/**
 * Use utf-8 encoding
 */
if (function_exists('mb_internal_encoding')) {
	mb_internal_encoding("UTF-8");
}

/**
 * Define start time / memory for benchmarking
 */
define('GITPHP_START_TIME', microtime(true));
define('GITPHP_START_MEM', memory_get_usage());

/**
 * Define some paths
 */
define('GITPHP_BASEDIR', dirname(__FILE__) . '/');
define('GITPHP_CONFIGDIR', GITPHP_BASEDIR . 'config/');
define('GITPHP_INCLUDEDIR', GITPHP_BASEDIR . 'include/');
define('GITPHP_LOCALEDIR', GITPHP_BASEDIR . 'locale/');
define('GITPHP_CACHEDIR', GITPHP_BASEDIR . 'cache/');
define('GITPHP_LIBDIR', GITPHP_BASEDIR . 'lib/');
define('GITPHP_SMARTYDIR', GITPHP_LIBDIR . 'smarty/libs/');
define('GITPHP_GESHIDIR', GITPHP_LIBDIR . 'geshi/');

include(GITPHP_INCLUDEDIR . 'version.php');

require(GITPHP_INCLUDEDIR . 'AutoLoader.class.php');

spl_autoload_register(array('GitPHP_AutoLoader', 'AutoLoad'));

date_default_timezone_set('UTC');


/*
 * Set the locale based on the user's preference
 */
if ((!isset($_COOKIE[GitPHP_Resource::LocaleCookie])) || empty($_COOKIE[GitPHP_Resource::LocaleCookie])) {

	/*
	 * User's first time here, try by HTTP_ACCEPT_LANGUAGE
	 */
	if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
		$httpAcceptLang = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
		$preferredLocale = GitPHP_Resource::FindPreferredLocale($_SERVER['HTTP_ACCEPT_LANGUAGE']);
		if (!empty($preferredLocale)) {
			setcookie(GitPHP_Resource::LocaleCookie, $preferredLocale, time()+GitPHP_Resource::LocaleCookieLifetime);
			GitPHP_Resource::Instantiate($preferredLocale);
		}
	}

	if (!GitPHP_Resource::Instantiated()) {
		/*
		 * Create a dummy cookie to prevent browser delay
		 */
		setcookie(GitPHP_Resource::LocaleCookie, 0, time()+GitPHP_Resource::LocaleCookieLifetime);
	}

} else if (isset($_GET['l']) && !empty($_GET['l'])) {

	/*
	 * User picked something
	 */
	setcookie(GitPHP_Resource::LocaleCookie, $_GET['l'], time()+GitPHP_Resource::LocaleCookieLifetime);
	GitPHP_Resource::Instantiate($_GET['l']);

} else if (isset($_COOKIE[GitPHP_Resource::LocaleCookie]) && !empty($_COOKIE[GitPHP_Resource::LocaleCookie])) {

	/*
	 * Returning visitor with a preference
	 */
	GitPHP_Resource::Instantiate($_COOKIE[GitPHP_Resource::LocaleCookie]);

}

$log = null;

try {

	/*
	 * Configuration
	 */
	GitPHP_Config::GetInstance()->LoadConfig(GITPHP_CONFIGDIR . 'gitphp.conf.php');

	/*
	 * Use the default language in the config if user has no preference
	 * with en_US as the fallback
	 */
	if (!GitPHP_Resource::Instantiated()) {
		GitPHP_Resource::Instantiate(GitPHP_Config::GetInstance()->GetValue('locale', 'en_US'));
	}

	/*
	 * Debug
	 */
	if (GitPHP_DebugLog::GetInstance()->GetEnabled()) {
		GitPHP_DebugLog::GetInstance()->SetStartTime(GITPHP_START_TIME);
		GitPHP_DebugLog::GetInstance()->SetStartMemory(GITPHP_START_MEM);
	}

	$controller = GitPHP_Controller::GetController((isset($_GET['a']) ? $_GET['a'] : null));
	if ($controller) {
		$controller->RenderHeaders();
		$controller->Render();
	}

} catch (Exception $e) {

	if (GitPHP_Config::GetInstance()->GetValue('debug', false) && !($e instanceof GitPHP_MessageException)) {
		throw $e;
	}

	if (!GitPHP_Resource::Instantiated()) {
		/*
		 * In case an error was thrown before instantiating
		 * the resource manager
		 */
		GitPHP_Resource::Instantiate('en_US');
	}

	$messageController = new GitPHP_Controller_Message();
	$messageController->SetParam('message', $e->getMessage());
	if ($e instanceof GitPHP_MessageException) {
		$messageController->SetParam('error', $e->Error);
		$messageController->SetParam('statuscode', $e->StatusCode);
	} else {
		$messageController->SetParam('error', true);
	}
	$messageController->RenderHeaders();
	$messageController->Render();

	unset($messageController);

}

GitPHP_Resource::DestroyInstance();
GitPHP_Config::DestroyInstance();

$log = $controller->GetLog();
if ($log && $log->GetEnabled()) {
	$entries = $log->GetEntries();
	foreach ($entries as $logline) {
		echo "<br />\n" . htmlspecialchars($logline, ENT_QUOTES, 'UTF-8', true);
	}
	unset($logline);
	unset($entries);
}
unset($controller);

GitPHP_DebugLog::DestroyInstance();

?>
