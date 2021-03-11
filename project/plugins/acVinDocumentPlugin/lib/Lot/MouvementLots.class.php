<?php

abstract class MouvementLots extends acCouchdbDocumentTree
{
    public function getUnicityKey() {

        return $this->lot_unique_id."-".KeyInflector::slugify($this->statut);
    }

    public function getLot() {

        return $this->getDocument()->get($this->lot_hash);
    }

}
