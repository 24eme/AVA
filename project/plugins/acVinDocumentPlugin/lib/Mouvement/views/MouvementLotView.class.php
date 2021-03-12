<?php

class MouvementLotView extends acCouchdbView
{
  const KEY_STATUT = 0;

  const VALUE_LOT = 0;

  public static function getInstance() {

    return acCouchdbManager::getView('mouvement', 'lot');
  }

    public function getByStatut($statut) {

        return $this->client->startkey(array($statut))
                            ->endkey(array($statut, array()))
                            ->getView($this->design, $this->view);
    }

    public function getByDeclarantIdentifiant($declarant_identifiant, $campagne = null, $statut = null) {

        throw new sfException("À réimplemter à partir de la vue mouvement lot history");
    }

    public function find($etablissementIdentifiant, $campagne, $query) {

        throw new sfException("À réimplemter à partir de la vue mouvement lot history");
    }

    public function getAllByIdentifiantAndStatuts($declarant_identifiant, $statuts, $campagne = null) {

        throw new sfException("À réimplemter à partir de la vue mouvement lot history");
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
