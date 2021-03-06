<?php

class ParcellaireClient extends acCouchdbClient {

    const TYPE_MODEL = "Parcellaire";
    const TYPE_COUCHDB = "PARCELLAIRE";

    const MODE_SAVOIRFAIRE_FERMIER = 'FERMIER';
    const MODE_SAVOIRFAIRE_PROPRIETAIRE = 'PROPRIETAIRE';
    const MODE_SAVOIRFAIRE_METAYER = 'METAYER';

    const PARCELLAIRE_DEFAUT_PRODUIT_HASH = '/declaration/certifications/DEFAUT/genres/DEFAUT/appellations/DEFAUT/mentions/DEFAUT/lieux/DEFAUT/couleurs/DEFAUT/cepages/DEFAUT';
    const PARCELLAIRE_DEFAUT_PRODUIT_LIBELLE = 'Pas de produit';

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

    public function getDelimitations($communes){
        $scrapydocs = sfConfig::get('app_scrapy_documents');
        $geojson = [];
        $files = '';
        foreach ($communes as $id => $commune) {
            $file_name = $scrapydocs.'/delimitation-'.$commune.'.json';
            $files = glob($file_name);
            if (!empty($files)) {
                $contents = file_get_contents($file_name);
                array_push($geojson, $contents);
            }
        }
        return $geojson;
    }

    /**
     * Scrape le site des douanes via le scrapy
     *
     * @param string $cvi Le numéro du CVI à scraper
     *
     * @throws Exception Si aucun CVI trouvé
     * @return string Le fichier le plus récent
     */
    public function scrapeParcellaireCSV($cvi, $contextInstance = null)
    {
        $contextInstance = ($contextInstance)? $contextInstance : sfContext::getInstance();
        $scrapydocs = sfConfig::get('app_scrapy_documents');
        $scrapybin = sfConfig::get('app_scrapy_bin');

        $contextInstance->getLogger()->info("scrapeParcellaireCSV() ".$scrapybin."/download_parcellaire.sh $cvi");
        exec($scrapybin."/download_parcellaire.sh $cvi", $output, $status);
        $contextInstance->getLogger()->info("scrapeParcellaireCSV() ".implode(' - ', $output));

        $file = $scrapydocs.'/parcellaire-'.$cvi.'.csv';

        if (empty($file)) {
            $contextInstance->getLogger()->info("scrapeParcellaireCSV() : pas de fichiers trouvés");
        }
        if ($status != 0) {
            $contextInstance->getLogger()->info("scrapeParcellaireCSV() : retour du scrap problématique");
        }

        if (empty($file) || $status != 0) {
            $contextInstance->getLogger()->info("scrapeParcellaireCSV() ".implode(' - ', $output));
            throw new Exception("Le scraping n'a retourné aucun résultat.");
        }

        return $file;
    }
    /**
     * Scrape le site des douanes via le scrapy
     *
     * @param string $cvi Le numéro du CVI à scraper
     *
     * @throws Exception Si aucun CVI trouvé
     * @return string Le fichier le plus récent
     */
    public function scrapeParcellaireJSON($cvi, $contextInstance = null)
    {
        $contextInstance = ($contextInstance)? $contextInstance : sfContext::getInstance();
        $scrapydocs = sfConfig::get('app_scrapy_documents');
        $scrapybin = sfConfig::get('app_scrapy_bin');

        $contextInstance->getLogger()->info("scrapeParcellaireJSON:  $scrapybin/download_parcellaire_geojson.sh $cvi");
        exec("$scrapybin/download_parcellaire_geojson.sh $cvi", $output, $status);
        $contextInstance->getLogger()->info("scrapeParcellaireJSON: output: ".implode(' - ', $output));
        $file = $scrapydocs.'/cadastre-'.$cvi.'-parcelles.json';
        $message = "";

        if (empty($file)) {
            $message = "Les parcelles n'existent pas dans les fichier du Cadastre. ";

            if($status != 0){
                $message .= "La récupération des geojson n'a pas fonctionné.";
            }
        }

        if(!empty($message)){
            $contextInstance->getLogger()->info("scrapeParcellaireJSON: error: ".$message);
            throw new Exception($message);
        }

        return $file;
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
    public function saveParcellaire(Etablissement $etablissement, Array &$errors, $contextInstance = null)
    {
        $fileCsv = $this->scrapeParcellaireCSV($etablissement->cvi, $contextInstance);
        $return = $this->saveParcellaireCSV($etablissement, $fileCsv, $errors['csv'], $contextInstance);
        $fileJson = $this->scrapeParcellaireJSON($etablissement->cvi, $contextInstance);
        $return = $this->saveParcellairePDF($etablissement, $fileJson, $errors['pdf']);
        return $return && $this->saveParcellaireGeoJson($etablissement, $fileJson, $errors['json']);
    }

    public function getParcellaireGeoJson($identifiant, $cvi){
        $file_name = "import-cadastre-".$cvi."-parcelles.json";

        $parcellaire = $this->getLast($identifiant);
        
        $uri = $parcellaire->getAttachmentUri($file_name);
        
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $uri);

    
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $import = curl_exec($ch);
        curl_close($ch);
        if(strpos($import, "Document is missing attachment")) {
            sfContext::getInstance()->getLogger()->info("getParcellaireGeoJson() : Document is missing attachment");
            return false;
        }
        return $import;


    }

    public function saveParcellaireGeoJson($etablissement, $path, &$error, $contextInstance = null){
        $contextInstance = ($contextInstance)? $contextInstance : sfContext::getInstance();
        try {

            $parcellaire = new ParcellaireJsonFile($etablissement, $path, $contextInstance);

            $parcellaire->save();

        } catch (Exception $e) {
            $error = "Une erreur lors de la sauvgarde ".$e->getMessage();
            $contextInstance->getLogger()->info("saveParcellaireGeoJson() : exception ".$e->getMessage());
            return false;
        }

        return true;

    }

    public function saveParcellaireCSV(Etablissement $etablissement, $path, &$error, $contextInstance = null){
        $contextInstance = ($contextInstance)? $contextInstance : sfContext::getInstance();
        try {
            $csv = new Csv($path);
            $parcellaire = new ParcellaireCsvFile($etablissement, $csv, $contextInstance);
            $parcellaire->convert();

        } catch (Exception $e) {
            $contextInstance->getLogger()->info("saveParcellaireCSV() : exception ".$e->getMessage());
            $error = $e->getMessage();
            return false;
        }

        $parcellaire->save();

        return true;
    }

    public function saveParcellairePDF(Etablissement $etablissement, $path, &$error, $contextInstance = null) {
        $contextInstance = ($contextInstance)? $contextInstance : sfContext::getInstance();
        $scrapydocs = sfConfig::get('app_scrapy_documents');
        $cvi = $etablissement->getCvi();
        $file = $scrapydocs.'/parcellaire-'.$cvi.'-parcellaire.pdf';
        $message = "";

        if (empty($file)) {
            $message = "Le PDF des parcelles n'existe pas.";
            $contextInstance->getLogger()->info("saveParcellairePDF: error: ".$message);
            throw new Exception($message);
        }

        $this->findOrCreateDocPDF($etablissement->identifiant, date('Y-m-d'), 'PRODOUANE', $file, $cvi);

        return $file;

    }

    public function find($id, $hydrate = self::HYDRATE_DOCUMENT, $force_return_ls = false) {
        $doc = parent::find($id, $hydrate, $force_return_ls);

        if ($doc && $doc->type != self::TYPE_MODEL) {
            sfContext::getInstance()->getLogger()->info("ParcellaireClient::find()".sprintf("Document \"%s\" is not type of \"%s\"", $id, self::TYPE_MODEL));
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

    public function findOrCreateDocPDF($identifiant, $date = null, $source = null, $path=null, $cvi, $type = self::TYPE_COUCHDB) {
        if (! $date) {
            $date = date('Ymd');
        }
        $parcellaire = $this->getLast($identifiant);
        $declaration = $parcellaire->getDeclaration();

        if ($parcellaire && $parcellaire->date == $date) {
            if($path){
                $parcellaire->storeAttachment($path, 'application/pdf', "import-cadastre-$cvi-parcelles.pdf");
                $parcellaire->setDeclaration($declaration);

                $parcellaire->save();
            }

            return $parcellaire;
        }

    }

    public function findOrCreateDocJson($identifiant, $date = null, $source = null, $path=null, $cvi, $type = self::TYPE_COUCHDB) {
        if (! $date) {
            $date = date('Ymd');
        }
        $parcellaire = $this->getLast($identifiant);
        $declaration = $parcellaire->getDeclaration();

        if ($parcellaire && $parcellaire->date == $date) {
            if($path){
                $parcellaire->storeAttachment($path, 'text/json', "import-cadastre-$cvi-parcelles.json");
                $parcellaire->setDeclaration($declaration);
                
                $parcellaire->save();
            }
            
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
