<?php
/**
 * Model for DegustationLot
 *
 */

class DegustationLot extends BaseDegustationLot {

  public function getEtablissement() {
      return EtablissementClient::getInstance()->findByIdentifiant($this->declarant_identifiant);
  }

  public function isNonConforme(){
    return ($this->statut == Lot::STATUT_NONCONFORME);
  }

  public function isConformeObs(){
    return ($this->statut == Lot::STATUT_CONFORME) && $this->exist('observation') && $this->observation;
  }

  public function getShortLibelleConformite(){
    if($this->isConformeObs()){ return 'Obs.'; }
        $libelles = Lot::$shortLibellesConformites;
        return ($this->exist('conformite') && isset($libelles[$this->conformite]))? $libelles[$this->conformite] : $conformite;
  }

  public function getNumeroTableStr() {
      if (!$this->numero_table) {
          return '';
      }
      return DegustationClient::getNumeroTableStr($this->numero_table);
  }

  public function isConditionnement(){
    return preg_match('/'.ConditionnementClient::TYPE_COUCHDB.'/', $this->id_document);
  }

  public function getTypeLot(){
    if(preg_match('/'.ConditionnementClient::TYPE_COUCHDB.'/', $this->id_document)){
      return 'Cond';
    }

    if(preg_match('/'.DRevClient::TYPE_COUCHDB.'/', $this->id_document)){
      return 'DRev';
    }
  }

    public function attributionTable($table)
    {
        $this->numero_table = $table;
        $this->statut = Lot::STATUT_ATTABLE;
    }

    public function anonymize($index)
    {
        $this->numero_anonymat = $this->getNumeroTableStr().($index+1);
        $this->statut = Lot::STATUT_ANONYMISE;
    }

    public function getDocumentType() {

        return DegustationClient::TYPE_MODEL;
    }

    public function getDocumentOrdre() {

        return "02";
    }

    public function getLibelle() {

        return "";
    }

    public function getMouvementFreeInstance() {

        return DegustationMouvementLots::freeInstance($this->getDocument());
    }
}
