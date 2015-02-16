<?php

abstract class _ParcellaireDeclarationNoeud extends acCouchdbDocumentTree {

    public function getConfig() {
        return $this->getCouchdbDocument()->getConfiguration()->get($this->getHash());
    }

    abstract public function getChildrenNode();

    public function hasManyNoeuds() {
        if (count($this->getChildrenNode()) > 1) {
            return true;
        }
        return false;
    }

    public function reorderByConf() {
        $children = array();

        foreach ($this->getChildrenNode() as $hash => $child) {
            $children[$hash] = $child->getData();
        }

        foreach ($children as $hash => $child) {
            $this->remove($hash);
        }

        foreach ($this->getConfig()->getChildrenNode() as $hash => $child) {
            if (!array_key_exists($hash, $children)) {
                continue;
            }

            $child_added = $this->add($hash, $children[$hash]);
            $child_added->reorderByConf();
        }
    }

    public function getChildrenNodeDeep($level = 1) {
        if ($this->getConfig()->hasManyNoeuds()) {

            throw new sfException("getChildrenNodeDeep() peut uniquement être appelé d'un noeud qui contient un seul enfant...");
        }

        $node = $this->getChildrenNode()->getFirst();

        if ($level > 1) {

            return $node->getChildrenNodeDeep($level - 1);
        }

        return $node->getChildrenNode();
    }

    public function getProduits($onlyActive = false) {
        $produits = array();
        foreach ($this->getChildrenNode() as $key => $item) {
            $produits = array_merge($produits, $item->getProduits($onlyActive));
        }

        return $produits;
    }

    public function getProduitsCepageDetails() {
        $produits = array();
        foreach ($this->getChildrenNode() as $key => $item) {
            $produits = array_merge($produits, $item->getProduitsCepageDetails());
        }

        return $produits;
    }

    public function getAcheteursNode() {
        $acheteurs = array();
        foreach($this->getProduits() as $produit) {
            $acheteurs = array_merge($acheteurs, $produit->getAcheteursNode());
        }

        return $acheteurs;
    }

    public function hasVtsgn() {
        foreach ($this->getProduitsCepage() as $produit) {
            if ($produit->hasVtsgn()) {

                return true;
            }
        }

        return false;
    }

    public function getLibelle() {
        if (is_null($this->_get('libelle'))) {
            if ($this->getConfig()->exist('libelle_long')) {
                $this->_set('libelle', $this->getConfig()->libelle_long);
            } else {
                $this->_set('libelle', $this->getConfig()->libelle);
            }
        }

        return $this->_get('libelle');
    }

    public function getLibelleComplet() {
        $libelle = $this->getParent()->getLibelleComplet();
        return trim($libelle) . ' ' . $this->libelle;
    }

    public function getTotalTotalSuperficie() {
        $total = 0;
        foreach ($this->getChildrenNode() as $key => $item) {
            $total += $item->getTotalSuperficie();
        }
        return $total;
    }

    public function getTotalVolumeRevendique() {
        $total = 0;
        foreach ($this->getChildrenNode() as $key => $item) {
            $total += $item->getTotalSuperficie();
        }
        return $total;
    }

    public function isCleanable() {
        if (count($this->getChildrenNode()) == 0) {

            return true;
        }

        return false;
    }

    public function cleanNode() {
        $hash_to_delete = array();
        foreach ($this->getChildrenNode() as $children) {
            $children->cleanNode();
            if ($children->isCleanable()) {
                $hash_to_delete[] = $children->getHash();
            }
        }

        foreach ($hash_to_delete as $hash) {
            $this->getDocument()->remove($hash);
        }
    }
}
