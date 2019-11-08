<?php

class ParcellaireClient extends acCouchdbClient {

    const TYPE_MODEL = "Parcellaire";
    const TYPE_COUCHDB = "PARCELLAIRE";

    const MODE_SAVOIRFAIRE_FERMIER = 'FERMIER';
    const MODE_SAVOIRFAIRE_PROPRIETAIRE = 'PROPRIETAIRE';
    const MODE_SAVOIRFAIRE_METAYER = 'METAYER';

    public static $modes_savoirfaire = array(
        self::MODE_SAVOIRFAIRE_FERMIER => "Fermier",
        self::MODE_SAVOIRFAIRE_PROPRIETAIRE => "Propriétaire",
        self::MODE_SAVOIRFAIRE_METAYER => "Métayer",
    );

    public static function getInstance() {
        return acCouchdbManager::getClient("Parcellaire");
    }

    /**
     * Créé un nouveau document de type Parcellaire
     *
     * @param string $identifiant L'identifiant etablissement du parcellaire
     * @param string La date de campagne du parcellaire
     * @param string Le type de document
     *
     * @return Le document créé
     */
    public function createDoc($identifiant, $campagne, $type = self::TYPE_COUCHDB)
    {
        $parcellaire = new Parcellaire();
        $parcellaire->initDoc($identifiant, $campagne, $type);

        return $parcellaire;
    }

    /**
     * Recherche une entrée dans les documents existants
     *
     * @param string $identifiant L'identifiant etablissement du parcellaire
     * @param string $date La date de création du parcellaire
     *
     * @return Un document existant
     */
    public function findByArgs($identifiant, $date)
    {
        $id = self::TYPE_COUCHDB . '-' . $identifiant . '-' . $date;
        return $this->find($id);
    }

    /**
     * Scrape le site des douanes via le scrapy
     *
     * @param string $cvi Le numéro du CVI à scraper
     *
     * @throws Exception Si aucun CVI trouvé
     * @return string Le fichier le plus récent
     */
    public function scrapeParcellaireCSV($cvi)
    {
        $scrapydocs = sfConfig::get('app_scrapy_documents');
        $scrapybin = sfConfig::get('app_scrapy_bin');
        
        //$dir = sfConfig::get('sf_apps_dir');
        //$scrapybin = $dir.'/../../../prodouane_scrapy/bin';
        //$scrapydocs = $dir.'/../../../prodouane_scrapy/documents';
        

        exec($scrapybin."/download_parcellaire.sh $cvi", $output, $status);
       
        $files = glob($scrapydocs.'/parcellaire-'.$cvi.'.csv');
        
        if (empty($files) && status != 0) {
            throw new Exception("Le scraping n'a retourné aucun résultat.");
        }

        return array_pop($files);
    }
    /**
     * Scrape le site des douanes via le scrapy
     *
     * @param string $cvi Le numéro du CVI à scraper
     *
     * @throws Exception Si aucun CVI trouvé
     * @return string Le fichier le plus récent
     */
    public function scrapeParcellaireJSON($cvi)
    {
        
        $scrapydocs = sfConfig::get('app_scrapy_documents');
        $scrapybin = sfConfig::get('app_scrapy_bin');
        //$dir = sfConfig::get('sf_apps_dir');
        //$scrapybin = $dir.'/../../../prodouane_scrapy/bin';
        //$scrapydocs = $dir.'/../../../prodouane_scrapy/documents';

        
        exec("$scrapybin/download_parcellaire_geojson.sh $cvi", $output, $status);

        $files = glob($scrapydocs.'/cadastre-'.$cvi.'-parcelles.json');

        if (empty($files) && status != 0) {
            throw new Exception("La récupération des géojson n'a pas fonctionné.");
        }

        return array_pop($files);
    }

    /**
     * Prend un chemin de fichier en paramètre et le transforme en Parcellaire
     * Vérifie que le nouveau parcellaire est différent du courant avant de le
     * sauver
     *
     * @param Etablissement $etablissement L'établissement à mettre à jour
     * @param Array &$error Le potentiel message d'erreur de retour
     *
     * @return bool
     */
    public function saveParcellaire(Etablissement $etablissement, Array &$errors)
    {
        $fileCsv = $this->scrapeParcellaireCSV($etablissement->cvi);
        $fileJson = $this->scrapeParcellaireJSON($etablissement->cvi);
        return $this->saveParcellaireCSV($etablissement, $fileCsv, $errors['csv']) &&
        $this->saveParcellaireGeoJson($etablissement, $fileJson, $errors['json']);
            
    }

    public function saveParcellaireGeoJson($etablissement, $path, &$error){
        try {
            
            
            $parcellaire = new ParcellaireJsonFile($etablissement, $path, new ParcellaireCsvFormat);
            $parcellaire->save();

        } catch (Exception $e) {
            $error = "Une erreur lors du sauvégardage !";
            //print_r($error);
            return false;
        }

        return true;
        
    }

    public function saveParcellaireCSV(Etablissement $etablissement, $path, &$error){
        try {
            $csv = new Csv($path);
            $parcellaire = new ParcellaireCsvFile($etablissement, $csv, new ParcellaireCsvFormat);
            $parcellaire->convert();
        } catch (Exception $e) {
            $error = $e->getMessage();
            return false;
        }

        $parcellaire->save();
        return true;
    }

    public function find($id, $hydrate = self::HYDRATE_DOCUMENT, $force_return_ls = false) {
        $doc = parent::find($id, $hydrate, $force_return_ls);

        if ($doc && $doc->type != self::TYPE_MODEL) {

            throw new sfException(sprintf("Document \"%s\" is not type of \"%s\"", $id, self::TYPE_MODEL));
        }

        return $doc;
    }

    public function findOrCreate($identifiant, $date = null, $source = null, $type = self::TYPE_COUCHDB) {
        if (! $date) {
            $date = date('Ymd');
        }
        $parcellaire = $this->getLast($identifiant);
        if ($parcellaire && $parcellaire->date == $date) {
            return $parcellaire;
        }
        $parcellaire = new Parcellaire();
        $parcellaire->initDoc($identifiant, $date);
        $parcellaire->source = $source;
       
        return $parcellaire;
    }

    public function findOrCreateDocJson($identifiant, $date = null, $source = null, $path=null, $cvi, $type = self::TYPE_COUCHDB) {
        if (! $date) {
            $date = date('Ymd');
        }
        $parcellaire = $this->getLast($identifiant);
        
        if ($parcellaire && $parcellaire->date == $date) {
            if($path){

                $parcellaire->storeAttachment($path, 'text/json', "import-cadastre-$cvi-parcelles.json");
                $parcellaire->save();
            }
            //print_r($parcellaire);
            //exit;
            
            return $parcellaire;
        }
    
    }

    public function findPreviousByIdentifiantAndDate($identifiant, $date, $hydrate = acCouchdbClient::HYDRATE_DOCUMENT) {
        $h = $this->getHistory($identifiant, $date, $hydrate);
        if (!count($h)) {
        return NULL;
        }
        $h = $h->getDocs();
        end($h);
        $doc = $h[key($h)];
        return $doc;
    }

    public function getLast($identifiant, $hydrate = acCouchdbClient::HYDRATE_DOCUMENT){
        $history = $this->getHistory($identifiant, $hydrate);

        return $this->findPreviousByIdentifiantAndDate($identifiant, '9999-99-99');
    }

    public function getHistory($identifiant, $date = '9999-99-99', $hydrate = acCouchdbClient::HYDRATE_DOCUMENT, $dateDebut = "0000-00-00") {

        return $this->startkey(sprintf(self::TYPE_COUCHDB."-%s-%s", $identifiant, str_replace('-', '', $dateDebut)))
                    ->endkey(sprintf(self::TYPE_COUCHDB."-%s-%s", $identifiant, str_replace('-', '', $date)))->execute($hydrate);
    }

    public function findAll($limit = null, $hydrate = acCouchdbClient::HYDRATE_DOCUMENT)
    {
    	$view = $this->startkey(sprintf(self::TYPE_COUCHDB."-%s-%s", "AAA0000000", "00000000"))
    	->endkey(sprintf(self::TYPE_COUCHDB."-%s-%s", "ZZZ9999999", "99999999"));
    	if ($limit) {
    		$view->limit($limit);
    	}
    	return $view->execute($hydrate)->getDatas();
    }

    public static function sortParcellesForCommune($a, $b) {
        $aK = $a->section.sprintf("%04d",$a->numero_parcelle);
        $bK = $b->section.sprintf("%04d",$b->numero_parcelle);
        return strcmp($aK,$bK);

    }

}
