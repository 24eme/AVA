<?php

class MouvementLotView extends acCouchdbView
{
  const KEY_DECLARANT_IDENTIFIANT = 0;
  const KEY_CAMPAGNE = 1;
  const KEY_STATUT = 2;
  const KEY_REGION = 3;
  const KEY_DATE = 4;
  const KEY_ORIGINE_DOCUMENT_ID = 5;

  const VALUE_LOT = 0;

  public static function getInstance() {

    return acCouchdbManager::getView('mouvement', 'lot');
  }

  public function getByStatut($campagne, $statut) {
    return $this->client->startkey(array(null, $campagne, $statut))
    ->endkey(array(null, $campagne, $statut, array()))
    ->getView($this->design, $this->view);
  }

  public function getByDeclarantIdentifiant($declarant_identifiant, $campagne = null, $statut = null) {
    $query = array($declarant_identifiant);
    if (!is_null($campagne)) {
      $query[] = $campagne;
      if (!is_null($statut)) {
        $query[] = $statut;
      }
    }
    return $this->client->startkey($query)
    ->endkey(array_merge($query, array(array())))
    ->getView($this->design, $this->view);
  }
  
    public function find($etablissementIdentifiant, $campagne, $query) {
        $mouvements = MouvementLotView::getInstance()->getByDeclarantIdentifiant($etablissementIdentifiant, $campagne);

        $mouvement = null;
        foreach ($mouvements->rows as $mouvement) {
            $match = true;
            foreach($query as $key => $value) {
                if($mouvement->value->{ $key } != $value) {
                    $match = false;
                    break;
                }
            }

            if(!$match) {
                continue;
            }

            return $mouvement->value;
        }

        return null;
    }

  public function getDegustationMouvementLot($declarant_identifiant, $numero_archive, $campagne = null, $statut = null){
    foreach ($this->getByDeclarantIdentifiant($declarant_identifiant, $campagne, $statut)->rows as $key => $mvt) {
      if(preg_match("/DEGUSTATION/", $mvt->id) && $mvt->value->numero_archive == $numero_archive){
        if(!preg_match("/(".Lot::STATUT_NONPRELEVABLE.")/", $mvt->value->statut)){
          return $mvt->value;
        }
      }
    }
    return null;
  }

  public function getAllByIdentifiantAndStatuts($declarant_identifiant, $statuts, $campagne = null) {
    $result = array();
    if ($campagne) {
      foreach($statuts as $statut) {
        $start = array($declarant_identifiant, $cStart, $statut);
        $end = array($declarant_identifiant, $cEnd, $statut, array());
        $result = array_merge($result, $this->client->startkey($start)->endkey($end)->getView($this->design, $this->view)->rows);
      }
    } else {
      $sResult = $this->client->startkey(array($declarant_identifiant))->endkey(array($declarant_identifiant, array()))->getView($this->design, $this->view)->rows;
      foreach($sResult as $item) {
        if ($statuts && in_array($item->key[self::KEY_STATUT], $statuts)) {
          $result[] = $item;
        } elseif (!$statuts) {
          $result[] = $item;
        }
      }
    }
    return $result;
  }

  public static function getDestinationLibelle($lot) {
    $libelles = DRevClient::$lotDestinationsType;
    return (isset($libelles[$lot->destination_type]))? $libelles[$lot->destination_type] : '';
  }

  public static function generateLotByMvt($mvt) {
    $lot = new stdClass();
    $lot->date = $mvt->date;
    $lot->id_document = $mvt->origine_document_id;
    $lot->numero_dossier = $mvt->numero_dossier;
    $lot->numero_archive = $mvt->numero_archive;
    $lot->numero_logement_operateur = $mvt->numero_logement_operateur;
    $lot->millesime = $mvt->millesime;
    $lot->volume = $mvt->volume;
    $lot->destination_type = $mvt->destination_type;
    $lot->destination_date = $mvt->destination_date;
    $lot->produit_hash = $mvt->produit_hash;
    $lot->produit_libelle = $mvt->produit_libelle;
    $lot->declarant_nom = $mvt->declarant_nom;
    $lot->declarant_identifiant = $mvt->declarant_identifiant;
    $lot->origine_mouvement = $mvt->origine_mouvement;
    $lot->details = $mvt->details;
    $lot->elevage = (isset($mvt->elevage))? $mvt->elevage : null;
    $lot->statut = $mvt->statut;
    $lot->specificite = (isset($mvt->specificite))? $mvt->specificite : null;
    if(isset($mvt->centilisation)) {
        $lot->centilisation = isset($mvt->centilisation) ? $mvt->centilisation : null;
    }
    if (isset($mvt->nombre_degustation)) {
        $lot->nombre_degustation = $mvt->nombre_degustation;
    }
    return $lot;
  }

  public function getLotStepsByArchive($campagne, $numero_archive){
    $lotsSteps = array_merge(
      ArchivageAllView::getInstance()->getDocsByTypeAndCampagne('Lot',$campagne, $numero_archive,$numero_archive,"%05d"),
      ArchivageAllView::getInstance()->getDocsByTypeAndCampagne('Lot',$campagne, $numero_archive."a",$numero_archive."zzzzz","%s")
    );
    $lotsStepsHistory = array();
    foreach ($lotsSteps as $key => $value) {
      $c = ucfirst(strtolower(preg_replace('/-.*/', '', $value->id))).'Client';
      $doc = $c::getInstance()->find($value->id);
      $k = $value->key[ArchivageAllView::KEYS_NUMERO_ARCHIVE].$value->id;
      $lotsStepsHistory[$k] = $doc->getLotByNumArchive($value->key[ArchivageAllView::KEYS_NUMERO_ARCHIVE]);
    }
    return $lotsStepsHistory;
  }


  public function getLotsStepsByDeclarantIdentifiant($identifiant, $campagne){

    $lotsSteps = array();

    foreach (MouvementLotView::getInstance()->getByDeclarantIdentifiant($identifiant,$campagne)->rows as $item) {
      $key = Lot::generateMvtKey($item->value);
      $key = $item->value->numero_dossier.preg_replace("/[a-z]*$/", "", $item->value->numero_archive);
      if (!isset($lotsSteps[$key])) {
        $lotsSteps[$key] = array();
      }
      if (!isset($lotsSteps[$key][$item->value->id_document])) {
        $lotsSteps[$key][$item->value->id_document] = array();
      }
      $lotsSteps[$key][$item->value->id_document] = $this->constructLotsSteps($item->value);
    }

    foreach ($lotsSteps as $key => $itemsDocs) {
      $chgtdenom = false;
      foreach ($itemsDocs as $keyDoc => $item) {
        $item->chgtdenom = false;
        if($item->origine_type == "chgtdenom"){
          $chgtdenom = $keyDoc;
        }
      }
      if($chgtdenom){
        foreach ($itemsDocs as $keyDoc => $item) {
          if($item->origine_type != "chgtdenom"){
            $item->chgtdenom = $chgtdenom;
          }
        }
      }
      if(count($itemsDocs)>1){
        foreach ($itemsDocs as $keyDoc => $item) {
          $typeDoc =  preg_replace('/-.*/', '', $keyDoc);
          if($typeDoc == DRevClient::TYPE_COUCHDB){
            unset($lotsSteps[$key][$keyDoc]);
          }
        }

      }

    }

    ksort($lotsSteps);
    return $lotsSteps;
  }

  private function constructLotsSteps($item){
    $item->dossier_type = strtolower(preg_replace('/-.*/', '', $item->origine_document_id));
    $item->dossier_libelle = ucfirst($item->dossier_type);
    $client = $item->dossier_libelle."Client";
    $item->dossier_origine = $client::getInstance()->find($item->origine_document_id);

    $item->degustation = false;
    if(preg_replace('/-.*/', '', $item->id_document) == DegustationClient::TYPE_COUCHDB){
      $item->degustation = DegustationClient::getInstance()->find($item->id_document);
      $item->degustation_anchor = $item->numero_dossier.$item->numero_archive;
      $lot = $item->degustation->getLotByNumArchive($item->numero_archive);

      if($item->degustation->isValidee() && in_array($lot->statut,Lot::$statuts_preleves)){
        $item->degustation_step_route = "degustation_preleve";
        $item->degustation_libelle = "prélévé";
        $item->degustation_color = "success";

        $item->numero_table_step_route = "degustation_organisation_table";
        $item->numero_table_color = "default";
        $item->numero_table = null;
        if($lot->numero_table){
          $item->numero_table = $lot->numero_table;
          $item->numero_table_color = "success";

          $item->resultat_step_route = "degustation_resultats";
          $item->resultat_color = "default";
          $item->conformite = null;
          if($lot->exist('conformite') && !is_null($lot->conformite)){
            $item->conformite = $lot->conformite;
            if($lot->conformite == Lot::CONFORMITE_CONFORME){
              $item->resultat_color = "success";
            }else{
              $item->resultat_color = "danger";
            }
          }
        }

      }elseif($item->degustation->isValidee()){
        $item->degustation_step_route = "degustation_preleve";
        $item->degustation_libelle = "préléver";
        $item->degustation_color = "default";
      }else{
        $item->degustation_step_route = "degustation_validation";
        $item->degustation_libelle = "à valider";
        $item->degustation_color = "warning";
      }
    }

    return $item;
  }

}
