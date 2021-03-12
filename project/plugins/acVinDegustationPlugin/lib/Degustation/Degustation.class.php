<?php
/**
 * Model for Degustation
 *
 */

class Degustation extends BaseDegustation implements InterfacePieceDocument, InterfaceMouvementLotsDocument {

	protected $piece_document = null;
	protected $tri = null;
	protected $cm = null;

    public function __construct() {
        parent::__construct();
        $this->initDocuments();
				$this->cm = new CampagneManager('08-01', CampagneManager::FORMAT_PREMIERE_ANNEE);
    }

		public function getDateStdr() {
			return ($this->date && preg_match('/^([0-9]{4}-[0-9]{2}-[0-9]{2}).*$/', $this->date, $m))? $m[1] : date ('Y-m-d');
		}

		public function getMaster() {
			return $this;
		}

    public function isLotsEditable(){
      return false;
    }

		public function getCampagneByDate() {
			return $this->cm->getCampagneByDate($this->getDateStdr());
		}

    public function __clone() {
        parent::__clone();
        $this->initDocuments();
    }

    protected function initDocuments() {
        $this->piece_document = new PieceDocument($this);
    }

    public function getConfiguration() {
        return ConfigurationClient::getInstance()->getConfiguration($this->getDateStdr());
    }

    public function constructId() {
				$dateId = str_replace("-", "", preg_replace("/(.+) (.+):(.+)$/","$1$2$3",$this->date));
        $id = sprintf("%s-%s-%s", DegustationClient::TYPE_COUCHDB, $dateId, $this->getLieuNom(true));

        $this->set('_id', $id);
    }


		public function getConfigProduits() {

				return $this->getConfiguration()->declaration->getProduits();
		}

    public function getLieuNom($slugify = false) {
        return self::getNomByLieu($this->lieu, $slugify);
    }

    public static function getNomByLieu($lieu, $slugify = false) {
        if (strpos($lieu, "—") === false) {
            throw new sfException('Le lieu « '.$lieu.' » n\'est pas correctement formaté dans la configuration. Séparateur « — » non trouvé.');
        }
        $lieuExpld = explode('—', $lieu);
        return ($slugify)? KeyInflector::slugify($lieuExpld[0]) : $lieuExpld[0];
    }

    public function getEtablissementObject() {

        return EtablissementClient::getInstance()->find("ETABLISSEMENT-".$this->identifiant);
    }

	protected function doSave() {
		$this->piece_document->generatePieces();
	}

	public function storeEtape($etape) {
	    if ($etape == $this->etape) {

	        return false;
	    }

	    $this->add('etape', $etape);

	    return true;
	}

	public function validate($date = null) {
	    if(is_null($date)) {
	        $date = date('Y-m-d');
	    }
	    $this->validation = $date;
	    $this->updateOrigineLots(Lot::STATUT_NONPRELEVABLE);
	    $this->generateMouvementsLots();
	}


	public function getVersion() {
			return null;
	}

	public function devalidate($reinit_version_lot = true) {
	    $this->validation = null;
	    if($this->exist('etape')) {
	        $this->etape = null;
	    }
	    $this->updateOrigineLots(Lot::STATUT_PRELEVABLE);
	}

	public function findLot($origineMouvement) {
		foreach($this->lots as $lot) {
			if($lot->origine_mouvement != $origineMouvement) {
				continue;
			}

			return $lot;
		}

		return null;
	}

	public function updateOrigineLots($statut) {
	    foreach ($this->lots as $lot) {
          if ($lot->leurre === true) {
          	continue;
          }
          if(!$lot->id_document) {
              continue;
          }
	        $doc = acCouchdbManager::getClient()->find($lot->id_document);
	        if ($doc instanceof InterfaceMouvementLotsDocument) {
	            if ($doc->exist($lot->origine_mouvement)) {
	               $doc->get($lot->origine_mouvement)->set('statut', $statut);
								 $doc->get($doc->get($lot->origine_mouvement)->origine_hash)->set('statut', $statut);
	               $doc->save();
	            }
	        }
	    }
	}

    public function updateLotLogement($lot, $logement)
    {
        $lots = $this->getLots();
        $lots[$lot->getKey()]->numero_logement_operateur = $logement;
        // TODO: voir pour les mouvements
    }

    public function updateLot($key, $lot)
    {
        $this->lots[$key] = $lot;
    }

	public function getInfosDegustation(){
		$infos = array();
		$infos["nbLots"] = count($this->getLots());
		$infos["nbLotsLeurre"] = count($this->getLots()) - count($this->getLotsWithoutLeurre());;
		$infos["nbLotsSansLeurre"] = count($this->getLotsWithoutLeurre());
		$infos['nbLotsPrelevable'] = count(DegustationClient::getInstance()->getLotsPrelevables());
		$infos['nbLotsRestantAPrelever'] = $this->getNbLotsRestantAPreleve();
		$infos['nbLotsPreleves'] = $this->getNbLotsPreleves();
		$infos['nbLotsPrelevesSansLeurre'] = $this->getNbLotsPreleves() - $infos["nbLotsLeurre"];
		$infos["nbAdherents"] = count($this->getAdherentsPreleves());
  	$infos["nbAdherentsLotsRestantAPrelever"] = count($this->getAdherentsByLotsWithStatut(Lot::STATUT_ATTENTE_PRELEVEMENT));
		$infos["nbAdherentsPreleves"] = count($this->getAdherentsPreleves());
		$infos["degustateursConfirmes"] = $this->getDegustateursConfirmes();
		$infos["nbDegustateursConfirmes"] = count($infos["degustateursConfirmes"]);
		$infos["nbDegustateursATable"] = count($this->getDegustateursATable());
		$infos["nbDegustateursSansTable"] = $infos["nbDegustateursConfirmes"] -	$infos["nbDegustateursATable"];
		$infos["degustateurs"] = array();
		foreach (DegustationConfiguration::getInstance()->getColleges() as $college_key => $libelle) {
			$collegeVar = ucfirst(str_replace('_','',$college_key));
			$infos["degustateurs"][$libelle] = array();
			$infos["degustateurs"][$libelle]['confirmes'] = $this->getNbDegustateursStatutWithCollege(true,$college_key);
			$infos["degustateurs"][$libelle]['total'] = count($this->degustateurs->getOrAdd($college_key));
			$infos["degustateurs"][$libelle]['key'] = "nb".$collegeVar;
		}
		$tables = $this->getTablesWithFreeLots();
		$infos["nbTables"] = count($tables);
		$infos["nbFreeLots"] = count($this->getFreeLots());
		$infos["nbLotsDegustes"] = count($this->getLotsDegustes());
		$infos["nbLotsConformes"] = $this->getNbLotsConformes();
		$infos["nbLotsNonConformes"] = $this->getNbLotsNonConformes();
		return $infos;
	}

	private function generateMouvementLotsFromLot($lot, $key) {
			$mvt = new stdClass();
			$mvt->date = $lot->date;
			$mvt->statut = $lot->statut;
            $mvt->doc_ordre = $this->getDocumentOrdre($lot);
			$mvt->numero_dossier = $lot->numero_dossier;
			$mvt->numero_archive = $lot->numero_archive;
			$mvt->numero_logement_operateur = $lot->numero_logement_operateur;
			$mvt->millesime = $lot->millesime;
			$mvt->volume = $lot->volume;
			$mvt->elevage = $lot->elevage;
			$mvt->produit_hash = $lot->produit_hash;
			$mvt->produit_libelle = $lot->produit_libelle;
			$mvt->produit_couleur = $lot->getCouleurLibelle();
			$mvt->region = '';
			$mvt->version = $this->getVersion();
			$mvt->origine_hash = $lot->getHash();
			$mvt->origine_type = 'degustation';
			$mvt->origine_document_id = $this->_id;
			$mvt->id_document = $this->_id;
			$mvt->origine_mouvement = '/mouvements_lots/'.$lot->declarant_identifiant.'/'.KeyInflector::slugify($key);
			$mvt->declarant_identifiant = $lot->declarant_identifiant;
			$mvt->declarant_nom = $lot->declarant_nom;
			$mvt->destination_type = $lot->destination_type;
			$mvt->destination_date = $lot->destination_date;
			$mvt->details = $lot->details;
			$mvt->campagne = $this->getCampagneByDate();
			$mvt->specificite = $lot->specificite;
			$mvt->centilisation = $lot->centilisation;
			$mvt->conformite = $lot->conformite;
			$mvt->motif = $lot->motif;
			return $mvt;
	}

    public function generateMouvementsLots()
    {
        $this->remove('mouvements_lots');

        foreach ($this->lots as $lot) {
            if ($lot->isLeurre()) {
                continue;
            }

            $mouvements = $this->buildMouvementsLot($lot);

            foreach ($mouvements as $key => $mouvement) {
                $this->add('mouvements_lots')->add($mouvement->declarant_identifiant)->add(KeyInflector::slugify($key), $mouvement);
            }
        }
    }

    public function buildMouvementsLot($lot)
    {
        $mvts = [];
        $key = $lot->getUnicityKey();
        $statut_originel = $lot->statut;

        switch($lot->statut) {
            case Lot::STATUT_CONFORME:
            case Lot::STATUT_NONCONFORME:
                $lot->statut = ($lot->statut === Lot::STATUT_CONFORME) ? Lot::STATUT_CONFORME : Lot::STATUT_NONCONFORME;
                $mvts[$key.'-'.$lot->statut] = $this->generateMouvementLotsFromLot($lot, $key.'-'.$lot->statut);

            case Lot::STATUT_DEGUSTE:
                $lot->statut = Lot::STATUT_DEGUSTE;
                $mvts[$key.'-'.$lot->statut] = $this->generateMouvementLotsFromLot($lot, $key.'-'.$lot->statut);

            case Lot::STATUT_ANONYMISE:
                $lot->statut = Lot::STATUT_ANONYMISE;
                $mvts[$key.'-'.$lot->statut] = $this->generateMouvementLotsFromLot($lot, $key.'-'.$lot->statut);

            case Lot::STATUT_ATTABLE:
                $lot->statut = Lot::STATUT_ATTABLE;
                $mvts[$key.'-'.$lot->statut] = $this->generateMouvementLotsFromLot($lot, $key.'-'.$lot->statut);

            case Lot::STATUT_PRELEVE:
                $lot->statut = Lot::STATUT_PRELEVE;
                $mvts[$key.'-'.$lot->statut] = $this->generateMouvementLotsFromLot($lot, $key.'-'.$lot->statut);

            case Lot::STATUT_ATTENTE_PRELEVEMENT:
                $lot->statut = Lot::STATUT_ATTENTE_PRELEVEMENT;
                $mvts[$key.'-'.$lot->statut] = $this->generateMouvementLotsFromLot($lot, $key.'-'.$lot->statut);

            case Lot::STATUT_AFFECTE_DEST:
                $lot->statut = Lot::STATUT_AFFECTE_DEST;
                $mvts[$key.'-'.$lot->statut] = $this->generateMouvementLotsFromLot($lot, $key.'-'.$lot->statut);

            default:
                break;
        }

        if ($statut_originel === Lot::STATUT_NONCONFORME) {
            if ($lot->exist('affectable') && $lot->affectable === true) {
                $lot->statut = Lot::STATUT_AFFECTABLE;
                $mvts[$key.'-'.$lot->statut] = $this->generateMouvementLotsFromLot($lot, $key.'-'.$lot->statut);

                $lot->statut = Lot::STATUT_AFFECTE_SRC;
                $mvts[$key.'-'.$lot->statut] = $this->generateMouvementLotsFromLot($lot, $key.'-'.$lot->statut);
            } else {
                $lot->statut = Lot::STATUT_MANQUEMENT_EN_ATTENTE;
                $mvts[$key.'-'.$lot->statut] = $this->generateMouvementLotsFromLot($lot, $key.'-'.$lot->statut);
            }
        }

        $lot->statut = $statut_originel;

        return $mvts;
    }

    public function getDocumentOrdre($lot)
    {
        // Retourne l'ordre du document en
        // interrogeant la vue
        return 1;
    }

	public function isValidee() {

	    return $this->validation;
	}

    /**** PIECES ****/

    public function getAllPieces() {
    	$pieces = array();
    	return $pieces;
    }

    public function generatePieces() {
    	return $this->piece_document->generatePieces();
    }

    public function generateUrlPiece($source = null) {
    	return null;
    }

    public static function getUrlvisualisationPiece($id, $admin = false) {
    	return null;
    }

    public static function getUrlGenerationCsvPiece($id, $admin = false) {
    	return null;
    }

    public static function isvisualisationMasterUrl($admin = false) {
    	return false;
    }

    public static function isPieceEditable($admin = false) {
    	return false;
    }

	 public function setLotsFromMvtKeys($keys, $statut){
		 $this->remove('lots');
		 $this->add('lots');
		 $mvts = $this->getMvtLotsPrelevables();
		 foreach($keys as $key => $activated) {
			 if (!$activated) {
				continue;
			 }
			 $lot = $this->addLot($mvts[$key], $statut);
		 }
	 }

	 public function getAdherentsByLotsWithStatut($statut = null){
		 $lots = $this->getLotsWithStatut($statut);
		 $lotsByAdherents = array();
		 foreach ($lots as $lot) {
			 if(!array_key_exists($lot->getDeclarantIdentifiant(),$lotsByAdherents)){
				 	$lotsByAdherents[$lot->getDeclarantIdentifiant()] = array();
				}
				$lotsByAdherents[$lot->getDeclarantIdentifiant()][] = $lot;
		 }

 	 	return $lotsByAdherents;
	}

	public function getAdherentsPreleves(){
		$adherents = array();
		foreach ($this->getLots() as $lot) {
				if($lot->isPreleve()){
					$adherents[$lot->getDeclarantIdentifiant()] = $lot->getDeclarantIdentifiant();
				}
		}
	 return $adherents;
 }

	 public function getNbLotsWithStatut($statut = null, $including_leurre = true){
			return count($this->getLotsWithStatut($statut,$including_leurre));
	 }

	 public function getLotsWithStatut($statut = null, $including_leurre = true){
		 if(!$statut){
			 return array();
		 }
		 $lots = array();
		 foreach ($this->getLots() as $lot) {
				if(!$including_leurre && $lot->isLeurre()){
					continue;
				}
				if($lot->statut == $statut){
					$lots[] = $lot;
				}
			}
			return $lots;
	 }

	 public function getLotsByOperateursAndConformites(){
		 $lotsByAdherents = array();
		 $conformiteArray = array(Lot::STATUT_CONFORME,Lot::STATUT_NONCONFORME);
		 foreach ($conformiteArray as $bool => $conformite) {
			 foreach ($this->getLotsConformesOrNot(!$bool) as $lot) {
				 if($lot->isLeurre()){
					 continue;
				 }
				 if(!array_key_exists($lot->getDeclarantIdentifiant(),$lotsByAdherents)){
					 $lotsByAdherents[$lot->getDeclarantIdentifiant()] = new stdClass();
					 $lotsByAdherents[$lot->getDeclarantIdentifiant()]->declarant_nom = $lot->declarant_nom;
                     $lotsByAdherents[$lot->getDeclarantIdentifiant()]->email_envoye = $lot->email_envoye;
                     if(!$lot->email_envoye){
                         $lotsByAdherents[$lot->getDeclarantIdentifiant()]->email_envoye = false;
                     }
					 $lotsByAdherents[$lot->getDeclarantIdentifiant()]->lots = array();
					}
					if(!array_key_exists($conformite,$lotsByAdherents[$lot->getDeclarantIdentifiant()]->lots)){
						$lotsByAdherents[$lot->getDeclarantIdentifiant()]->lots[$conformite] = array();
 					}
				 $lotsByAdherents[$lot->getDeclarantIdentifiant()]->lots[$conformite][] = $lot;
				 if($lotsByAdherents[$lot->getDeclarantIdentifiant()]->email_envoye === false){
                     $lotsByAdherents[$lot->getDeclarantIdentifiant()]->email_envoye = false;
                 }
			 }
		 }
		return $lotsByAdherents;
	 }

	 public function getLotsConformitesOperateur($identifiant){
		$lotsByAdherents = $this->getLotsByOperateursAndConformites();
	 return $lotsByAdherents[$identifiant];
	}

	 public function getLotByNumArchive($numero_archive){
		 foreach ($this->lots as $lot) {
			 if($lot->numero_archive == $numero_archive){
				 return $lot;
			 }
		 }
		 return null;
	 }

	 public function getNbLotsRestantAPreleve(){
		 return $this->getNbLotsWithStatut(Lot::STATUT_ATTENTE_PRELEVEMENT,false);
	 }

	 public function getLotsDegustes(){
		 return array_merge($this->getLotsWithStatut(Lot::STATUT_CONFORME,false),$this->getLotsWithStatut(Lot::STATUT_NONCONFORME,false));
	 }


	 public function getNbLotsPreleves(){
		 return count($this->getLotsPreleves());
	 }

	 public function getNbLotsConformes(){

			return count($this->getLotsConformesOrNot(true));
	 }

	 public function getNbLotsNonConformes(){

		 return count($this->getLotsConformesOrNot(false));
	 }

	 public function getLotsConformesOrNot($conforme = true){
		 $lots = array();
		 foreach ($this->getLotsDegustes() as $lot) {
			 if($conforme && $lot->exist('conformite') && $lot->conformite == Lot::CONFORMITE_CONFORME){
				 $lots[] = $lot;
			 }
			 if(!$conforme && $lot->isNonConforme()){
				 $lots[] = $lot;
			 }
		 }
		 return $lots;
	 }

    /**** FIN DES PIECES ****/


		/**** Gestion des tables de la degustation ****/

		public function getLotsPreleves() {
	   		$lots = array();
	   		foreach ($this->getLots() as $lot) {
	   			if(!$lot->leurre && in_array($lot->statut, array(Lot::STATUT_PRELEVABLE, Lot::STATUT_NONPRELEVABLE, Lot::STATUT_ATTENTE_PRELEVEMENT))) {
	   				continue;
	   			}
	   			$lots[] = $lot;
	   		}
			return $lots;
		}

		public function getLotsPrelevesCustomSort(array $tri = null) {
			$lots = $this->getLotsPreleves();
			if (!$tri) {
				$tri = array('couleur', 'appellation', 'cepage');
			}
			$this->tri = $tri;
	   		uasort($lots, array($this, "sortLotsByThisTri"));
	   		return $lots;
   	 	}

		public function getFreeLots(){
			$freeLots = array();
			foreach ($this->getLotsPreleves() as $lot) {
				if(! $lot->exist('numero_table') || !$lot->numero_table){
					$freeLots[] = $lot;
				}
			}
			return $freeLots;
		}

		public function getTablesWithFreeLots($add_default_table = false){
			$tables = array();
			$freeLots = $this->getFreeLots();
			foreach ($this->lots as $lot) {
				if($lot->exist('numero_table') && $lot->numero_table){
					if(!isset($tables[$lot->numero_table])){
						$tables[$lot->numero_table] = new stdClass();
						$tables[$lot->numero_table]->lots = array();
						$tables[$lot->numero_table]->freeLots = $freeLots;
					}
					$tables[$lot->numero_table]->lots[] = $lot;
				}
			}

			if($add_default_table && !count($tables)){
				$table = new stdClass();
				$table->lots = array();
				$table->freeLots = $freeLots;
				$tables[] = $table;
			}
			return $tables;
		}

		public function getLotsWithoutLeurre(){
			$lots = array();
			foreach ($this->lots as $lot) {
					if ($lot->leurre === true) {
							continue;
					}
					$lots[] = $lot;
			}
			return $lots;
		}

		public function getLotsByTable($numero_table){
			$lots = array();
			foreach ($this->getLots() as $lot) {
				if(intval($lot->numero_table) == $numero_table){
					$lots[] = $lot;
				}
			}
			$this->tri = ['numero_anonymat'];
			usort($lots, array($this, "sortLotsByThisTri"));
 		 	return $lots;
		}

		public function anonymize(){
			for($table = 1; true ; $table++) {
				$lots = $this->getLotsByTable($table);
				if (!count($lots)) {
					break;
				}
				$this->tri = ['couleur','appellation','cépage'];
				usort($lots, array($this, 'sortLotsByThisTri'));
				foreach ($lots as $k => $lot){
					if ($lot->numero_anonymat) {
						throw new sfException("L'anonymat a déjà été réalisé");
					}

                    $lot->anonymize($k);
				}
			}

            $this->generateMouvementsLots();
		}

		public function desanonymize(){
			for($table = 1; true ; $table++) {
				$lots = $this->getLotsByTable($table);
				if (!count($lots)) {
					break;
				}
				foreach ($lots as $k => $lot){
					if ($lot->numero_anonymat){
						$lot->numero_anonymat = null;
					}
				}
			}

            $this->generateMouvementsLots();
		}

		public function isAnonymized(){
			for($table = 1; true ; $table++) {
				$lots = $this->getLotsByTable($table);
				if (!count($lots)) {
					return false;
				}
				foreach ($lots as $k => $lot){
					if ($lot->numero_anonymat) {
					return true;
					}
				}
			}
			return false;
		}

		public function getLotsTableOrFreeLots($numero_table, $free = true){
			$lots = array();
			foreach ($this->getLotsPreleves() as $lot) {
				if(($lot->numero_table == $numero_table)){
					$lots[] = $lot;
					continue;
				}

				if($free && is_null($lot->numero_table))  {
					$lots[] = $lot;
					continue;
				}
			}
			return $lots;
		}

		public function getLotsTableOrFreeLotsCustomSort($numero_table, array $tri,  $free = true){
			$lots = $this->getLotsTableOrFreeLots($numero_table, $free);
			$this->tri = $tri;
			uasort($lots, array($this, 'sortLotsByThisTri'));
			return $lots;
		}

		public function hasFreeLots(){
			foreach ($this->getLotsPreleves() as $lot) {
				if(!$lot->exist("numero_table") || is_null($lot->numero_table)){
					return true;
				}
			}
			return false;
		}

		public function getSyntheseLotsTable($numero_table = null){
			$lots = $this->getLotsPreleves();
			$syntheseLots =  $this->createSynthesFromLots($lots, $numero_table);
			ksort($syntheseLots);
			return $syntheseLots;
		}
		public function getSyntheseLotsTableCustomTri($numero_table = null, array $tri){
			$lots = $this->getLotsPrelevesCustomSort($tri);
			return $this->createSynthesFromLots($lots, $numero_table, $tri);
		}
		private function createSynthesFromLots($lots, $numero_table, array $tri = null) {
			$syntheseLots = array();
			foreach ($lots as $lot) {
				if($lot->numero_table == $numero_table || is_null($numero_table) || is_null($lot->numero_table)){
					if(!array_key_exists($lot->getTriHash($tri),$syntheseLots)){
						$synthese = new stdClass();
						$synthese->lotsTable = array();
						$synthese->lotsFree = array();
						$synthese->libelle = $lot->getTriLibelle($tri);
						$synthese->details = '';
						if (!$tri || in_array('Cépage', $tri)) {
							$synthese->details = $lot->getDetails();
						}
						$synthese->millesime = $lot->getMillesime();

						$syntheseLots[$lot->getTriHash($tri)] = $synthese;
					}
					if($lot->numero_table == $numero_table || (is_null($numero_table) && $lot->numero_table)){
						$syntheseLots[$lot->getTriHash($tri)]->lotsTable[] = $lot;
					}else{
						$syntheseLots[$lot->getTriHash($tri)]->lotsFree[] = $lot;
					}
				}
			}
			return $syntheseLots;
		}

		public function getFirstNumeroTable(){
			$tables = array_keys($this->getTablesWithFreeLots());
			if(!count($tables)) { return 0; }
			return min($tables);
		}

		public function getLastNumeroTable(){
			$tables = array_keys($this->getTablesWithFreeLots());
			if(!count($tables)) { return 0; }
			return max($tables);
		}

    public function sortLotsByThisTri($a, $b){
			$a_data = '';
			$b_data = '';
			foreach($this->tri as $t) {
				$a_data .= $a->getValueForTri($t);
				$b_data .= $b->getValueForTri($t);
				if ( $this->tri == ['numero_anonymat']){
					$cmp = $a_data-$b_data;
					if ($cmp !=0) {
						return $cmp;
					}
				}
				else{
					$cmp = strcmp($a_data, $b_data);
					if ($cmp) {
					return $cmp;
					}
				}
			}
      return 0;
      }
    public function addLeurre($hash, $cepages, $numero_table)
        {
            if (! $this->exist('lots')) {
                $this->add('lots');
            }
            $leurre = $this->lots->add();
            $leurre->leurre = true;
            $leurre->numero_table = $numero_table;
            $leurre->setProduitHash($hash);
						$leurre->details = $cepages;

						$leurre->statut = Lot::STATUT_NONPRELEVABLE;

            return $leurre;
        }

		/**** Fin Gestion des tables de la degustation ****/


		/**** Gestion dégustateurs ****/

		public function getNbDegustateursStatutWithCollege($confirme = true ,$college = null){
			return count($this->getDegustateursStatutWithCollege($confirme,$college));
		}

		public function getDegustateursStatutWithCollege($confirme = true ,$college = null){
			$degustateurs = array();
			foreach ($this->getDegustateursStatutsParCollege() as $collegeDegs => $degs) {
				if($collegeDegs != $college){
					continue;
				}
				foreach ($degs as $compte_id => $confirmeDeg) {
						if($confirmeDeg == $confirme){
							$degustateurs[] = $compte_id;
						}
					}
				}
			return  $degustateurs;
		}

		public function getDegustateursStatutsParCollege(){
			$degustateursByCollege = array();
			foreach ($this->degustateurs as $college => $degs) {
				if(!array_key_exists($college,$degustateursByCollege)){
					$degustateursByCollege[$college] = array();
				}
				foreach ($degs as $compte_id => $degustateur) {
						$degustateursByCollege[$college][$compte_id] = ($degustateur->exist('confirmation') && !is_null($degustateur->confirmation) && $degustateur->confirmation);
					}
			}
			return $degustateursByCollege;
		}


		public function getDegustateursConfirmes(){
			$degustateurs = array();
			foreach ($this->degustateurs as $college => $degs) {
				foreach ($degs as $compte_id => $degustateur) {
					if($degustateur->exist('confirmation') && !is_null($degustateur->confirmation)){
						$degustateurs[$compte_id] = $degustateur;
					}
				}
			}
			return $degustateurs;
		}

		public function getDegustateursConfirmesTableOrFreeTable($numero_table = null){
			$degustateurs = array();
			foreach ($this->getDegustateursConfirmes() as $id => $degustateur) {
				if(($degustateur->exist('numero_table') && $degustateur->numero_table == $numero_table)
					|| (!$degustateur->exist('numero_table') || is_null($degustateur->numero_table))){
					$degustateurs[$id] = $degustateur;
				}
			}
			return $degustateurs;
		}

		public function getDegustateursATable(){
			$degustateurs = array();
			foreach ($this->degustateurs as $college => $degs) {
				foreach ($degs as $compte_id => $degustateur) {
					if($degustateur->exist('numero_table') && !is_null($degustateur->numero_table)){
						$degustateurs[$compte_id] = $degustateur;
					}
				}
			}
			return $degustateurs;
		}

		public function hasAllDegustateursConfirmation(){
			$confirmation = true;
			foreach ($this->getDegustateurs() as $collegeKey => $degustateursCollege) {
				foreach ($degustateursCollege as $compte_id => $degustateur) {
					if(!$degustateur->exist('confirmation')){
						$confirmation = false;
						break;
					}
				}
			}
			return $confirmation;
		}

		/**** Fin Gestion dégustateurs ****/

		/**** Gestion PDF ****/

		public function getEtiquettesFromLots($maxLotsParPlanche){
			$nbLots = 0;
			$planche = 0;
			$etiquettesPlanches = array();
			$etablissements = array();
			$produits = array();
			$lots = array();

			foreach ($this->getLots() as $key => $lot) {
				if($lot->leurre)
					continue;
				$lots[] = $lot;
			}
			$lotsSorted = $lots;

			usort($lotsSorted, function ($lot1, $lot2) {
					if (strcmp($lot1->declarant_nom, $lot2->declarant_nom) === 0) {
							return 0;
					}
					return strcmp($lot1->declarant_nom, $lot2->declarant_nom) < 0  ? -1 : 1;
			});

			foreach ($lotsSorted as $lot) {
				if($nbLots > $maxLotsParPlanche){
					$planche++;
					$nbLots = 0;
				}
				if(!array_key_exists($planche,$etiquettesPlanches)){
					$etiquettesPlanches[$planche] = array();
				}

				if(!array_key_exists($lot->declarant_identifiant,$etablissements)){
					$etablissements[$lot->declarant_identifiant] = EtablissementClient::getInstance()->findByIdentifiant($lot->declarant_identifiant);
				}

				if(!array_key_exists($lot->produit_hash,$produits)){
					$produits[$lot->produit_hash] = $lot->getConfig()->getCouleur()->getLibelle();
				}

				$infosLot = new stdClass();
				$infosLot->lot = $lot;
				$infosLot->etablissement = $etablissements[$lot->declarant_identifiant];
				$infosLot->couleur = $produits[$lot->produit_hash];
				$etiquettesPlanches[$planche][] = $infosLot;
				$nbLots++;
			}
			return $etiquettesPlanches;
		}

		public function getAllLotsTables(){
			$tables = $this->getTablesWithFreeLots();
      $allTablesLots = array();
      foreach ($tables as $key => $value) {
        foreach ($value as $lot) {
          if(!$lot)
            continue;
          $allTablesLots = array_merge($allTablesLots, $lot);
        }

      }
			return $allTablesLots;
		}

		public function getLotTableBySlice($slice){
			$allTablesLots = $this->getAllLotsTables();
			$lotsBySlice = array();
			$cpt = 0;
			$n = intval(count($allTablesLots)/$slice);
			foreach ($allTablesLots as $key => $lot) {
				if($cpt < $slice){
					$cpt++;
				}else {
					$n--;
					$cpt = 1;
				}
				$lotsBySlice[$n][] = $lot;
			}
			return $lotsBySlice;
		}

		public function getLotsByNumDossier(){
			$lots = array();
			foreach ($this->getLotsTablesByNumAnonyme() as $numTab => $lotTable) {
				foreach ($lotTable as $numAnonyme => $lot) {
					$lots[$lot->numero_dossier][$numAnonyme] = $lot;
				}
			}

			return $lots;
		}

		public function getLotsByNumDossierNumCuve(){
			$lots = array();
			foreach ($this->getLots() as  $lot) {
				if($lot->numero_logement_operateur)
					$lots[$lot->numero_dossier][$lot->numero_logement_operateur] = $lot;
				else
					$lots[$lot->numero_dossier][$lot->numero_archive] = $lot;
			}

			return $lots;
		}

		public function getOdg(){
			return sfConfig::get('sf_app');
		}

		public function getLotsTablesByNumAnonyme(){
			$lots = array();
			for($numTab=1; $numTab <= $this->getLastNumeroTable(); $numTab++) {
				$table = chr($numTab+64);
				foreach ($this->getLotsByTable($numTab) as $key => $lot) {
					$lots[$numTab][$lot->getNumeroAnonymat()] = $lot;
				}
			}
			return $lots;
		}

		public function getComptesDegustateurs(){
			$arrayAssocDegustCompte = array();
			foreach ($this->getDegustateursStatutsParCollege() as $college => $degs) {
				if(count($degs)){
					foreach ($degs as $id_compte => $value) {
						$compte = CompteClient::getInstance()->findByIdentifiant($id_compte);
						$arrayAssocDegustCompte[$college][$id_compte] = $compte;
					}
				}
			}
			return $arrayAssocDegustCompte;
		}

		public function getVolumeLotsConformesOrNot($conforme = true){
			$volume = 0;
			foreach ($this->getLotsDegustes() as $lot) {
				if($conforme && $lot->exist('conformite') && $lot->conformite == Lot::CONFORMITE_CONFORME){
					$volume += $lot->volume;
				}
				if(!$conforme && $lot->isNonConforme()){
					$volume += $lot->volume;
				}
			}
			return $volume;
		}

		public function getEtablissementLotsConformesOrNot($conforme = true){
			$etablissements = array();

			foreach ($this->getLotsDegustes() as $lot) {
				$etablissement = EtablissementClient::getInstance()->findByIdentifiant($lot->declarant_identifiant);
				if($conforme && $lot->exist('conformite') && $lot->conformite == Lot::CONFORMITE_CONFORME){
					$etablissements[$lot->declarant_identifiant] = $etablissement;
				}
				if(!$conforme && $lot->isNonConforme()){
					$etablissements[$lot->declarant_identifiant] = $etablissement;
				}
			}
			return $etablissements;
		}

		public function isMailEnvoyeEtablissement($identifiant){
				return boolval($this->getLotsConformitesOperateur($identifiant)->email_envoye);
		}

		public function setMailEnvoyeEtablissement($identifiant, $date){
				foreach ($this->getLotsConformitesOperateur($identifiant)->lots as $conformite => $lots) {
					foreach ($lots as $lot) {
						$lot->email_envoye = $date;
					}
				}
		}

		public function getLotsDegustesByAppelation(){
			$degust = array();
			foreach ($this->getLotsDegustes() as $key => $lot) {
				$degust[$lot->getConfig()->getAppellation()->getLibelle()][] = $lot;
			}

			return $degust;
		}

		public function addLot($mouvement, $statut) {

			$lot = $this->lots->add(null, MouvementLotView::generateLotByMvt($mouvement));
            $lot->statut = $statut;
            return $lot;
		}

		public function getNbLotByTypeForNumDossier($numDossier){
			$lots = array();
			foreach ($this->getLotsByNumDossierNumCuve()[$numDossier] as $numCuve => $lot) {
				$lots[$lot->getTypeLot()] +=1;
			}
			return $lots;
		}

		/** Mis à jour par la degustation du volume d'un lot de DRev **/
		public function modifyVolumeLotAndCreateDRevModificatrice($hash_lot,$volume){

			$lot = $this->get($hash_lot);

			// Drev => modificatrice + changement dans Drev
			$drevOriginal = DRevClient::getInstance()->find($lot->id_document);
			$mvtLotDrevOriginal = $drevOriginal->get($lot->origine_mouvement);

			$hashOriginalLot = $mvtLotDrevOriginal->origine_hash;
			$lotDrevOriginal = $drevOriginal->get($hashOriginalLot);
            $lotDrevOriginalToSave = clone $lotDrevOriginal;

			// $modificatrice
			$modificatrice = $drevOriginal->generateModificative();
			$modificatrice->save();

			$modificatrice = DRevClient::getInstance()->find($modificatrice->_id);


		    $lotModificatrice = $modificatrice->get($hashOriginalLot);
            $lotModificatrice->volume = $volume;
            $lotModificatrice->statut = Lot::STATUT_PRELEVABLE;

            $modificatrice->validate();
			$modificatrice->validateOdg();
			$modificatrice->generateMouvementsLots();
			$modificatrice->save();

			$lot->volume = $volume;
            $lot->id_document = $modificatrice->_id;
			$lot->origine_mouvement = $modificatrice->getMouvementLotFromLot($lotModificatrice)->getHash();

		}

}
