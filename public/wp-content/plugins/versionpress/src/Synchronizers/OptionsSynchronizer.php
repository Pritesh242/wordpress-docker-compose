<?php
namespace VersionPress\Synchronizers;

use VersionPress\Database\DbSchemaInfo;
use VersionPress\Storages\OptionsStorage;
use VersionPress\Storages\Storage;
use VersionPress\Utils\AbsoluteUrlReplacer;
use wpdb;

class OptionsSynchronizer implements Synchronizer {

    private $optionsStorage;

    private $database;
    

    private $urlReplacer;

    private $tableName;

    function __construct(Storage $optionsStorage, $wpdb, DbSchemaInfo $dbSchema, AbsoluteUrlReplacer $urlReplacer) {
        $this->optionsStorage = $optionsStorage;
        $this->database = $wpdb;
        $this->urlReplacer = $urlReplacer;
        $this->tableName = $dbSchema->getPrefixedTableName('option');
    }

    function synchronize($task, $entitiesToSynchronize = null) {
        $options = $this->optionsStorage->loadAll();
        if (count($options) == 0) return array();

        $syncQuery = "INSERT INTO {$this->tableName} (option_name, option_value, autoload) VALUES ";
        foreach ($options as $optionName => $values) {
            $values = $this->urlReplacer->restore($values);
            if (!isset($values['autoload'])) $values['autoload'] = 'yes'; 
            $syncQuery .= "(\"$optionName\", \"" . $this->database->_real_escape($values['option_value']) . "\", \"$values[autoload]\"),";
        }

        $syncQuery[mb_strlen($syncQuery) - 1] = " "; 
        $syncQuery .= " ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = VALUES(autoload);";

        $this->database->query($syncQuery);

        $ignoredOptionNames = array_map(function ($option) {
            return "\"" . $option['option_name'] . "\"";
        }, $options);

        $ignoredOptionNames[] = '"cron"';
        $ignoredOptionNames[] = '"siteurl"';
        $ignoredOptionNames[] = '"home"';
        $ignoredOptionNames[] = '"db_upgraded"';
        $ignoredOptionNames[] = '"auto_updater.lock"';

        $deleteSql = "DELETE FROM {$this->tableName} WHERE option_name NOT IN(" . join(", ", $ignoredOptionNames) . ") OR option_name NOT LIKE '_%'";
        $this->database->query($deleteSql);
        return array();
    }

}
