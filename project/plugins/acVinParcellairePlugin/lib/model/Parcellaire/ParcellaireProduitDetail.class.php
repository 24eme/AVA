<?php

/**
 * Model for ParcellaireCepageDetail
 *
 */
class ParcellaireProduitDetail extends BaseParcellaireProduitDetail {

    public function getProduit() {

        return $this->getParent()->getParent();
    }

    public function addAcheteur($acheteur) {

        return $this->getCepage()->addAcheteurFromNode($acheteur, $this->lieu);
    }

    public function getAcheteurs() {

        return $this->getCepage()->getAcheteursNode($this->lieu);
    }

    public function getAcheteursByCVI() {
        $acheteursCvi = array();
        foreach($this->getAcheteurs() as $type => $acheteurs) {
            foreach($acheteurs as $cvi => $acheteur) {
                $acheteursCvi[$cvi] = $acheteur;
            }
        }

        return $acheteursCvi;
    }

    public function getProduitsDetails($onlyVtSgn = false, $active = false) {
		if ($active && !$this->getActive()) {
			return array();
		}
        return array($this->getHash() => $this);
    }

    public function getLibelleComplet() {
        return $this->getAppellation()->getLibelleComplet().' '.$this->getLieuLibelle().' '.$this->getCepageLibelle();
    }

    public function getLieuLibelle() {
        if ($this->lieu) {

            return $this->lieu;
        }

        return $this->getLieuNode()->getLibelle();
    }

    public function getParcelleIdentifiant() {
        return sprintf('%s %03s %03s', $this->commune, $this->section, $this->numero_parcelle);
    }

    public function getAppellation() {
        return $this->getCepage()->getAppellation();
    }

    public function isCleanable() {
    	if (!$this->getActive()) {
    		return true;
    	}
        return ($this->isFromAppellation('ALSACEBLANC') && !$this->getVtsgn());
    }

    public function isAffectee($lieu = null) {
        if ($lieu && $this->lieu && KeyInflector::slugify(trim($lieu)) != KeyInflector::slugify(trim($this->lieu))) {

    		return false;
    	}

        return !$this->isCleanable();
    }

    public function getProduitLibelle() {

        return $this->getProduit()->getLibelle();
    }

    public function getCepage() {

        return $this->getProduit()->getConfig()->getCepage();
    }

    public function getCepageLibelle() {

        return $this->getCepage()->getLibelle();
    }

    public function getLieuNode() {

        return $this->getProduit()->getConfig()->getLieu();
    }

    public function cleanNode() {

        return false;
    }

    public function getActive() {
        $v = $this->_get('active');
        if (!$this->superficie) {
            return false;
        }
        if ($v === null) {
            return true;
        }
        return ($v) ? true : false;
    }
    public function setActive($value) {
        return $this->_set('active', $value * 1);
    }
    public function getVtsgn() {
        $v = $this->_get('vtsgn');
        if ($v === null || !$this->superficie) {
            return false;
        }
        return ($v) ? true : false;
    }
    public function setVtsgn($value) {
        return $this->_set('vtsgn', $value * 1);
    }

    public function isFromAppellation($appellation){
        return 'appellation_'.$appellation == $this->getAppellation()->getKey();
    }
}
