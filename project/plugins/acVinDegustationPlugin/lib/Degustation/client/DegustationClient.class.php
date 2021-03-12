<?php

class DegustationClient extends acCouchdbClient {

    const TYPE_MODEL = "Degustation";
    const TYPE_COUCHDB = "DEGUSTATION";
    const SPECIFICITE_PASSAGES = "Xème passage";

    public static function getInstance()
    {
        return acCouchdbManager::getClient("Degustation");
    }

    public static function updatedSpecificite($lot) {
      $nb = 2;
      if (preg_match("/.*([0-9]+)".str_replace('X', '', self::SPECIFICITE_PASSAGES).".*/", $lot->specificite, $m)) {
        $nb = ((int)$m[1]) + 1;
      }

      if ($lot->specificite === null) {
          $lot->specificite = str_replace('X', $nb, self::SPECIFICITE_PASSAGES);
      } else {
          $lot->specificite = (strpos($lot->specificite, str_replace('X', '', self::SPECIFICITE_PASSAGES)) !== false)
              ? str_replace($nb - 1, $nb, $lot->specificite)                              // il y a déjà un X passage
              : $lot->specificite.', '.str_replace('X', $nb, self::SPECIFICITE_PASSAGES); // il n'y a pas de passage
      }

      $lot->statut = Lot::STATUT_PRELEVABLE;
      return $lot;
    }

    public function find($id, $hydrate = self::HYDRATE_DOCUMENT, $force_return_ls = false) {
        $doc = parent::find($id, $hydrate, $force_return_ls);
        if($doc && $doc->type != self::TYPE_MODEL) {
            throw new sfException(sprintf("Document \"%s\" is not type of \"%s\"", $id, self::TYPE_MODEL));
        }
        return $doc;
    }

    public function getHistory($limit = 10, $hydrate = acCouchdbClient::HYDRATE_DOCUMENT) {

        return $this->startkey(self::TYPE_COUCHDB."Z")->endkey(self::TYPE_COUCHDB)->descending(true)->limit($limit)->execute($hydrate);
    }

	public function getLotsPrelevables() {
	    $lots = array();
	    foreach (MouvementLotView::getInstance()->getByStatut(Lot::STATUT_AFFECTABLE)->rows as $mouvement) {
	        $lots[$mouvement->key[MouvementLotView::KEY_LOT_UNIQUE_ID]] = $mouvement->value;
	    }
        uasort($lots, function ($lot1, $lot2) {
            $date1 = DateTime::createFromFormat('Y-m-d', $lot1->date);
            $date2 = DateTime::createFromFormat('Y-m-d', $lot2->date);

            if ($date1 == $date2) {
                return 0;
            }
            return ($date1 < $date2) ? -1 : 1;
        });

        return $lots;
	}

    public static function getNumeroTableStr($numero_table){
      $alphas = range('A', 'Z');
      return $alphas[$numero_table-1];
    }

    public function getManquements() {
        $manquements = array();
        foreach (MouvementLotView::getInstance()->getByStatut(null, Lot::STATUT_MANQUEMENT_EN_ATTENTE)->rows as $item) {
            $manquements[Lot::generateMvtKey($item->value)] = $item->value;
        }
        return $manquements;
    }

}
