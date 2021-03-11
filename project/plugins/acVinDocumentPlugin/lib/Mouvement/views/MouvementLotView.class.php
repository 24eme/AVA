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

}
