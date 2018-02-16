<?php
/**
 * Model for RegistreVCIProduit
 *
 */

class RegistreVCIProduit extends BaseRegistreVCIProduit {

  public function getConfig()
  {
    return $this->getCouchdbDocument()->getConfiguration()->get($this->getProduitHash());
  }

  public function getLibelle() {
    if(!$this->_get('libelle')) {
      $this->libelle = $this->getConfig()->getLibelleComplet();
      if($this->exist('denomination_complementaire')) {
        $this->libelle .= ' '.$this->denomination_complementaire;
      }
    }

    return $this->_get('libelle');
  }

  public function getLibelleComplet()
  {
    return $this->getLibelle();
  }

  public function getProduitHash() {
      return $this->getHash();
  }

  public function addMouvement($mouvement_type, float $volume, $lieu_id) {
    if (!$this->details->exist($lieu_id)) {
      $detail = $this->add('details')->add($lieu_id);
      if (!$lieu_id || $lieu_id == RegistreVCIClient::LIEU_CAVEPARTICULIERE) {
        $detail->stockage_libelle = "Cave particulière";
      }else{
        $e = EtablissementClient::getInstance()->findByIdentifiant($lieu_id);
        if (!$e) {
          throw new sfException('Etablisssement '.$lieu_id.' non trouvé');
        }
        $detail->stockage_libelle = $e->getNom();
      }
      $detail->stockage_identifiant = $lieu_id;
    }
    $detail = $this->details->get($lieu_id);
    $detail->addMouvement($mouvement_type, $volume);
    return $detail;
  }

  public function addVolume($mouvement_type, float $volume) {
    $this->_set($mouvement_type, $this->{$mouvement_type} + $volume);
    $this->_set('stock_final', $this->stock_final + $volume * RegistreVCIClient::MOUVEMENT_SENS($mouvement_type));
  }

  public function setConstitue($v) {
    throw new sfException('Not callable, use addMouvement');
  }
  public function setRafraichi($v) {
    throw new sfException('Not callable, use addMouvement');
  }
  public function setComplement($v) {
    throw new sfException('Not callable, use addMouvement');
  }
  public function setSubstitue($v) {
    throw new sfException('Not callable, use addMouvement');
  }
  public function setDetruit($v) {
    throw new sfException('Not callable, use addMouvement');
  }
  public function setStockFinal($v) {
    throw new sfException('Not callable, use addMouvement');
  }

  public function getAppellation() {
    return $this->getConfig()->getAppellation();
  }

  public function freeIncr($mvt, $v) {
    if (!$this->isPseudoAppellation()) {
      throw new sfException('Not callable from a real produit');
    }
    $this->_set($mvt, $this->get($mvt) + $v);
  }

  private $pseudo_appellation = null;
  private $pseudo_produit = null;
  private $pseudo_registre = null;

  public function isPseudoAppellation() {
    return (isset($this->pseudo_appellation) && $this->pseudo_appellation);
  }

  public function getPseudoAppellation() {
    if (!$this->pseudo_produit) {
      throw new sfException("Should be a pseudo appellation");
    }
    return $this->pseudo_produit;
  }

  public function setIsPseudoAppellation($registre, $produit) {
      $this->pseudo_appellation = 1;
      $this->pseudo_produit = $produit;
      $this->pseudo_registre = $registre;
      $this->libelle = $this->pseudo_produit->getLibelle();
  }

  public function getSuperficieFromDrev() {
    if (!$this->pseudo_registre) {
      throw new sfException('Not callable from a real produit');
    }
    $drev = $this->pseudo_registre->getDRev();
    if (!$drev) {
      return 'XXX';
    }
    return $drev->get($this->pseudo_produit->getHash())->getTotalTotalSuperficie();
  }

}