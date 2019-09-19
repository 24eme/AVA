<?php

class FichierClient extends acCouchdbClient {
    public static function getInstance()
    {
      return acCouchdbManager::getClient("Fichier");
    }

    public function createDoc($identifiant, $papier = false)
    {
        $fichier = new Fichier();
        $fichier->initDoc($identifiant);

        if($papier) {
            $fichier->add('papier', 1);
        }

        $fichier->date_import = date('Y-m-d');

        return $fichier;
    }

    /**
     * Scrape le site des douanes pour récupérer des documents administratifs
     * et les converti en document CouchDB
     *
     * @param Etablissement $etablissement Un objet CouchDB Etablissement
     * @param string $type Le type de document à scraper
     * @param string $annee L'année de création du document
     *
     * @return false|Un document
     */
    public function scrapeAndSaveFiles($etablissement, $type, $annee)
    {
    	$this->scrapeFiles($etablissement, $type, $annee);
    	if (!$files = $this->getScrapyFiles($etablissement, strtolower($type), $annee)) {
    		return false;
    	}
    	$client = $this->getClientFromType($type);
    	if (!$fichier = $client->findByArgs($etablissement->identifiant,  $annee)) {
    		$fichier = $client->createDoc($etablissement->identifiant, $annee);
    	}
    	if ($fichier->isNew()) {
    		$fichier->setLibelle("$type $annee issue de Prodouane");
    		$fichier->save();
    	}
    	try {
	    	foreach ($files as $file) {
	    		$fichier->storeFichier($file);
	    	}
	    	$fichier->save();
    	} catch (Exception $e) {
        	throw new sfException($e->getMessage());
        	return;
        }
        return $fichier;
    }

    private function scrapeFiles($etablissement, $type, $annee)
    {
        $types = array(
            DRCsvFile::CSV_TYPE_DR,
            SV11CsvFile::CSV_TYPE_SV11,
            SV12CsvFile::CSV_TYPE_SV12,
        );

    	if (!in_array($type, $types)) {
    		throw new sfException("$type is not allowed for scrapy file");
    	}

        $scrapybin = sfConfig::get("app_scrapy_bin");
        $scrapydocs = sfConfig::get("app_scrapy_documents");

        if (!preg_match('/^[0-9]{4}$/', $annee)) {
            throw new sfException("$annee is not a valid year for scrapy file");
        }

        if (!$etablissement->cvi || !preg_match('/^[0-9A]{5}[0-9A-Z]{5}$/i', $etablissement->cvi)) {
            throw new sfException("CVI : ".$etablissement->cvi." is not a valid cvi for scrapy file");
        }

        $t = strtolower($type);
        $cvi = $etablissement->cvi;
        exec("$scrapybin/download_douane.sh $t $annee $cvi > /dev/null 2>&1");
    }

    private function getScrapyFiles($etablissement, $type, $annee)
    {
    	$files = array();
    	$directory = new DirectoryIterator(sfConfig::get('app_scrapy_documents'));
    	$iterator = new IteratorIterator($directory);
    	$regex = new RegexIterator($directory, '/^'.$type.'-'.$annee.'-'.$etablissement->cvi.'\..+$/i', RegexIterator::MATCH);
    	foreach($regex as $file) {
    		$files[] = $file->getPathname();
    	}
    	return $files;
    }

    /**
     * Retourne une instance d'un client en fonction du type
     *
     * @param string $type Le type de document
     *
     * @return Un client
     */
    public function getClientFromType($type)
    {
    	switch ($type) {
    		case 'DR':
    			$client = DRClient::getInstance();
    			break;
    		case 'SV11':
    			$client = SV11Client::getInstance();
    			break;
    		case 'SV12':
    			$client = SV12Client::getInstance();
    			break;
    		default:
    			$client = null;
    	}
    	return $client;
    }

    public function getCategories() {

        return array(
            "Dr" => "Dr",
            "Drev" => "Drev",
            "Identification" => "Identification"
        );
    }
}
