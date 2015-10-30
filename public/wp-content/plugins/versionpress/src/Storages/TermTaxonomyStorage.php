<?php
namespace VersionPress\Storages;

use VersionPress\ChangeInfos\TermChangeInfo;
use VersionPress\Utils\IniSerializer;

class TermTaxonomyStorage extends SingleFileStorage {

    protected $notSavedFields = array('vp_term_id', 'count', 'term_id', 'term_taxonomy_id');

    private $currentlySavedTaxonomy;

    public function save($data) {
        $this->currentlySavedTaxonomy = $data['taxonomy'];

        $this->loadEntities();
        $termId = $this->findTermId($data);

        if (!$termId) {
            return null;
        }

        $taxonomyVpid = $data['vp_id'];

        if (!isset($this->entities[$termId]['taxonomies'])) {
            $this->entities[$termId]['taxonomies'] = array();
        }

        $originalTaxonomies = $this->entities[$termId]['taxonomies'];

        $isNew = !isset($originalTaxonomies[$taxonomyVpid]);

        $this->updateTaxonomy($termId, $taxonomyVpid, $data);

        if ($this->entities[$termId]['taxonomies'] != $originalTaxonomies) {
            $changeInfo = $this->createChangeInfo(null, $this->entities[$termId], $isNew ? 'create' : 'edit');
            $this->saveEntities();
            return $changeInfo;
        } else {
            return null;
        }
    }

    public function delete($restriction) {
        $taxonomyId = $restriction['vp_id'];

        $this->loadEntities();
        $termId = $this->findTermId($restriction);

        if ($termId === null)
            return null;
        $originalTerm = $this->entities[$termId];
        $originalTaxonomies = $originalTerm['taxonomies'];
        unset($this->entities[$termId]['taxonomies'][$taxonomyId]);
        if ($this->entities[$termId]['taxonomies'] != $originalTaxonomies) {
            $this->saveEntities();
            return $this->createChangeInfo(null, $originalTerm, 'delete');
        } else {
            return null;
        }

    }

    public function loadEntity($id, $parentId = null) {
        $this->loadEntities();
        foreach ($this->entities as $term) {
            if (isset($term['taxonomies']) && isset($term['taxonomies'][$id])){
                $taxonomy = $term['taxonomies'][$id];
                $taxonomy['vp_term_id'] = $term['vp_id'];
                return $taxonomy;
            }
        }
        return null;
    }

    public function loadAll() {
        $this->loadEntities();
        $taxonomies = array();

        foreach ($this->entities as $term) {
            if (isset($term['taxonomies'])) {
                foreach ($term['taxonomies'] as $taxonomy) {
                    $taxonomy['vp_term_id'] = $term['vp_id'];
                    $taxonomies[$taxonomy['vp_id']] = $taxonomy;
                }
            }
        }

        return $taxonomies;
    }

    public function shouldBeSaved($data) {
        return !(count($data) === 3 && isset($data['count'], $data[$this->entityInfo->idColumnName], $data['vp_id']));
    }

    public function exists($id, $parentId = null) {
        $this->loadAll();
        foreach ($this->entities as $term) {
            if (isset($term['taxonomies']) && isset($term['taxonomies'][$id])) {
                return true;
            }
        }
        return false;
    }

    protected function loadEntities() {
        parent::loadEntities();
        $entities = $this->entities;

        foreach ($entities as $id => &$entity) {
            if (isset ($entity['taxonomies'])) {
                foreach ($entity['taxonomies'] as $taxonomyId => &$taxonomy) {
                    $taxonomy['vp_id'] = $taxonomyId;
                }
            }
        }

        $this->entities = $entities;
    }

    protected function saveEntities() {
        $entities = $this->entities;
        foreach ($entities as &$entity) {
            if (isset ($entity['taxonomies'])) {
                foreach ($entity['taxonomies'] as &$taxonomy) {
                    unset ($taxonomy['vp_id']);
                }
            }
        }

        $this->entities = $entities;
        parent::saveEntities();
    }

    private function findTermId($data) {
        $taxonomyVpid = $data['vp_id'];

        foreach ($this->entities as $termId => $term) {
            if (isset($term['taxonomies'][$taxonomyVpid])
                || (isset($data['vp_term_id']) && strval($term['vp_id']) == strval($data['vp_term_id']))
            ) {
                return $termId;
            }
        }

        return null;
    }

    private function updateTaxonomy($termId, $taxonomyId, $data) {
        $taxonomies = &$this->entities[$termId]['taxonomies'];

        if (!isset($taxonomies[$taxonomyId]))
            $taxonomies[$taxonomyId] = array();

        foreach ($this->notSavedFields as $field)
            unset($data[$field]);

        foreach ($data as $field => $value)
            $taxonomies[$taxonomyId][$field] = $value;
    }

    protected function createChangeInfo($oldEntity, $newEntity, $action = null) {
        
        return new TermChangeInfo('edit', $newEntity['vp_id'], $newEntity['name'], $this->currentlySavedTaxonomy);
    }
}
