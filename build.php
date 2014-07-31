<?php
/**
 * This a build script for Joomla extensions
 * example use: php build.php -i /path/to/build.ini -v 1.0.17 -p BETA
 *
 * @author Joomunited
 */

$arguments = getopt( 'v:p:i:ud' );

if( !isset($arguments['i']) )
    die( "You must give an ini file with the -i argument. Aborting.\r\n" );

$ini = parse_ini_file($arguments['i'], true);
if (!CheckIni($ini))
    die( "Incorrect .ini file content. Aborting.\r\n" );

// Get extension directory
$extensionDir = rtrim($ini['build']['extension_root'],'/');
if (strpos($extensionDir, '/') !== 0) {
    $extensionDir = rtrim(dirname($arguments['i']),'/') . "/{$extensionDir}";
}

// Get builds directory
$buildsDir = $extensionDir . '/' . trim($ini['build']['builds_dir'],'/') . '/';

if( !isset($arguments['v']) )
    die( "You must give a version number with the -v argument. Aborting.\r\n" );


$version = $arguments['v'];
$precision = (!empty($arguments['p'])) ? '-' . $arguments['p'] : '';
$filename = "{$ini['extension']['slug_name']}_j{$ini['extension']['joomla_version']}_v" . $version . $precision;
if (isset($arguments['d'])) {
	$filename .= '-DO-NOT-RETAIL';
}

echo "Building version {$version}{$precision} to {$filename}.zip:\r\n";

$tmpDir = $buildsDir . $filename;
if( !mkdir ( $tmpDir ) )
    die( "Failed to create build directory {$tmpDir}. Aborting.\r\n" );

if( isset($ini['ignore_dirs']) && isset($ini['ignore_dirs']['dirs']) ) {
    $ignoreDirs = $ini['ignore_dirs']['dirs'];
} else {
    $ignoreDirs = array();
}

array_walk($ignoreDirs, function (&$dir) {
    global $extensionDir;
    $dir = $extensionDir . trim($dir);
});

$pb = new ProgressBar();
$pb->output(0, 'Initializing...');

$pb->output(10, 'Copying files...');

RecursiveCopy( $extensionDir, $tmpDir );

$pb->output(20, 'Writing version number...');

if (!isset($arguments['d'])) {
	foreach($ini['version']['files'] as $file) {
		WriteVersion($tmpDir . $file, $version);
	}
}

$pb->output(50, 'Zipping files...');

foreach($ini['zip']['files'] as $file) {
    $dir = $tmpDir . $file;
    ZipDirectory( $dir, basename($dir));
}
$pb->output(80, 'Checking for errors...');

$errors = FindParseErrors($extensionDir);

$pb->output(100, 'Done.');

echo "\r\n";
echo "Parse errors? ";
echo (empty($errors)) ?  'None.' : "\r\n$errors\r\n";
echo ( "\r\n\t--> $filename.zip\r\n" );

if (isset($arguments['u']) && empty($errors)) {
    foreach($ini as $sectionName => $section) {
        if (stripos($sectionName, 'autoinstall') !== FALSE) {
            if (!empty($section['name'])) {
                echo ( "\r\nDeploying on {$section['name']}... \r\n" );
            } else {
                echo ( "\r\nDeploying online... \r\n" );
            }

            InstallExtension($section['root_url'], $section['username'], $section['password']);
            echo "\r\n";
        }
    }
}

echo "\r\n";

//
// Utilities
// 

function __autoload($class_name) {
    require_once(dirname(__FILE__) . "/lib/{$class_name}.php");
}

function FindParseErrors($extensionDir) {
    $uname = php_uname('s');

    $command = 'find "'.str_replace('\\','/',$extensionDir).'" -name \'*.php\' -exec php -l "{}" \;';
    if (stristr($uname, 'windows')) {
        $command = stripcslashes($command);
    }
    $check = ShellExecute($command);
    $errors = '';
    $results = explode("\n", $check);
    foreach($results as $result) {
        if (!strstr($result, "No syntax errors detected in")) {
            $errors .= $result."\n";
        }
    }
    return trim($errors);
}

function InstallExtension($website, $username, $passwd) {
    global $filename;
    global $buildsDir;
    global $ini;

    $pb = new ProgressBar();
    $pb->setSize(10);
    $pb->output(0, 'Initializing...');

    $file = "{$buildsDir}/{$filename}.zip";

    $website = trim($website, '/');
    $url = $website.'/administrator/index.php';

    $wc = new WebClient();
    $cookie = GetCookieForUrl($url);
    $wc->setCookie($cookie);

    $page = $wc->Navigate($url.'?option=com_installer');
    if ($page === FALSE) {
        $pb->output(0, 'Failed to load login page.');
        return FALSE;
    }

    $post = $wc->getInputs();

    if (isset($post['task']) && $post['task'] == 'login') {
        $pb->output(25, 'Logging in...');

        $post['username'] = $username;
        $post['passwd'] = $passwd;

        $page = $wc->Navigate($url.'?option=com_installer', $post);
        if ($page === FALSE) {
            $pb->output(25, 'Failed to post credentials.');
            return FALSE;
        }

        $cookie = $wc->getCookie();
        SaveCookie($url, $cookie);
    } else {
        $pb->output(25, 'Already authenticated...');
    }

    $pb->output(50, 'Initializing installation...');

    $pb->output(75, 'Installing...');

    $post = $wc->getInputs();
    $post['install_package'] = '@'.$file;

    $page = $wc->Navigate($url. (((float)$ini['extension']['joomla_version'] > 1.5) ? '?option=com_installer&view=install' : ''), $post);
    if ($page === FALSE) {
        $pb->output(75, 'Failed to upload file.');
        return FALSE;
    }

    $pb->output(100, 'Done.');

    return $page;
}

function GetCookieStorageFile()
{
    $storageFile = realpath(__DIR__) . '/cache.txt';
    if (!file_exists($storageFile)) {
        touch($storageFile);
    }
    return $storageFile;
}

function GetCookies()
{
    $storageFile = GetCookieStorageFile();
    $cache = file($storageFile);
    $cookies = array();
    foreach($cache as $raw)
    {
        $raw = trim($raw);
        list($url, $cookie) = explode('|', $raw);
        $url = base64_decode($url);
        $cookie = base64_decode($cookie);
        $cookies[$url] = $cookie;
    }
    return $cookies;
}

function SetCookies($cookies)
{
    $data = '';
    foreach($cookies as $url => $cookie)
    {
        $data .= base64_encode($url) . "|" . base64_encode($cookie) . "\n";
    }
    $storageFile = GetCookieStorageFile();
    file_put_contents($storageFile, $data);
}

function SaveCookie($url, $cookie)
{
    $cookies = GetCookies();
    $cookies[$url] = $cookie;
    SetCookies($cookies);
}

function GetCookieForUrl($url)
{
    $cookies = GetCookies();
    if (isset($cookies[$url]) && !empty($cookies[$url]))
        return $cookies[$url];

    return '';
}

function ZipDirectory( $directory, $zip )
{
    ShellExecute( "cd $directory && zip -r ../$zip.zip *" );
    ShellExecute( "rm -r $directory" );
}

function ShellExecute( $command )
{
    //echo "$command\r\n";
    return shell_exec( $command );
}

function WriteVersion( $file, $version )
{
    $content = file_get_contents( $file );
    $content = str_replace( '[[VERSION]]', $version, $content );

    $fp = fopen( $file, 'w+' );
    fwrite( $fp, $content );
    fclose( $fp );
}

function RecursiveCopy($source, $dest)
{
    global $ignoreDirs;

    if (array_search($source, $ignoreDirs) !== FALSE) {
        return;
    }

    $sourceHandle = opendir($source);

    if( !is_dir($dest) )
    {
        mkdir( $dest );
    }

    while($res = readdir($sourceHandle))
    {
        // Ignore self, parent, hidden files except for .htaccess ones
        if($res == '.'
            || $res == '..'
            || (strpos($res, '.') === 0 && $res != '.htaccess')
        )
            continue;

        if(is_dir($source . '/' . $res))
            RecursiveCopy($source . '/' . $res, $dest . '/' . $res);
        else
            copy($source . '/' . $res, $dest . '/' . $res);

    }

    closedir( $sourceHandle );
}

function CheckIni($ini)
{
    ob_start();

    if (!isset($ini['build'])) {
        echo ".ini: Missing 'build' section.\r\n";
    } else {
        if (!isset($ini['build']['extension_root'])) {
            echo ".ini: Missing 'extension_root' key in 'build' section.\r\n";
        }
        if (!isset($ini['build']['builds_dir'])) {
            echo ".ini: Missing 'builds_dir' key in 'build' section.\r\n";
        }
    }

    if (!isset($ini['extension'])) {
        echo ".ini: Missing 'extension' section.\r\n";
    } else {
        if (!isset($ini['extension']['slug_name'])) {
            echo ".ini: Missing 'slug_name' key in 'extension' section.\r\n";
        }
        if (!isset($ini['extension']['joomla_version'])) {
            echo ".ini: Missing 'joomla_version' key in 'extension' section.\r\n";
        }
    }

    if (!isset($ini['version'])) {
        echo ".ini: Missing 'version' section.\r\n";
    } else {
        if (!isset($ini['version']['files'])) {
            echo ".ini: Missing 'files' key in 'version' section.\r\n";
        }
    }

    if (!isset($ini['zip'])) {
        echo ".ini: Missing 'zip' section.\r\n";
    } else {
        if (!isset($ini['zip']['files'])) {
            echo ".ini: Missing 'files' key in 'zip' section.\r\n";
        }
    }

    // [autoinstall] is not mandatory

    $output = ob_get_contents();
    ob_end_clean();

    if (!empty($output)) {
        echo $output;
        return FALSE;
    }

    return TRUE;
}
