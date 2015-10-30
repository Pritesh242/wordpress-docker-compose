<?php

namespace VersionPress\Storages;

use VersionPress\ChangeInfos\TermChangeInfo;
use VersionPress\Utils\EntityUtils;

class TermsStorage extends SingleFileStorage {

    protected function createChangeInfo($oldEntity, $newEntity, $action = null) {
        $diff = EntityUtils::getDiff($oldEntity, $newEntity);

        $taxonomy = 'term'; 

        if (isset($newEntity['taxonomies']) && count($newEntity['taxonomies']) === 1) {
            $termTaxonomy = current($newEntity['taxonomies']);
            $taxonomy = $termTaxonomy['taxonomy'];
        }

        if ($oldEntity && isset($diff['name'])) {
            return new TermChangeInfo('rename', $newEntity['vp_id'], $newEntity['name'], $taxonomy, $oldEntity['name']);
        }

        return new TermChangeInfo($action, $newEntity['vp_id'], $newEntity['name'], $taxonomy);
    }
}