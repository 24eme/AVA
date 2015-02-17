<?php

/**
 * Model for Parcellaire
 *
 */
class Parcellaire extends BaseParcellaire {

    protected $declarant_document = null;

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
    }

    public function storeDeclarant() {
        $this->declarant_document->storeDeclarant();
    }

    public function getEtablissementObject() {

        return EtablissementClient::getInstance()->findByIdentifiant($this->identifiant);
    }

    public function initDoc($identifiant, $campagne) {
        $this->identifiant = $identifiant;
        $this->campagne = $campagne;
        $this->set('_id', ParcellaireClient::getInstance()->buildId($this->identifiant, $this->campagne));
        $this->storeDeclarant();
    }

    public function getConfiguration() {
        return acCouchdbManager::getClient('Configuration')->retrieveConfiguration($this->campagne);
    }

    public function storeEtape($etape) {
        $isGt = ParcellaireEtapes::getInstance()->isGt($this->etape, $etape, true);
        if ($isGt) {
            return false;
        }
        $this->add('etape', $etape);
    }

    public function isPapier() {
        return $this->exist('papier') && $this->get('papier');
    }

    public function hasVendeurs() {
        return count($this->vendeurs);
    }

    public function initProduitFromLastParcellaire() {
        if (count($this->declaration) == 0) {
            $this->importProduitsFromLastParcellaire();
        }
    }

    public function getParcellaireLastCampagne() {
        $campagnePrec = $this->campagne - 1;
        $parcellairePrevId = ParcellaireClient::getInstance()->buildId($this->identifiant, $campagnePrec);
        return ParcellaireClient::getInstance()->find($parcellairePrevId);
    }

    private function importProduitsFromLastParcellaire() {
        $parcellairePrev = $this->getParcellaireLastCampagne();
        if (!$parcellairePrev) {
            return;
        }
        $this->declaration = $parcellairePrev->declaration;
    }

    public function getProduits($onlyActive = false) {
        return $this->declaration->getProduits($onlyActive = false);
    }

    public function getAllParcellesByAppellations() {
        $appellations = $this->declaration->getAppellations();
        $parcellesByAppellations = array();
        foreach ($appellations as $appellation) {
            $parcellesByAppellations[$appellation->getHash()] = new stdClass();
            $parcellesByAppellations[$appellation->getHash()]->appellation = $appellation;
            $parcellesByAppellations[$appellation->getHash()]->parcelles = $appellation->getProduitsCepageDetails();
        }
        return $parcellesByAppellations;
    }

    public function getAllParcellesByAppellation($appellationHash) {
       $allParcellesByAppellations = $this->getAllParcellesByAppellations();
        $parcelles = array();

        foreach ($allParcellesByAppellations as $appellation) {
            $appellationKey = str_replace('appellation_', '', $appellation->appellation->getKey());
            if ($appellationKey == $appellationHash) {
                $parcelles = $appellation->parcelles;
            }
        }
        return $parcelles;
    }

    public function getAppellationNodeFromAppellationKey($appellationKey, $autoAddAppellation = false) {
        $appellations = $this->declaration->getAppellations();
        $appellationNode = null;
        foreach ($appellations as $key => $appellation) {
            if ('appellation_' . $appellationKey == $key) {
                $appellationNode = $appellation;
                break;
            }
        }
        if (!$appellationNode && $autoAddAppellation) {
            foreach ($this->getConfiguration()->getDeclaration()->getNoeudAppellations() as $key => $appellation) {
                if ('appellation_' . $appellationKey == $key) {
                    $appellationNode = $this->addAppellation($appellation->getHash());
                    break;
                }
            }
        }
        return $appellationNode;
    }

    public function addProduit($hash, $add_appellation = true) {
        $config = $this->getConfiguration()->get($hash);
        if ($add_appellation) {
            $this->addAppellation($config->getAppellation()->getHash());
        }

        $produit = $this->getOrAdd($config->getHash());
        $produit->getLibelle();

        return $produit;
    }

    public function addProduitParcelle($hash, $parcelleKey, $commune, $section, $numero_parcelle, $lieu = null) {
        $produit = $this->getOrAdd($hash);
        $this->addProduit($produit->getHash());

        return $produit->addDetailNode($parcelleKey, $commune, $section, $numero_parcelle, $lieu);
    }

    public function addParcelleForAppellation($appellationKey, $cepage, $commune, $section, $numero_parcelle, $lieu = null) {
        $hash = str_replace('-', '/', $cepage);
        $commune = KeyInflector::slugify($commune);
        $section = KeyInflector::slugify($section);
        $numero_parcelle = KeyInflector::slugify($numero_parcelle);
        $parcelleKey = KeyInflector::slugify($commune . '-' . $section . '-' . $numero_parcelle);
        $this->addProduitParcelle($hash, $parcelleKey, $commune, $section, $numero_parcelle, $lieu);
    }

    public function addAppellation($hash) {
        $config = $this->getConfiguration()->get($hash);
        $appellation = $this->getOrAdd($config->hash);

        return $appellation;
    }

    public function addAcheteur($type, $cvi) {
        if ($this->acheteurs->add($type)->exist($cvi)) {

            return $this->acheteurs->add($type)->get($cvi);
        }

        $acheteur = $this->acheteurs->add($type)->add($cvi);

        if ($cvi == $this->identifiant) {
            $acheteur->nom = "Sur place";
            $acheteur->cvi = $cvi;
            $acheteur->commune = null;

            return $acheteur;
        }

        $etablissement = EtablissementClient::getInstance()->find('ETABLISSEMENT-' . $cvi, acCouchdbClient::HYDRATE_JSON);

        if (!$etablissement) {
            throw new sfException(sprintf("L'acheteur %s n'a pas été trouvé", 'ETABLISSEMENT-' . $cvi));
        }

        $acheteur->nom = $etablissement->raison_sociale;
        $acheteur->cvi = $cvi;
        $acheteur->commune = $etablissement->commune;

        return $acheteur;
    }

    public function hasParcelleForAppellationKey($appellationKey) {
        $allParcelles = $this->getAllParcellesByAppellations();
        foreach ($allParcelles as $hash => $appellation) {
            if ($appellation->appellation->getKey() == 'appellation_' . $appellationKey) {
                foreach ($appellation->appellation->getMentions() as $mention) {
                    if (!count($mention->getLieux())) {
                        return false;
                    }
                }
                return true;
            }
        }
        return false;
    }

    public function getParcellesByLieux() {
        $parcellesByLieux = array();
        foreach ($this->declaration->getProduitsCepageDetails() as $parcelle) {
                $keyLieu = $parcelle->getLibelleComplet();
                if (!array_key_exists($keyLieu, $parcellesByLieux)) {
                    $parcellesByLieux[$keyLieu] = new stdClass();
                    $parcellesByLieux[$keyLieu]->total_superficie = 0;
                    $parcellesByLieux[$keyLieu]->appellation_libelle = $parcelle->getAppellation()->getLibelle();
                    $parcellesByLieux[$keyLieu]->lieu_libelle = $parcelle->getLieuLibelle();
                    $parcellesByLieux[$keyLieu]->parcelles = array();
                    $parcellesByLieux[$keyLieu]->acheteurs = $parcelle->getCepage()->getAcheteursNode();
                }
                
                $parcellesByLieux[$keyLieu]->parcelles[$parcelle->gethash()] = new stdClass();
                $parcellesByLieux[$keyLieu]->parcelles[$parcelle->gethash()]->cepage_libelle = $parcelle->getCepageLibelle();
                $parcellesByLieux[$keyLieu]->parcelles[$parcelle->gethash()]->parcelle = $parcelle;
                $parcellesByLieux[$keyLieu]->total_superficie += $parcelle->superficie;
            }
        return $parcellesByLieux;
    }

    public function validate($date = null) {
        if (is_null($date)) {
            $date = date('Y-m-d');
        }

        $this->declaration->cleanNode();
        $this->validation = $date;
        $this->validateOdg();
    }

    public function validateOdg() {
        $this->validation_odg = date('Y-m-d');
    }

}
