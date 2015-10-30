<?php

namespace VersionPress\Synchronizers;

use VersionPress\Database\DbSchemaInfo;
use VersionPress\Storages\StorageFactory;
use VersionPress\Utils\AbsoluteUrlReplacer;
use wpdb;

class SynchronizerFactory {
    

    private $storageFactory;
    

    private $database;
    

    private $dbSchema;

    private $urlReplacer;

    private $synchronizerClasses = array(
        'post' => 'VersionPress\Synchronizers\PostsSynchronizer',
        'postmeta' => 'VersionPress\Synchronizers\PostMetaSynchronizer',
        'comment' => 'VersionPress\Synchronizers\CommentsSynchronizer',
        'option' => 'VersionPress\Synchronizers\OptionsSynchronizer',
        'user' => 'VersionPress\Synchronizers\UsersSynchronizer',
        'usermeta' => 'VersionPress\Synchronizers\UserMetaSynchronizer',
        'term' => 'VersionPress\Synchronizers\TermsSynchronizer',
        'term_taxonomy' => 'VersionPress\Synchronizers\TermTaxonomySynchronizer',
    );

    function __construct(StorageFactory $storageFactory, $wpdb, DbSchemaInfo $dbSchema, AbsoluteUrlReplacer $urlReplacer) {
        $this->storageFactory = $storageFactory;
        $this->database = $wpdb;
        $this->dbSchema = $dbSchema;
        $this->urlReplacer = $urlReplacer;
    }

    public function createSynchronizer($synchronizerName) {
        $synchronizerClass = $this->synchronizerClasses[$synchronizerName];
        return new $synchronizerClass($this->getStorage($synchronizerName), $this->database, $this->dbSchema, $this->urlReplacer);
    }

    public function getAllSupportedSynchronizers() {
        return array_keys($this->synchronizerClasses);
    }

    private function getStorage($synchronizerName) {
        return $this->storageFactory->getStorage($synchronizerName);
    }
}