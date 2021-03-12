<?php

/**
 * Model for DRev
 *
 */
class DRev extends BaseDRev implements InterfaceProduitsDocument, InterfaceVersionDocument, InterfaceDeclarantDocument, InterfaceDeclaration, InterfaceMouvementFacturesDocument, InterfacePieceDocument, InterfaceMouvementLotsDocument, InterfaceArchivageDocument {

    const CUVE = 'cuve_';
    const BOUTEILLE = 'bouteille_';
    const CUVE_ALSACE = 'cuve_ALSACE';
    const CUVE_GRDCRU = 'cuve_GRDCRU';
    const CUVE_VTSGN = 'cuve_VTSGN';
    const BOUTEILLE_ALSACE = 'bouteille_ALSACE';
    const BOUTEILLE_GRDCRU = 'bouteille_GRDCRU';
    const BOUTEILLE_VTSGN = 'bouteille_VTSGN';
    const DEFAULT_KEY = 'DEFAUT';

    public static $prelevement_libelles = array(
        self::CUVE => "Dégustation conseil",
        self::BOUTEILLE => "Contrôle externe",
    );
    public static $prelevement_libelles_produit_type = array(
        self::CUVE => "Cuve ou fût",
        self::CUVE_VTSGN => "Cuve, fût ou bouteille",
        self::BOUTEILLE => "Bouteille",
    );
    public static $prelevement_appellation_libelles = array(
        self::CUVE => "Cuve ou fût",
        self::CUVE_VTSGN => "Cuve, fût ou bouteille",
        self::BOUTEILLE => "Bouteille",
    );
    public static $prelevement_keys = array(
        self::CUVE_ALSACE,
        self::CUVE_GRDCRU,
        self::CUVE_VTSGN,
        self::BOUTEILLE_ALSACE,
        self::BOUTEILLE_GRDCRU,
        self::BOUTEILLE_VTSGN,
    );

    protected $declarant_document = null;
    protected $mouvement_document = null;
    protected $version_document = null;
    protected $piece_document = null;
    protected $csv_douanier = null;
    protected $document_douanier_type = null;
    protected $archivage_document = null;

    public function __construct() {
        parent::__construct();
        $this->initDocuments();
    }

    public function __clone() {
        parent::__clone();
        $this->initDocuments();
    }

    protected function initDocuments() {
        $this->declarant_document = new DeclarantDocument($this);
        $this->mouvement_document = new MouvementFacturesDocument($this);
        $this->version_document = new VersionDocument($this);
        $this->piece_document = new PieceDocument($this);
        $this->archivage_document = new ArchivageDocument($this);
        $this->csv_douanier = null;
    }

    public function constructId() {
        $id = 'DREV-' . $this->identifiant . '-' . $this->campagne;
        if($this->version) {
            $id .= "-".$this->version;
        }
        $this->set('_id', $id);
    }

    public function getConfiguration() {
        $configuration = ConfigurationClient::getInstance()->getConfiguration($this->campagne.'-10-01');
        if(ConfigurationConfiguration::getInstance()->hasEffervescentVinbase()){
          $configuration->setEffervescentVindebaseActivate();
        }
        return $configuration;
    }

    public function getProduits($region = null) {

        return $this->declaration->getProduits($region);
    }

    public function getProduitsWithoutLots($region = null) {

        return $this->declaration->getProduitsWithoutLots($region);
    }

    public function getProduitsVci($region = null) {

        return $this->declaration->getProduitsVci($region);
    }

    public function getProduitsLots($region = null) {

        return $this->declaration->getProduitsLots($region);
    }

    public function summerizeProduitsLotsByCouleur() {
        $couleurs = array();

        // Parcours dans le noeud declaration
        foreach($this->getProduitsLots() as $h => $p) {
            $couleur = $p->getConfig()->getCouleur()->getLibelleComplet();
            if (!isset($couleurs[$couleur])) {
                $couleurs[$couleur] = array('volume_total' => 0, 'superficie_totale' => 0, 'volume_max' => 0, 'volume_lots' => 0, 'volume_restant' => 0);
            }
            if(isset($couleurs[$couleur]['volume_total']) && $p->canCalculTheoriticalVolumeRevendiqueIssuRecolte()) {
                $couleurs[$couleur]['volume_total'] += $p->getTheoriticalVolumeRevendiqueIssuRecole();
            } else {
                unset($couleurs[$couleur]['volume_total']);
            }
            $couleurs[$couleur]['volume_max'] += ($p->canCalculTheoriticalVolumeRevendiqueIssuRecolte()) ? $p->getTheoriticalVolumeRevendiqueIssuRecole() : $p->recolte->volume_sur_place;
            $couleurs[$couleur]['superficie_totale'] += $p->superficie_revendique;
        }

        // Parcours dans les lots
        foreach($this->lots as $lot) {

          $couleur = $lot->getProduitRevendiqueLibelleComplet();
          if($lot->getProduitRevendique()){
            $couleur = $lot->getProduitRevendique()->getConfig()->getCouleur()->getLibelleComplet();
          }

          if (!isset($couleurs[$couleur]['volume_lots'])) {
              $couleurs[$couleur]['volume_lots'] = 0;
          }
            $couleurs[$couleur]['volume_lots'] += $lot->volume;
        }
        foreach($couleurs as $k => $couleur) {
            if (!isset($couleur['volume_lots'])) {
                $couleur['volume_lots'] = 0;
            }
            if (isset($couleur['volume_total'])) {
                $couleurs[$k]['volume_restant'] = $couleur['volume_total'] - $couleur['volume_lots'];
            }
        }
        return $couleurs;
    }

    public function getLotsRevendiques() {
        $lots = array();
        foreach ($this->getLots() as $lot) {
            if(!$lot->hasVolumeAndHashProduit()){
                continue;
            }

            $lots[] = $lot;
       }

       return $lots;
    }

    public function getLotByNumArchive($numero_archive){
      foreach ($this->lots as $lot) {
        if($lot->numero_archive == $numero_archive){
          return $lot;
        }
      }
      return null;
    }

    public function getLotsByCouleur($visualisation = true) {
        $couleurs = array();

        foreach($this->getProduitsLots() as $h => $p) {
            $couleurs[$p->getConfig()->getCouleur()->getLibelleComplet()] = array();
        }

        foreach ($this->getLots() as $lot) {
           if($visualisation && !$lot->hasVolumeAndHashProduit()){
             continue;
           }
          $couleur = "vide";
          if($lot->produit_hash){
            $couleur = $lot->getConfigProduit()->getCouleur()->getLibelleComplet();
          }
            if (!isset($couleurs[$couleur])) {
                $couleurs[$couleur] = array();
            }
            $couleurs[$couleur][] = $lot;
        }
        return $couleurs;
    }

    public function getLotsHorsDR(){
      $lotsDR = $this->summerizeProduitsLotsByCouleur();
      $lotsHorsDR = array();
      foreach ($this->getLots() as $key => $lot) {

        if(!isset($lotsDR[$lot->produit_libelle])){
            $lotsHorsDR[$lot->produit_libelle] = $lot;
        }
      }
      return $lotsHorsDR;
    }

    public function getLots(){
        if(!$this->exist('lots')) {

            return array();
        }
        $lots = $this->_get('lots')->toArray(1,1);
        if($lots){
            return $this->_get('lots');
        }
        uasort($lots, "DRev::compareLots");
        return $lots;
    }

    public static function compareLots($lotA, $lotB){
        $dateA = $lotA->getDate();
        $dateB = $lotB->getDate();
        if(empty($dateA)){
            if(!empty($dateB)){
                return $dateB;
            }
        }
        return strcasecmp($dateA, $dateB);
    }

    public function getConfigProduits() {

        return $this->getConfiguration()->declaration->getProduits();
    }

    public function mustDeclareCepage() {

        return $this->isNonRecoltant() || $this->hasDR();
    }

    public function isNonRecoltant() {

        return $this->exist('non_recoltant') && $this->get('non_recoltant');
    }

    public function isNonConditionneur() {

        return $this->exist('non_conditionneur') && $this->get('non_conditionneur');
    }

    public function isLectureSeule() {

        return $this->exist('lecture_seule') && $this->get('lecture_seule');
    }

    public function isNonVinificateur() {

        return $this->exist('non_vinificateur') && $this->get('non_vinificateur');
    }

    public function isNonConditionneurJustForThisMillesime() {

        return $this->isNonConditionneur() && $this->chais->exist(Drev::BOUTEILLE);
    }

    public function isPapier() {

        return $this->exist('papier') && $this->get('papier');
    }

    public function isAutomatique() {

        return $this->exist('automatique') && $this->get('automatique');
    }

    public function getValidation() {

        return $this->_get('validation');
    }

    public function getValidationOdg() {

        return $this->_get('validation_odg');
    }

    public function hasDR() {
        return ($this->getDR());
    }

    public function getDR() {

        return $this->getDocumentDouanier();
    }

    public function getDocumentsDouaniers($ext = null, $hydrate = acCouchdbClient::HYDRATE_DOCUMENT) {
        $etablissements = $this->getEtablissementObject()->getMeAndLiaisonOfType(EtablissementClient::TYPE_LIAISON_METAYER);
        $fichiers = array();
        foreach($etablissements as $e) {
            $f = $this->getDocumentDouanier($ext, $e->identifiant, $hydrate);
            if ($f) {
                $fichiers[] = $f;
            }
        }
        return $fichiers;
    }

    public function getDocumentDouanier($ext = null, $identifiant = null, $hydrate = acCouchdbClient::HYDRATE_DOCUMENT) {
        if (!$identifiant) {
            $identifiant = $this->identifiant;
        }

        foreach(array("DR", "SV12", "SV11") as $type) {
            $fichier = FichierClient::getInstance()->findByArgs($type, $identifiant, $this->campagne);
            if (!$fichier) {
                continue;
            }
            return ($ext)? $fichier->getFichier($ext) : $fichier;
        }

        return null;
    }

    public function hasDocumentDouanier() {
        $a = $this->getDocumentsDouaniers();
        if (!$a) {
            return false;
        }
        return count($a);
    }

    public function getDocumentDouanierType() {
        if(!is_null($this->document_douanier_type)) {
            return $this->document_douanier_type;
        }

        if($this->declarant->famille == EtablissementFamilles::FAMILLE_PRODUCTEUR || $this->declarant->famille == EtablissementFamilles::FAMILLE_PRODUCTEUR_VINIFICATEUR) {

            return DRCsvFile::CSV_TYPE_DR;
        }

        if($this->declarant->famille == EtablissementFamilles::FAMILLE_COOPERATIVE) {

            return SV11CsvFile::CSV_TYPE_SV11;
        }
        if(preg_match('/^'.EtablissementFamilles::FAMILLE_NEGOCIANT.'/', $this->declarant->famille)) {

            return SV12CsvFile::CSV_TYPE_SV12;
        }

        $document = $this->getDocumentDouanier(null, null, acCouchdbClient::HYDRATE_JSON);

        $this->document_douanier_type = ($document) ? $document->type : null;

        return $this->document_douanier_type;
    }

    public function getDocumentDouanierClient()
    {
    	$type = $this->getDocumentDouanierType();
    	if ($type == DRCsvFile::CSV_TYPE_DR) {
    		return DRClient::getInstance();
    	}
    	if ($type == SV11CsvFile::CSV_TYPE_SV11) {
    		return SV11Client::getInstance();
    	}
    	if ($type == SV12CsvFile::CSV_TYPE_SV12) {
    		return SV12Client::getInstance();
    	}
    	return null;
    }

    public function getDocumentDouanierTypeLibelle() {

        if(!$this->getDocumentDouanierType()) {

            return "Données de la récolte";
        }

        if($this->getDocumentDouanierType() == "DR") {

            return "Déclaration de récolte";
        }

        return $this->getDocumentDouanierType();
    }

    public function initDoc($identifiant, $campagne) {
        $this->identifiant = $identifiant;
        $this->campagne = $campagne;
        $etablissement = $this->getEtablissementObject();
    }

    public function getCSV() {
        $csv = new DRCsvFile($this->getAttachmentUri('DR.csv'));
        return $csv->getCsv();
    }

    public function getCsvFromDocumentDouanier() {

        if ($this->csv_douanier != null) {
            return $this->csv_douanier;
        }
    	if (!$this->hasDocumentDouanier()) {
    		return null;
    	}

    	$typeDocumentDouanier = $this->getDocumentDouanierType();
    	$csvFiles = $this->getDocumentsDouaniers('csv');


    	if (!count($csvFiles)) {
    		$docDouanier = $this->getDocumentDouanier();
    		if ($docDouanier &&  $docDouanier->exist('donnees') && count($docDouanier->donnees) >= 1) {
    			$className = DeclarationClient::getInstance()->getExportCsvClassName($typeDocumentDouanier);
    			$csvOrigine = new $className($docDouanier, false);
    			$this->csv_douanier = $csvOrigine->getCsv();
    		}
            return $this->csv_douanier;
    	}

        $csvContent = '';
        foreach($csvFiles as $a_csv_file) {
    	    $csvOrigine = DouaneImportCsvFile::getNewInstanceFromType($typeDocumentDouanier, $a_csv_file);
            if ($csvOrigine) {
    	        $csvContent .= $csvOrigine->convert();
            }
        }

    	if (!$csvContent) {
    		return null;
    	}
    	$path = sfConfig::get('sf_cache_dir').'/dr/';
    	$filename = $csvOrigine->getCsvType().'-'.$this->identifiant.'-'.$this->campagne.'.csv';
    	if (!is_dir($path)) {
    		if (!mkdir($path)) {
    			throw new sfException('cannot create '.$path);
    		}
    	}
    	file_put_contents($path.$filename, $csvContent);
    	$csv = DouaneCsvFile::getNewInstanceFromType($csvOrigine->getCsvType(), $path.$filename);
        $this->csv_douanier = $csv->getCsv();

    	return $this->csv_douanier;
    }

    public function getFictiveFromDocumentDouanier() {
    	$drev = clone $this;
    	$drev->remove('declaration');
    	$drev->add('declaration');
    	$drev->importFromDocumentDouanier();
    	return $drev;
    }

    public function getBailleurs() {
    	$csv = $this->getCsvFromDocumentDouanier();
      if (!$csv) {
        return array();
      }
        $etablissement = $this->getEtablissementObject();
        $etablissementBailleurs = array();
        foreach($etablissement->getMeAndLiaisonOfType(EtablissementClient::TYPE_LIAISON_BAILLEUR) as $etablissementBailleur) {
            if(!$etablissementBailleur->ppm) {
                continue;
            }
            if(!$etablissementBailleur->exist('liaisons_operateurs/METAYER_'.$etablissement->_id)) {
                continue;
            }
            $etablissementBailleurs[$etablissementBailleur->ppm] = $etablissementBailleur;
        }


    	$bailleurs = array();
    	foreach($csv as $line) {
    		$produitConfig = $this->getConfiguration()->findProductByCodeDouane($line[DRCsvFile::CSV_PRODUIT_INAO]);
    		if(!$produitConfig) {
    			continue;
    		}
    		if (!$produitConfig->isActif()) {
    			continue;
    		}

    		if($line[DouaneCsvFile::CSV_TYPE] == DRCsvFile::CSV_TYPE_DR && trim($line[DRCsvFile::CSV_BAILLEUR_PPM])) {
                $etablissement_id = isset($etablissementBailleurs[$line[DRCsvFile::CSV_BAILLEUR_PPM]]) ? $etablissementBailleurs[$line[DRCsvFile::CSV_BAILLEUR_PPM]]->_id : null;
                $id = ($etablissement_id) ? $etablissement_id : $line[DRCsvFile::CSV_BAILLEUR_PPM];
    			$bailleurs[$id]  = array('raison_sociale' => $line[DRCsvFile::CSV_BAILLEUR_NOM], 'etablissement_id' => $etablissement_id, 'ppm' => $line[DRCsvFile::CSV_BAILLEUR_PPM]);
    		}
    	}
    	return $bailleurs;
    }

    public function importFromDocumentDouanier($force = false) {
      if (!$force && count($this->declaration) && $this->declaration->getTotalTotalSuperficie()) {
        return false;
      }
      $csv = $this->getCsvFromDocumentDouanier();
      if (!$csv) {
      	return false;
      }
	  try {
        $this->importCSVDouane($csv);
        return true;
      } catch (Exception $e) { }
      return false;
    }

    public function importCSVDouane($csv) {
    	$todelete = array();
        $bailleurs = array();

        $preserve = false;
        if(count($this->declaration) > true) {
            $preserve = true;
        }

        $produitsImporte = array();
        $has_bio = false;

        $has_bailleurs_or_multiple = 0;
        $first_cvi = $csv[0][DRCsvFile::CSV_RECOLTANT_CVI];
        foreach($csv as $k => $line) {
            if ($line[DRCsvFile::CSV_BAILLEUR_PPM]) {
                $has_bailleurs_or_multiple = true;
                break;
            }
            if ($first_cvi != $line[DRCsvFile::CSV_RECOLTANT_CVI]) {
                $has_bailleurs_or_multiple = true;
                break;
            }
        }
        $cvi = $this->declarant->cvi;
        $ppm = $this->declarant->ppm;
        $known_produit = array();
        foreach($csv as $k => $line) {
            $is_bailleur = false;

            $produitConfig = null;
            $produitConfigAlt = null;

            if($line[DouaneCsvFile::CSV_TYPE] == DRCsvFile::CSV_TYPE_DR && $line[DRCsvFile::CSV_LIGNE_CODE] == DRCsvFile::CSV_LIGNE_CODE_ACHAT_TOLERANCE) {
                $this->add('achat_tolerance', 1);
                continue;
            }

            if (!isset($known_produit[$line[DRCsvFile::CSV_PRODUIT_INAO]])) {
                $produitConfig = $this->getConfiguration()->findProductByCodeDouane($line[DRCsvFile::CSV_PRODUIT_INAO]);
                if(!$produitConfig) {
                    if (preg_match('/([a-zA-Z0-9]{5,6}) ([0-9]{1,2})/', $line[DRCsvFile::CSV_PRODUIT_INAO], $m)) {
                        $produitConfig = $this->getConfiguration()->findProductByCodeDouane($m[1]);
                    }
                }
                $known_produit[$line[DRCsvFile::CSV_PRODUIT_INAO]] = $produitConfig;
            }else{
                $produitConfig = $known_produit[$line[DRCsvFile::CSV_PRODUIT_INAO]];
            }

            if (!$produitConfig) {
            	continue;
            }
            if (!$produitConfig->isActif()) {
            	continue;
            }

            if($line[DRCsvFile::CSV_PRODUIT_COMPLEMENT]) {
                if (!isset($known_produit[$produitConfig->getLibelleComplet()." ". $line[DRCsvFile::CSV_PRODUIT_COMPLEMENT]])) {
                    $produitConfigAlt = $this->getConfiguration()->identifyProductByLibelle($produitConfig->getLibelleComplet()." ". $line[DRCsvFile::CSV_PRODUIT_COMPLEMENT]);
                    $known_produit[$produitConfig->getLibelleComplet()." ". $line[DRCsvFile::CSV_PRODUIT_COMPLEMENT]] = $produitConfigAlt;
                }else{
                    $produitConfigAlt = $known_produit[$produitConfig->getLibelleComplet()." ". $line[DRCsvFile::CSV_PRODUIT_COMPLEMENT]];
                }
            }

            if(isset($produitConfigAlt) && $produitConfigAlt && $produitConfigAlt->isActif()) {
                $produitConfig = $produitConfigAlt;
                $line[DRCsvFile::CSV_PRODUIT_COMPLEMENT] = null;
            }

            $complement = null;

            if (DRevConfiguration::getInstance()->hasDenominationAuto() &&
                  ( $this->hasDenominationAuto(DRevClient::DENOMINATION_BIO_TOTAL) || preg_match('/ bio|^bio| ab$/i', $line[DRCsvFile::CSV_PRODUIT_COMPLEMENT]) )
                ) {
              $has_bio = true;
              $complement = DRevClient::DENOMINATION_BIO_LIBELLE_AUTO;
          } elseif ($line[DouaneCsvFile::CSV_TYPE] == DRCsvFile::CSV_TYPE_DR && DRevConfiguration::getInstance()->hasImportDRWithMentionsComplementaire() && $line[DRCsvFile::CSV_PRODUIT_COMPLEMENT]) {
                $complement = $line[DRCsvFile::CSV_PRODUIT_COMPLEMENT];
            }

            if($line[DouaneCsvFile::CSV_TYPE] == DRCsvFile::CSV_TYPE_DR && trim($line[DRCsvFile::CSV_BAILLEUR_PPM])) {
                $is_bailleur = true;
                if($complement) {
                    $complement .= " - ";
                }
                $complement .= $line[DRCsvFile::CSV_RECOLTANT_LIBELLE];
            }
            $produit = $this->addProduit($produitConfig->getHash(), $complement, $line[DRCsvFile::CSV_COLONNE_ID]);

            if($is_bailleur) {
                $bailleurs[$produit->getHash()] = $produit->getHash();
            }

            if ($is_bailleur && (!$has_bailleurs_or_multiple || !$ppm || $ppm != trim($line[DRCsvFile::CSV_BAILLEUR_PPM]))) {
                continue;
            }
            if (!$is_bailleur && $has_bailleurs_or_multiple && (!$cvi || $cvi != trim($line[DRCsvFile::CSV_RECOLTANT_CVI]))) {
                continue;
            }

            if(!array_key_exists($produit->getHash(), $produitsImporte)) {
                $produit->remove('recolte');
                $produit->add('recolte');
                $produitsImporte[$produit->getHash()] = $produit;
            }

            $produitRecolte = $produit->recolte;

            if($line[DouaneCsvFile::CSV_TYPE] == DRCsvFile::CSV_TYPE_DR && $line[DRCsvFile::CSV_LIGNE_CODE] == DRCsvFile::CSV_LIGNE_CODE_RECOLTE) {
            	$produitRecolte->volume_total += VarManipulator::floatize($line[DRCsvFile::CSV_VALEUR]);
            }
            if ($line[DouaneCsvFile::CSV_TYPE] == DRCsvFile::CSV_TYPE_DR && $line[DRCsvFile::CSV_LIGNE_CODE] == DRCsvFile::CSV_LIGNE_CODE_USAGESIND) {
            	$produitRecolte->usages_industriels_total += VarManipulator::floatize($line[DRCsvFile::CSV_VALEUR]);
            }
            if ($line[DouaneCsvFile::CSV_TYPE] == DRCsvFile::CSV_TYPE_DR && $line[DRCsvFile::CSV_LIGNE_CODE] == DRCsvFile::CSV_LIGNE_CODE_SUPERFICIE) {
            	$produitRecolte->superficie_total += VarManipulator::floatize($line[DRCsvFile::CSV_VALEUR]);
            }
            if ($line[DouaneCsvFile::CSV_TYPE] == DRCsvFile::CSV_TYPE_DR && $line[DRCsvFile::CSV_LIGNE_CODE] == DRCsvFile::CSV_LIGNE_CODE_VOLUME)  {
            	$produitRecolte->volume_sur_place += VarManipulator::floatize($line[DRCsvFile::CSV_VALEUR]);
            }
            if ($line[DouaneCsvFile::CSV_TYPE] == DRCsvFile::CSV_TYPE_DR && $line[DRCsvFile::CSV_LIGNE_CODE] == DRCsvFile::CSV_LIGNE_CODE_RECOLTENETTE) {
            	$produitRecolte->recolte_nette += VarManipulator::floatize($line[DRCsvFile::CSV_VALEUR]);
            }
            if ($line[DouaneCsvFile::CSV_TYPE] == DRCsvFile::CSV_TYPE_DR && $line[DRCsvFile::CSV_LIGNE_CODE] == DRCsvFile::CSV_LIGNE_CODE_VCI) {
                $produitRecolte->vci_constitue += VarManipulator::floatize($line[DRCsvFile::CSV_VALEUR]);
            	$produit->vci->constitue = $produitRecolte->vci_constitue;
            }

            if ($line[DouaneCsvFile::CSV_TYPE] == SV12CsvFile::CSV_TYPE_SV12 && $line[SV12CsvFile::CSV_LIGNE_CODE] == SV12CsvFile::CSV_LIGNE_CODE_SUPERFICIE) {
                $produitRecolte->superficie_total += VarManipulator::floatize($line[SV12CsvFile::CSV_VALEUR]);
            }
            if ($line[DouaneCsvFile::CSV_TYPE] == SV12CsvFile::CSV_TYPE_SV12 && $line[SV12CsvFile::CSV_LIGNE_CODE] == SV12CsvFile::CSV_LIGNE_CODE_VOLUME_VENDANGE_FRAICHE) {
                $produitRecolte->recolte_nette += VarManipulator::floatize($line[SV12CsvFile::CSV_VALEUR]);
                $produitRecolte->volume_total += VarManipulator::floatize($line[SV12CsvFile::CSV_VALEUR]);
                $produitRecolte->volume_sur_place += VarManipulator::floatize($line[SV12CsvFile::CSV_VALEUR]);
            }

            if ($line[DouaneCsvFile::CSV_TYPE] == SV11CsvFile::CSV_TYPE_SV11 && $line[SV11CsvFile::CSV_LIGNE_CODE] == SV11CsvFile::CSV_LIGNE_CODE_SUPERFICIE) {
                $produitRecolte->superficie_total += VarManipulator::floatize($line[SV11CsvFile::CSV_VALEUR]);
            }

            if ($line[DouaneCsvFile::CSV_TYPE] == SV11CsvFile::CSV_TYPE_SV11 && $line[SV11CsvFile::CSV_LIGNE_CODE] == SV11CsvFile::CSV_LIGNE_CODE_VOLUME_APTE) {
                $produitRecolte->recolte_nette += VarManipulator::floatize($line[SV11CsvFile::CSV_VALEUR]);
                $produitRecolte->volume_total += VarManipulator::floatize($line[SV11CsvFile::CSV_VALEUR]);
                $produitRecolte->volume_sur_place += VarManipulator::floatize($line[SV11CsvFile::CSV_VALEUR]);
            }
            if ($line[DouaneCsvFile::CSV_TYPE] == SV11CsvFile::CSV_TYPE_SV11 && $line[SV11CsvFile::CSV_LIGNE_CODE] == SV11CsvFile::CSV_LIGNE_CODE_VOLUME_VCI) {
                $produitRecolte->vci_constitue += VarManipulator::floatize($line[SV11CsvFile::CSV_VALEUR]);
                $produit->vci->constitue = $produitRecolte->vci_constitue;
            }
        }
        //Si on n'a pas de volume sur place
        foreach ($this->declaration->getProduits() as $hash => $p) {
            if(!$p->recolte->volume_sur_place) {
                $p->recolte->vci_constitue = null;
                $p->vci->constitue = null;
            }
            if (!$p->recolte->volume_sur_place && !$p->superficie_revendique && !$p->volume_revendique_total && !$p->hasVci()) {
    		   $todelete[$hash] = $hash;
               continue;
        	}
        }

        foreach ($todelete as $del) {
            $this->remove($del);
        }
        $todelete = array();

        //Supprime les colonnes pour ne proposer qu'un aggréga par produit
        $my_produits = $this->declaration->getProduits();
        foreach ($my_produits as $hash => $p) {
            $hash_produit = $p->getParent()->getHash();
            $produit = $this->addProduit($hash_produit, $p->denomination_complementaire);
            $produitRecolte = $produit->add("recolte");

            if ($p->recolte->volume_sur_place) {
                $produitRecolte->volume_sur_place += $p->recolte->volume_sur_place;
            }
            if ($p->recolte->volume_sur_place_revendique) {
                $produitRecolte->volume_sur_place_revendique += $p->recolte->volume_sur_place_revendique;
            }
            if ($p->recolte->usages_industriels_sur_place) {
                $produitRecolte->usages_industriels_sur_place += $p->recolte->usages_industriels_sur_place;
            }
            if ($p->recolte->usages_industriels_total) {
                $produitRecolte->usages_industriels_total += $p->recolte->usages_industriels_total;
            }
            if ($p->recolte->volume_total) {
                $produitRecolte->volume_total += $p->recolte->volume_total;
            }
            if ($p->recolte->superficie_total) {
                $produitRecolte->superficie_total += $p->recolte->superficie_total;
            }
            if ($p->recolte->recolte_nette) {
                $produitRecolte->recolte_nette += $p->recolte->recolte_nette;
            }
            if ($p->recolte->vci_constitue) {
                $produitRecolte->vci_constitue += $p->recolte->vci_constitue;
            }
            if ($produitRecolte->vci_constitue) {
                $produit->vci->constitue = $produitRecolte->vci_constitue;
            }

            if (! $p->vci->stock_precedent) {
                $todelete[$hash] = $hash;
            }
        }
        foreach ($todelete as $del) {
            $this->remove($del);
        }

        if (!$has_bio && DRevConfiguration::getInstance()->hasDenominationAuto() && $this->hasDenominationAuto(DRevClient::DENOMINATION_BIO_PARTIEL)) {
            foreach ($this->declaration->getProduits() as $hash => $p) {
                $produitBio = $this->addProduit($p->getParent()->getHash(), DRevClient::DENOMINATION_BIO_LIBELLE_AUTO);
            }
        }

        if($preserve) {
            return;
        }

        foreach ($this->declaration->getProduits() as $hash => $p) {
            if ($p->recolte->volume_total && $p->recolte->volume_sur_place && round($p->recolte->volume_total, 4) == round($p->recolte->volume_sur_place, 4) && !in_array($p->getHash(), $bailleurs)) {
                $p->superficie_revendique = $p->recolte->superficie_total;
            }
        }
        $this->updateFromPrecedente();
    }

    public function updateFromPrecedente()
    {
    	if ($precedente = DRevClient::getInstance()->findMasterByIdentifiantAndCampagne($this->identifiant, ($this->campagne - 1))) {
        foreach($precedente->getProduitsVci() as $produit) {
          if ($produit->vci->stock_final) {
            $this->cloneProduit($produit);
          }
        }
    	}
    }

    public function updateFromDRev($drev) {
        foreach ($drev->getProduits() as $produit) {
        	if (!$produit->getConfig()->isActif()) {
        		continue;
        	}
          $p = $this->addProduit($produit->getProduitHash(), $produit->denomination_complementaire);
        }
    }

    public function addLotFromDegustation($lot) {
        $lot_degustation = clone $lot;

        $lot_degustation->remove('details');
        $lot_degustation->remove('numero_table');
        $lot_degustation->remove('leurre');
        $lot_degustation->remove('conformite');
        $lot_degustation->remove('motif');
        $lot_degustation->remove('observation');
        $lot_degustation->remove('declarant_nom');
        $lot_degustation->remove('declarant_identifiant');
        $lot_degustation->remove('origine_mouvement');
        $lot_degustation->remove('numero_anonymat');
        $lot_degustation->remove('centilisation');
        $lot_degustation->remove('email_envoye');

        $lot_degustation->statut = Lot::STATUT_PRELEVABLE;

        $lots = [];
        foreach ($this->lots as $lot) {
            $lots[] = $lot;
        }
        $lots[] = $lot_degustation;

        $this->remove('lots');
        $this->add('lots', $lots);

        return $lot_degustation;
    }

    public function addProduit($hash, $denominationComplementaire = null, $hidden_denom = null) {
        $detailKey = self::DEFAULT_KEY;

        if($denominationComplementaire || $hidden_denom){
            $detailKey = substr(hash("sha1", KeyInflector::slugify(trim($denominationComplementaire).trim($hidden_denom))), 0, 7);
        }

        $hashToAdd = preg_replace("|/declaration/|", '', $hash);
        $exist = $this->exist('declaration/'.$hashToAdd);
        $produit = $this->add('declaration')->add($hashToAdd)->add($detailKey);
        $produit->denomination_complementaire = null;
        if($denominationComplementaire) {
            $produit->denomination_complementaire = $denominationComplementaire;
        }
        $produit->getLibelle();

        if(!$exist) {
            $this->declaration->reorderByConf();
        }

        if(!$exist && $produit->getConfig()->isRevendicationParLots()) {
            $lot = $this->addLot();
            $lot->setProduitHash($produit->getConfig()->getHash());
        }

        return $this->get($produit->getHash());
    }

    public function cloneProduit($produit) {
      $pclone = $this->declaration->add(preg_replace('/\/declaration\//', '', $produit->getParent()->getHash()))
        ->add($produit->getKey());
      $pclone->denomination_complementaire = $produit->denomination_complementaire;
      $pclone->vci->stock_precedent = $produit->vci->stock_final;
      return $pclone;
    }

    public function cleanDoc() {
        $this->declaration->cleanNode();
        $this->cleanLots();
        $this->clearMouvementsLots();
        $this->clearMouvementsFactures();
    }

    public function cleanLots() {
        if(!$this->exist('lots')) {
            return;
        }
        $lotsToKeep = array();

        foreach($this->lots as $keyLot => $lot) {
            if(!$lot->isCleanable()) {
                $lotsToKeep[] = $lot;
            }
        }
         $this->remove('lots');
         $this->add('lots', $lotsToKeep);
    }

    public function addLot() {
        $lot = $this->add('lots')->add();
        $lot->declarant_identifiant = $this->identifiant;
        $lot->declarant_nom = $this->declarant->raison_sociale;
        $lot->affectable = true;
        $lot->initDefault();
        return $lot;
    }

    public function lotsImpactRevendication() {
        foreach($this->getProduitsLots() as $produit) {
            $produit->volume_revendique_issu_recolte = 0;
        }
        foreach($this->lots as $lot) {
            if(!$lot->produit_hash) {
                continue;
            }

            $produit = $lot->getProduitRevendique();

            if(!$produit) {

                continue;
            }

            $produit->volume_revendique_issu_recolte += $lot->volume;
        }
    }

    public function storeDeclarant() {
        $this->declarant_document->storeDeclarant();

        if($this->getEtablissementObject()->famille) {
            $this->declarant->famille = $this->getEtablissementObject()->famille;
        }
    }

    public function storeEtape($etape) {
        $etapeOriginal = ($this->exist('etape')) ? $this->etape : null;

        $this->add('etape', $etape);

        return $etapeOriginal != $this->etape;
    }

    public function storeLotsDateVersion($date) {
        if(!$this->exist('lots')){
            return;
        }
        foreach($this->lots as $lot) {
          if($lot->hasVolumeAndHashProduit() && (!$lot->exist('id_document') || !$lot->id_document)){
            $lot->add('id_document',$this->_id);
            $lot->add('date',$date);
          }
          foreach ($lot as $key => $field) {
            if($lot->hasVolumeAndHashProduit() && $this->getDocument()->isModifiedMother($lot->getHash(), $key)){
              $lot->add('id_document',$this->_id);
              $lot->add('date',$date);
              break;
            }
          }
        }
    }

    public function validate($date = null) {
        if(is_null($date)) {
            $date = date('c');
        }

        $this->storeLotsDateVersion($date);
        //assigné le doc_id dans lots
        $this->cleanDoc();
        $this->validation = $date;
        $this->archiver();
        $this->generateMouvementsFactures();
        $this->updateStatutsLotsSupprimes();

        $this->setStatutOdgByRegion(DRevClient::STATUT_SIGNE);

        if(!count($this->getLotsRevendiques())) {
            foreach($this->getProduitsLots() as $produit) {
                $produit->validateOdg($date);
            }
        }
    }



    public function delete() {
        $this->updateStatutsLotsSupprimes(false);
        return parent::delete();
    }

    public function devalidate($reinit_version_lot = true) {
        $this->validation = null;
        $this->validation_odg = null;
        if($this->exist('etape')) {
            $this->etape = null;
        }
        if($this->exist("envoi_oi")){
         $this->envoi_oi = null;
        }
        if($reinit_version_lot && ConfigurationClient::getCurrent()->declaration->isRevendicationParLots() && $this->exist('lots')){
          foreach($this->lots as $lot) {
              if($lot->exist('date') && $lot->date && ($this->_id == $lot->id_document)){
                $lot->date = null;
                $lot->id_document = null;
              }
          }
        }
        $this->updateStatutsLotsSupprimes(false);
        $this->setStatutOdgByRegion(DRevClient::STATUT_BROUILLON);
    }

    public function updateStatutsLotsSupprimes($validation = true) {
      if (!$this->hasVersion()) {
        return;
      }
      $mother = $this->getMother();
      $updated = false;
      if ($mother)
      foreach ($mother->getLots() as $lot) {
        if ($validation && in_array($lot->statut, array(Lot::STATUT_PRELEVABLE, Lot::STATUT_ELEVAGE)) && !$this->mouvements_lots->get($this->identifiant)->exist($lot->getUnicityKey())) {
          $lot->statut = Lot::STATUT_NONPRELEVABLE;
          $updated = true;
        }
        if (!$validation && $lot->statut == Lot::STATUT_NONPRELEVABLE && !$this->mouvements_lots->get($this->identifiant)->exist($lot->getUnicityKey())) {
          $lot->statut = Lot::STATUT_PRELEVABLE;
          $updated = true;
        }
      }
      if ($updated) {
        $mother->generateMouvementsLots();
        $mother->save();
      }
    }

    public function validateOdg($date = null, $region = null) {
        if(is_null($date)) {
            $date = date('c');
        }

        if(!$region && DrevConfiguration::getInstance()->hasOdgProduits() && DrevConfiguration::getInstance()->hasValidationOdgRegion()) {
            throw new sfException("La validation nécessite une région");
        }

        $this->setStatutOdgByRegion(DRevClient::STATUT_VALIDATION_ODG, $region);

        if(DrevConfiguration::getInstance()->hasOdgProduits() && $region){
            return $this->validateOdgByRegion($date, $region);
        }

        $this->validation_odg = $date;
    }

    public function setStatutOdgByRegion($statut, $region = null) {
        if(DrevConfiguration::getInstance()->hasValidationOdgRegion()) {
            if($region) {
                foreach ($this->getProduits($region) as $hash => $produit) {
                    $produit->setStatutOdg($statut);
                }
            } else {
                foreach (DrevConfiguration::getInstance()->getOdgRegions() as $region) {
                    $this->setStatutOdgByRegion($statut, $region);
                }
            }
        }else{
            foreach ($this->getProduits($region) as $hash => $produit) {
                $produit->setStatutOdg($statut);
            }
        }
        $allStatut = true;
        foreach ($this->declaration->getProduits() as $key => $produit) {
            if($produit->getStatutOdg() == $statut){
               continue;
            }
            $allStatut = false;
            break;
        }
        if(!$allStatut) {
            return;
        }
        if (!$this->exist('statut_odg')) {
            return $this->add('statut_odg', $statut);
        }
        return $this->_set('statut_odg', $statut);
    }

    public function isMiseEnAttenteOdg() {
        return ($this->getStatutOdg() == DRevClient::STATUT_EN_ATTENTE);
    }

    public function getStatutOdg() {
        if (!$this->exist('statut_odg')) {
            return null;
        }
        return $this->_get('statut_odg');
    }

    protected function validateOdgByRegion($date = null, $region = null) {
        if($region) {
            foreach ($this->getProduits($region) as $hash => $produit) {
                $produit->validateOdg($date);
            }
        } else {
            foreach (DrevConfiguration::getInstance()->getOdgRegions() as $region) {
                $this->validateOdg($date, $region);
            }
        }

        $allValidate = true;
        foreach ($this->declaration->getProduits() as $key => $produit) {
            if($produit->isValidateOdg()){
               continue;
            }
            $allValidate = false;
            break;
        }

        if($this->isModificative()){
            $this->getMother()->validateOdgByRegion($date, $region);
            $this->getMother()->save();
        }

        if(!$allValidate) {

            return;
        }

        $this->validation_odg = $date;
    }

    public function isValidateOdgByRegion($region){
      if (!$region) {
          return false;
      }
      foreach ($this->getProduits($region) as $hash => $produit) {
        if(!$produit->isValidateOdg()){
          return false;
        }
      }
      return true;
    }

    public function getValidationOdgDateByRegion($region){
      if(!$region){
        return null;
      }
      foreach ($this->getProduits($region) as $hash => $produit) {
        if($produit->isValidateOdg()){
          return $produit->validation_odg;
        }
      }
      return null;
    }

    public function getEtablissementObject() {

        return EtablissementClient::getInstance()->findByIdentifiant($this->identifiant);
    }

    public function initProduits() {
        $produits = $this->getConfigProduits();
        foreach ($produits as $produit) {
            $this->addProduit($produit->getHash());
        }
    }

    protected function updateProduitDetailFromCSV($csv) {
        $this->resetProduitDetail();
        foreach ($csv as $line) {

            if (!preg_match("/^TOTAL/", $line[DRCsvFile::CSV_LIEU]) && !preg_match("/^TOTAL/", $line[DRCsvFile::CSV_CEPAGE])) {

                continue;
            }

            $line[DRCsvFile::CSV_HASH_PRODUIT] = preg_replace("/(mentionVT|mentionSGN)/", "mention", $line[DRCsvFile::CSV_HASH_PRODUIT]);

            if (!$this->getConfiguration()->exist(preg_replace('|/recolte.|', '/declaration/', $line[DRCsvFile::CSV_HASH_PRODUIT]))) {
                continue;
            }

            $config = $this->getConfiguration()->get($line[DRCsvFile::CSV_HASH_PRODUIT])->getNodeRelation('revendication');

            if ($config instanceof ConfigurationAppellation && !$config->mention->lieu->hasManyCouleur()) {
                $config = $config->mention->lieu->couleur;
            }

            if ($config instanceof ConfigurationMention && !$config->lieu->hasManyCouleur()) {
                $config = $config->lieu->couleur;
            }

            if (!$config instanceof ConfigurationCouleur) {
                continue;
            }

            $produit = $this->addProduit($config->getHash());

            $produitDetail = $produit->detail;
            if($line[DRCsvFile::CSV_VTSGN]) {
                $produitDetail = $produit->detail_vtsgn;
            }

            $produitDetail->volume_total += (float) $line[DRCsvFile::CSV_VOLUME_TOTAL];
            $produitDetail->usages_industriels_total += (float) $line[DRCsvFile::CSV_USAGES_INDUSTRIELS_TOTAL];
            $produitDetail->superficie_total += (float) $line[DRCsvFile::CSV_SUPERFICIE_TOTALE];
            $produitDetail->volume_sur_place += (float) $line[DRCsvFile::CSV_VOLUME];
            if (!$produitDetail->exist('superficie_vinifiee')) {
            	$produitDetail->add('superficie_vinifiee');
            }
            if($line[DRCsvFile::CSV_SUPERFICIE] != "") {
                $produitDetail->superficie_vinifiee += (float) $line[DRCsvFile::CSV_SUPERFICIE];
            } else {
                $produitDetail->superficie_vinifiee = null;
            }
            if ($line[DRCsvFile::CSV_USAGES_INDUSTRIELS] == "") {
                $produitDetail->usages_industriels_sur_place = -1;
            } elseif ($produitDetail->usages_industriels_sur_place != -1) {
                $produitDetail->usages_industriels_sur_place += (float) $line[DRCsvFile::CSV_USAGES_INDUSTRIELS];
            }
        }

        $this->updateProduitDetail();
    }

    protected function resetProduitDetail() {
        foreach ($this->declaration->getProduits() as $produit) {
            $produit->resetDetail();
        }
    }

    protected function updateProduitDetail() {
        foreach ($this->declaration->getProduits() as $produit) {
            $produit->updateDetail();
        }
    }

    protected function updateProduitRevendiqueFromDetail() {
        foreach ($this->declaration->getProduits() as $produit) {
            $produit->updateRevendiqueFromDetail();
        }
    }

    public function updatePrelevementsFromRevendication() {
        return;
        $prelevements_to_delete = array_flip($this->prelevement_keys);
        foreach ($this->declaration->getProduits() as $produit) {
            if (!$produit->getTotalVolumeRevendique()) {

                continue;
            }
            $hash = $this->getConfiguration()->get($produit->getHash())->getHashRelation('lots');
            $key = $this->getPrelevementsKeyByHash($hash);
            $this->addPrelevement(self::CUVE . $key);
            if(!$this->isNonConditionneur()) {
                $this->addPrelevement(self::BOUTEILLE . $key);
            }
            unset($prelevements_to_delete[self::CUVE . $key]);
            unset($prelevements_to_delete[self::BOUTEILLE . $key]);
        }

        if ($this->declaration->hasVtsgn()) {
            $this->addPrelevement(self::CUVE_VTSGN);
            if(!$this->isNonConditionneur()) {
                $this->addPrelevement(self::BOUTEILLE_VTSGN);
            }
            unset($prelevements_to_delete[self::CUVE_VTSGN]);
            unset($prelevements_to_delete[self::BOUTEILLE_VTSGN]);
        }

        foreach ($prelevements_to_delete as $key => $value) {
            if (!$this->prelevements->exist($key)) {

                continue;
            }

            $this->prelevements->remove($key);
        }
    }

    public function hasCompleteDocuments()
    {
    	$complete = true;
    	foreach($this->getOrAdd('documents') as $document) {
    		if ($document->statut != DRevDocuments::STATUT_RECU) {
    			$complete = false;
    			break;
    		}
    	}
    	return $complete;
    }

    public function isSauvegarde()
    {
    	$tabId = explode('-', $this->_id);
    	return (strlen($tabId[(count($tabId) - 1)]) > 4)? true : false;
    }

    public function canHaveSuperficieVinifiee()
    {
    	foreach ($this->declaration->getProduits() as $produit) {
    		if ($produit->exist('superficie_vinifiee') || $produit->exist('superficie_vinifiee_vtsgn')) {
    			return true;
    		}
    	}
    	return false;
    }

    public function isAdresseLogementDifferente() {
        if(!$this->chais->adresse && !$this->chais->commune && !$this->chais->code_postal) {

            return false;
        }

        return ($this->chais->adresse != $this->declarant->adresse || $this->chais->commune != $this->declarant->commune || $this->chais->code_postal != $this->declarant->code_postal);
    }

	protected function doSave() {
        $this->piece_document->generatePieces();
        foreach ($this->declaration->getProduits() as $key => $produit) {
            $produit->update();
        }
        $this->generateMouvementsLots();
	}

  public function archiver() {
      $this->archivage_document->preSave();
      if ($this->isArchivageCanBeSet()) {
          $this->archiverLot($this->numero_archive);
      }
  }

  /*** ARCHIVAGE ***/

  public function getNumeroArchive() {

      return $this->_get('numero_archive');
  }

  public function isArchivageCanBeSet() {

      return $this->isValidee();
  }

  public function archiverLot($numeroDossier) {
      $lastNum = ArchivageAllView::getInstance()->getLastNumeroArchiveByTypeAndCampagne(Lot::TYPE_ARCHIVE, $this->archivage_document->getCampagne());
      $num = 0;
      if (preg_match("/^([0-9]+).*/", $lastNum, $m)) {
        $num = $m[1];
      }
      foreach($this->lots as $lot) {
        if (!$lot->numero_archive && !$lot->numero_dossier) {
          $num++;
          $lot->numero_archive = sprintf("%05d", $num);
          $lot->numero_dossier = $numeroDossier;
        }
      }
  }

  /*** FIN ARCHIVAGE ***/

	public function hasVciDetruit()
	{
		return $this->declaration->hasVciDetruit();
	}

	public function getDateValidation($format = 'Y-m-d')
	{
		if ($this->validation) {
			$date = new DateTime($this->validation);
		} else {
			$date = new DateTime($this->getDate());
		}
		return $date->format($format);
	}

    /*
     * Facture
     */
	public function getSurfaceFacturable()
	{
		return $this->declaration->getTotalTotalSuperficie();
	}

	public function getVolumeFacturable($produitFilter = null)
	{
		return $this->declaration->getTotalVolumeRevendique($produitFilter);
	}

	public function getSurfaceVinifieeFacturable()
	{
		return $this->declaration->getTotalSuperficieVinifiee();
	}

    public function getTotalVolumeRevendique()
    {

        return $this->declaration->getTotalVolumeRevendique();
    }

    public function getTotalVolumeRevendiqueVCI()
    {

        return $this->declaration->getTotalVolumeRevendiqueVCI();
    }

    /**** MOUVEMENTS ****/

    public function getTemplateFacture() {
        if($templateName = FactureConfiguration::getInstance()->getUniqueTemplateFactureName($this->getCampagne())){
          return TemplateFactureClient::getInstance()->find($templateName);
        }
        return TemplateFactureClient::getInstance()->find("TEMPLATE-FACTURE-AOC-".$this->getCampagne());
    }

    public function getMouvementsFactures() {

        return $this->_get('mouvements');
    }

    public function getMouvementsFacturesCalcule() {
      $templateFacture = $this->getTemplateFacture();
      if(!$templateFacture) {
          return array();
      }

      $cotisations = $templateFacture->generateCotisations($this);

      if($this->hasVersion()) {
          $cotisationsPrec = $templateFacture->generateCotisations($this->getMother());
      }

      $identifiantCompte = $this->getIdentifiant();

      $mouvements = array();

      $rienAFacturer = true;

      foreach($cotisations as $cotisation) {
          $mouvement = DRevMouvementFactures::freeInstance($this);
          $mouvement->fillFromCotisation($cotisation);
          $mouvement->facture = 0;
          $mouvement->facturable = 1;
          $mouvement->date = $this->getCampagne().'-12-10';
          $mouvement->date_version = $this->validation;
          $mouvement->version = $this->version;

          if(isset($cotisationsPrec[$cotisation->getHash()])) {
              $mouvement->quantite = $mouvement->quantite - $cotisationsPrec[$cotisation->getHash()]->getQuantite();
          }

          if($this->hasVersion() && !$mouvement->quantite) {
              continue;
          }

          if($mouvement->quantite) {
              $rienAFacturer = false;
          }

          $mouvements[$mouvement->getMD5Key()] = $mouvement;
      }

      if($rienAFacturer) {
          return array($identifiantCompte => array());

      }

      return array($identifiantCompte => $mouvements);
    }

    public function getMouvementsFacturesCalculeByIdentifiant($identifiant) {

        return $this->mouvement_document->getMouvementsFacturesCalculeByIdentifiant($identifiant);
    }

    public function generateMouvementsFactures() {

        return $this->mouvement_document->generateMouvementsFactures();
    }

    public function findMouvementFactures($cle, $id = null){
      return $this->mouvement_document->findMouvementFactures($cle, $id);
    }

    public function facturerMouvements() {

        return $this->mouvement_document->facturerMouvements();
    }

    public function isFactures() {

        return $this->mouvement_document->isFactures();
    }

    public function isNonFactures() {

        return $this->mouvement_document->isNonFactures();
    }

    public function clearMouvementsFactures(){
        $this->remove('mouvements');
        $this->add('mouvements');
    }

    /**** FIN DES MOUVEMENTS ****/

    /**** MOUVEMENTS LOTS ****/

    public function clearMouvementsLots(){
        $this->remove('mouvements_lots');
        $this->add('mouvements_lots');
    }

    public function addMouvementLot($mouvement) {

        return $this->mouvements_lots->add($mouvement->declarant_identifiant)->add($mouvement->getUnicityKey(), $mouvement);
    }

    public function generateMouvementsLots()
    {
        $this->clearMouvementsLots();

        if (!$this->isValideeOdg()) {
          return;
        }

        foreach ($this->lots as $lot) {
            $this->addMouvementLot($lot->getMouvement(Lot::STATUT_REVENDIQUE));

            if ($lot->isAffectable()) {
                $this->addMouvementLot($lot->getMouvement(Lot::STATUT_AFFECTABLE));
                continue;
            }

            if($lot->isAffecte()) {
                $this->addMouvementLot($lot->getMouvement(Lot::STATUT_AFFECTE_SRC_DREV));
                continue;
            }

            $this->addMouvementLot($lot->getMouvement(Lot::STATUT_NONAFFECTABLE));
        }
    }

    /**** FIN DES MOUVEMENTS LOTS ****/

    /**** PIECES ****/

    public function getAllPieces() {
    	$complement = ($this->isPapier())? '(Papier)' : '(Télédéclaration)';
    	$complement .= ($this->isSauvegarde())? ' Non facturé' : '';
      $date = null;
      if ($this->getValidation()) {
        $dt = new DateTime($this->getValidation());
        $date = $dt->format('Y-m-d');
      }
    	return (!$this->getValidation())? array() : array(array(
    		'identifiant' => $this->getIdentifiant(),
    		'date_depot' => $date,
    		'libelle' => 'Revendication des produits '.$this->campagne.' '.$complement,
    		'mime' => Piece::MIME_PDF,
    		'visibilite' => 1,
    		'source' => null
    	));
    }

    public function generatePieces() {
    	return $this->piece_document->generatePieces();
    }

    public function generateUrlPiece($source = null) {
    	return sfContext::getInstance()->getRouting()->generate('drev_export_pdf', $this);
    }

    public static function getUrlVisualisationPiece($id, $admin = false) {
    	return sfContext::getInstance()->getRouting()->generate('drev_visualisation', array('id' => $id));
    }

    public static function getUrlGenerationCsvPiece($id, $admin = false) {
    	return null;
    }

    public static function isVisualisationMasterUrl($admin = false) {
    	return true;
    }

    public static function isPieceEditable($admin = false) {
    	return false;
    }

    public function getCategorie(){
      return strtolower($this->type);
    }

    /**** FIN DES PIECES ****/

    /**** VERSION ****/

    public static function buildVersion($rectificative, $modificative) {

        return VersionDocument::buildVersion($rectificative, $modificative);
    }

    public static function buildRectificative($version) {

        return VersionDocument::buildRectificative($version);
    }

    public static function buildModificative($version) {

        return VersionDocument::buildModificative($version);
    }

    public function getVersion() {

        return $this->_get('version');
    }

    public function hasVersion() {

        return $this->version_document->hasVersion();
    }

    public function isVersionnable() {
        if (!$this->validation) {

            return false;
        }

        return $this->version_document->isVersionnable();
    }

    public function getRectificative() {

        return $this->version_document->getRectificative();
    }

    public function isRectificative() {

        return $this->version_document->isRectificative();
    }

    public function isRectifiable() {

        return false;
    }

    public function getModificative() {

        return $this->version_document->getModificative();
    }

    public function isModificative() {

        return $this->version_document->isModificative();
    }

    public function isModifiable() {
        return $this->version_document->isModifiable();
    }

    public function isTeledeclare() {
        return !$this->isPapier();
    }

    public function isTeledeclareFacturee() {
        return $this->isTeledeclare() && !$this->isNonFactures();
    }

    public function isTeledeclareNonFacturee() {
        return $this->isTeledeclare() && $this->isNonFactures();
    }

    public function getPreviousVersion() {

        return $this->version_document->getPreviousVersion();
    }

    public function getMasterVersionOfRectificative() {

        throw new sfException("Not implemented");
    }

    public function needNextVersion() {

        return $this->version_document->needNextVersion() || !$this->isSuivanteCoherente();
    }

    public function getMaster() {

        return $this->version_document->getMaster();
    }

    public function isMaster() {

        return $this->version_document->isMaster();
    }

    public function findMaster() {

        return DRevClient::getInstance()->findMasterByIdentifiantAndCampagne($this->identifiant, $this->campagne);
    }

    public function findDocumentByVersion($version) {
        $id = 'DREV-' . $this->identifiant . '-' . $this->campagne;
        if($version) {
            $id .= "-".$version;
        }

        return DRevClient::getInstance()->find($id);
    }

    public function getMother() {

        return $this->version_document->getMother();
    }

    public function motherGet($hash) {

        return $this->version_document->motherGet($hash);
    }

    public function motherExist($hash) {

        return $this->version_document->motherExist($hash);
    }

    public function motherHasChanged() {
        if ($this->declaration->total != $this->getMother()->declaration->total) {

            return true;
        }

        if (count($this->getProduitsDetails($this->teledeclare)) != count($this->getMother()->getProduitsDetails($this->teledeclare))) {

            return true;
        }

        if ($this->droits->douane->getCumul() != $this->getMother()->droits->douane->getCumul()) {

            return true;
        }

        return false;
    }

    public function getDiffWithMother() {

        return $this->version_document->getDiffWithMother();
    }

    public function isModifiedMother($hash_or_object, $key = null) {

        return $this->version_document->isModifiedMother($hash_or_object, $key);
    }

    public function generateRectificative() {

        return $this->version_document->generateRectificative();
    }

    public function generateModificative() {
        $doc = $this->version_document->generateModificative();
        $doc->clearMouvementsLots();
        $doc->clearMouvementsFactures();
        return $doc;
    }

    public function generateNextVersion() {

        throw new sfException("Not implemented");
    }

    public function listenerGenerateVersion($document) {
        $document->devalidate(false);
        foreach ($document->getProduitsLots() as $produit) {
          if($produit->exist("validation_odg") && $produit->validation_odg){
            $produit->validation_odg = null;
          }
        }
        foreach ($document->lots as $lot) {
          $lot->statut = Lot::STATUT_NONPRELEVABLE;
        }
    }

    public function listenerGenerateNextVersion($document) {

    }

    public function getSuivante() {

        throw new sfException("Not implemented");
    }

    public function isValidee() {

        return $this->validation;
    }

    public function isValideeOdg() {

        return boolval($this->getValidationOdg());
    }

    public function isLotsEditable(){
      return $this->isValideeOdg() && $this->isValidee();
    }

    /**** FIN DE VERSION ****/

    public function getDate() {
      return $this->campagne.'-12-10';
    }

    public function hasDenominationAuto($const) {
      return $this->exist("denomination_auto") && ($this->denomination_auto == $const);
    }

    public function getDocumentsAEnvoyer() {
        $documents = array();

        foreach($this->getOrAdd('documents') as $document) {
            if($document->statut != DRevDocuments::STATUT_EN_ATTENTE) {
                continue;
            }

            $documents[$document->getKey()] = $document;
        }

        return $documents;
    }

    public function getNonHabilitationINAO() {
        try {
            return DRevClient::getInstance()->getNonHabilitationINAO($this);
        }catch(Exception $e) {
            return array();
        }
    }

    public function hasProduitWithMutageAlcoolique() {
        foreach($this->getProduits() as $produit) {

            if($produit->getConfig()->hasMutageAlcoolique()) {
                return true;
            }
        }

        return false;
    }

    public function setDateDegustationSouhaitee($date) {
        $this->_add('date_degustation_voulue', $date);
    }

    public function getProduitsWithReserveInterpro($region = null) {
        $produits = array();
        foreach($this->getProduits($region) as $p) {
            if ($p->hasReserveInterpro()) {
                $produits[] = $p;
            }
        }
        return $produits;
    }

    public function hasProduitsReserveInterpro($region = null) {
        return (count($this->getProduitsWithReserveInterpro($region)));
    }

}
