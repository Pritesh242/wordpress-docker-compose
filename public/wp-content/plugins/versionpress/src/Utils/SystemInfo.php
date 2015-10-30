<?php

namespace Utils;

use Nette\Utils\Strings;
use Symfony\Component\Process\Process;
use VersionPress\Configuration\VersionPressConfig;
use VersionPress\DI\VersionPressServices;
use VersionPress\Utils\RequirementsChecker;
use VersionPress\VersionPress;

class SystemInfo {

    public static function getAllInfo() {

        global $versionPressContainer;
        

        $vpConfig = $versionPressContainer->resolve(VersionPressServices::VP_CONFIGURATION);

        $output = array();
        $output['summary'] = array();
        $output['git-info'] = self::getGitInfo();
        $output['wordpress-info'] = self::getWordPressInfo();
        $output['php-info'] = self::getPhpInfo();

        $output['summary']['wordpress-version'] = $output['wordpress-info']['wp-version'];
        $output['summary']['versionpress-version'] = VersionPress::getVersion();
        $output['summary']['operating-system'] = php_uname();
        $output['summary']['php-version'] = phpversion();
        $output['summary']['php-sapi'] = php_sapi_name();
        $output['summary']['custom-vp-config'] = $vpConfig->customConfig;
        $output['summary']['git-version'] = isset($output['git-info']['git-version']) ? $output['git-info']['git-version'] : '';
        $output['summary']['git-full-path'] = isset($output['git-info']['git-full-path']) ? $output['git-info']['git-full-path'] : '';

        return $output;
    }

    public static function getGitInfo() {

        global $versionPressContainer;
        

        $vpConfig = $versionPressContainer->resolve(VersionPressServices::VP_CONFIGURATION);
        $gitBinary = $vpConfig->gitBinary;

        $info = array();

        $process = new Process(escapeshellarg($gitBinary) . " --version");
        $process->run();

        $info['git-binary-as-configured'] = $gitBinary;
        $info['git-available'] = $process->getErrorOutput() === null;

        if ($info['git-available'] === false) {
            $info['output'] = array(
                'stdout' => trim($process->getOutput()),
                'stderr' => trim($process->getErrorOutput())
            );

            $info['env-path'] = getenv('PATH');
            return $info;
        }

        $output = trim($process->getOutput());

        $match = Strings::match($output, "~git version (\\d[\\d\\.]+\\d).*~");
        $version = $match[1];

        $gitPath = "unknown";
        if ($gitBinary == "git") {
            $osSpecificWhereCommand = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? "where" : "which";
            $process = new Process("$osSpecificWhereCommand git");
            $process->run();

            if ($process->isSuccessful()) {
                $gitPath = $process->getOutput();
            }
        } else {
            $gitPath = $gitBinary;
        }

        $info['git-version'] = $version;
        $info['git-binary-as-called-by-vp'] = $gitBinary;
        $info['git-full-path'] = $gitPath;
        $info['versionpress-min-required-version'] = RequirementsChecker::GIT_MINIMUM_REQUIRED_VERSION;
        $info['matches-min-required-version'] = RequirementsChecker::gitMatchesMinimumRequiredVersion($version);

        return $info;

    }

    public static function getGitVersion() {
        $gitInfo = self::getGitInfo();
        if (empty($gitInfo)) {
            throw new \Exception("Git not available");
        } else {
            return $gitInfo['git-version'];
        }
    }

    public static function getWordPressInfo() {

        $info = array();

        $info['wp-version'] = get_bloginfo('version');
        $installedPlugins = get_plugins();
        array_walk($installedPlugins, function(&$pluginInfo, $pluginFile) {

            $keysToKeep = array('Name', 'PluginURI', 'Version', 'Author', 'AuthorURI');
            $pluginInfo = array_intersect_key($pluginInfo, array_flip($keysToKeep));

            $pluginInfo['__IsActive'] = is_plugin_active($pluginFile);

        });
        $info['installed-plugins'] = $installedPlugins;
        $info['installed-themes'] = array_keys(wp_get_themes());
        $info['active-plugins'] = get_option('active_plugins');
        $info['active-theme'] = self::getActiveThemeInfo();

        return $info;
    }

    public static function getActiveThemeInfo() {
        $info = array();
        $wpTheme = wp_get_theme();

        $info['stylesheet'] = $wpTheme->get_stylesheet();
        $info['template'] = $wpTheme->get_template();
        $parent = $wpTheme->parent();
        $parentName = '';
        if ($parent instanceof \WP_Theme) {
            $parentName = $parent->get_template();
        }
        $info['parent'] = $parentName;
        $info['Name'] = $wpTheme->get('Name');
        $info['ThemeURI'] = $wpTheme->get('ThemeURI');

        return $info;
    }

    public static function getPhpInfo(){
        ob_start();
        phpinfo(-1);

        $pi = preg_replace(
            array('#^.*<body>(.*)</body>.*$#ms', '#<h2>PHP License</h2>.*$#ms',
                '#<h1>Configuration</h1>#',  "#\r?\n#", "#</(h1|h2|h3|tr)>#", '# +<#',
                "#[ \t]+#", '#&nbsp;#', '#  +#', '# class=".*?"#', '%&#039;%',
                '#<tr>(?:.*?)" src="(?:.*?)=(.*?)" alt="PHP Logo" /></a>'
                .'<h1>PHP Version (.*?)</h1>(?:\n+?)</td></tr>#',
                '#<h1><a href="(?:.*?)\?=(.*?)">PHP Credits</a></h1>#',
                '#<tr>(?:.*?)" src="(?:.*?)=(.*?)"(?:.*?)Zend Engine (.*?),(?:.*?)</tr>#',
                "# +#", '#<tr>#', '#</tr>#'),
            array('$1', '', '', '', '</$1>' . "\n", '<', ' ', ' ', ' ', '', ' ',
                '<h2>PHP Configuration</h2>'."\n".'<tr><td>PHP Version</td><td>$2</td></tr>'.
                "\n".'<tr><td>PHP Egg</td><td>$1</td></tr>',
                '<tr><td>PHP Credits Egg</td><td>$1</td></tr>',
                '<tr><td>Zend Engine</td><td>$2</td></tr>' . "\n" .
                '<tr><td>Zend Egg</td><td>$1</td></tr>', ' ', '%S%', '%E%'),
            ob_get_clean());

        $sections = explode('<h2>', strip_tags($pi, '<h2><th><td>'));
        unset($sections[0]);

        $pi = array();
        foreach($sections as $section){
            $n = substr($section, 0, strpos($section, '</h2>'));
            preg_match_all(
                '#%S%(?:<td>(.*?)</td>)?(?:<td>(.*?)</td>)?(?:<td>(.*?)</td>)?%E%#',
                $section, $askapache, PREG_SET_ORDER);
            foreach($askapache as $m)
                
                
                $pi[$n][$m[1]] = @( (!isset($m[3])||$m[2]==$m[3])?$m[2]:array_slice($m,2) );
        }

        $pi['Extensions'] = get_loaded_extensions();

        return $pi;
    }
}
