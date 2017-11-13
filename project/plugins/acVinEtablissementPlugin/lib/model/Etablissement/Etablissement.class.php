<?php

class Etablissement extends BaseEtablissement implements InterfaceCompteGenerique {

    protected $_interpro = null;
    protected $droit = null;
    protected $societe = null;

    /**
     * @return _Compte
     */
    public function getInterproObject() {
        if (is_null($this->_interpro)) {
            $this->_interpro = InterproClient::getInstance()->find($this->interpro);
        }

        return $this->_interpro;
    }

    public function constructId() {
        $this->set('_id', 'ETABLISSEMENT-' . $this->identifiant);
        if ($this->isViticulteur()) {
            $this->raisins_mouts = is_null($this->raisins_mouts) ? EtablissementClient::RAISINS_MOUTS_NON : $this->raisins_mouts;
            $this->exclusion_drm = is_null($this->exclusion_drm) ? EtablissementClient::EXCLUSION_DRM_NON : $this->exclusion_drm;
            $this->type_dr = is_null($this->type_dr) ? EtablissementClient::TYPE_DR_DRM : $this->type_dr;
        }

        if ($this->isViticulteur() || $this->isNegociant()) {
            $this->relance_ds = is_null($this->relance_ds) ? EtablissementClient::RELANCE_DS_OUI : $this->relance_ds;
        }

        $this->statut = is_null($this->statut) ? EtablissementClient::STATUT_ACTIF : $this->statut;
    }

    public function setRelanceDS($value) {
        if (!($this->isViticulteur() || $this->isNegociant())) {
            throw new sfException("Le champs 'relance_ds' n'est valable que pour les viticulteurs ou les négociants");
        }

        $this->_set('relance_ds', $value);
    }

    public function setExclusionDRM($value) {
        if (!($this->isViticulteur())) {
            throw new sfException("Le champs 'exclusion_drm' n'est valable que pour les viticulteurs");
        }

        $this->_set('exclusion_drm', $value);
    }

    public function setRaisinsMouts($value) {
        if (!($this->isViticulteur())) {
            throw new sfException("Le champs 'raisins_mouts' n'est valable que pour les viticulteurs");
        }

        $this->_set('raisins_mouts', $value);
    }

    public function setTypeDR($value) {
        if (!($this->isViticulteur())) {
            throw new sfException("Le champs 'type_dr' n'est valable que pour les viticulteurs");
        }

        $this->_set('type_dr', $value);
    }

    public function getAllDRM() {
        return acCouchdbManager::getClient()->startkey(array($this->identifiant, null))
                        ->endkey(array($this->identifiant, null))
                        ->getView("drm", "all");
    }

    public function setCompte($c) {
      return $this->_set('compte', $c);
    }

    public function getMasterCompte() {
        if ($this->compte) {
            return $this->getSociete()->getCompte($this->compte);
        }
        return $this->getSociete()->getCompte($this->getSociete()->compte_societe);
    }

    public function getContact() {

        return $this->getMasterCompte();
    }

    public function getSociete() {
      if (!$this->societe) {
          $this->societe = SocieteClient::getInstance()->findSingleton($this->id_societe);
      }
      return $this->societe;
    }

    public function setSociete($s) {
      $this->societe = $s;
    }

    public function isSameAdresseThanSociete() {

        return $this->isSameAdresseThan($this->getSociete()->getMasterCompte());
    }

    public function isSameContactThanSociete() {

        return $this->isSameContactThan($this->getSociete()->getMasterCompte());
    }

    public function isSameCompteThanSociete() {

        return ($this->compte == $this->getSociete()->compte_societe);
    }

    public function getNumCompteEtablissement() {
        if (!$this->compte)
            return null;
        if ($this->compte != $this->getSociete()->compte_societe)
            return $this->compte;
        return null;
    }

    public function getNoTvaIntraCommunautaire() {
        $societe = $this->getSociete();

        if (!$societe) {

            return null;
        }

        return $societe->no_tva_intracommunautaire;
    }

    public function getDenomination() {

        return ($this->nom) ? $this->nom : $this->raison_sociale;
    }

    public function addLiaison($type, $etablissement,$saveOther = true) {

        if(!$etablissement instanceof Etablissement) {
            $etablissement = EtablissementClient::getInstance()->find($etablissement);
        }

        if (!in_array($type, EtablissementClient::listTypeLiaisons()))
            throw new sfException("liaison type \"$type\" unknown");
        $liaison = $this->liaisons_operateurs->add($type . '_' . $etablissement->_id);
        $liaison->type_liaison = $type;
        $liaison->id_etablissement = $etablissement->_id;
        $liaison->libelle_etablissement = $etablissement->nom;
        $compte = $this->getMasterCompte();
        $compte->addTag('manuel',$type);
        $compte->save();
        if($etablissement->exist('ppm') && $etablissement->ppm){
          $liaison->ppm = $etablissement->ppm;
        }
        if($etablissement->exist('cvi') && $etablissement->cvi){
          $liaison->cvi = $etablissement->cvi;
        }
        if($saveOther && ($type == EtablissementClient::TYPE_LIAISON_BAILLEUR)){
          $etablissement->addLiaison(EtablissementClient::TYPE_LIAISON_METAYER,$this,false);
          $etablissement->save();
          $compteOther = $etablissement->getMasterCompte();
          $compteOther->addTag('manuel',EtablissementClient::TYPE_LIAISON_METAYER);
          $compteOther->save();
        }
        if($saveOther && ($type == EtablissementClient::TYPE_LIAISON_METAYER)){
          $etablissement->addLiaison(EtablissementClient::TYPE_LIAISON_BAILLEUR,$this,false);
          $etablissement->save();
          $compteOther = $etablissement->getMasterCompte();
          $compteOther->addTag('manuel',EtablissementClient::TYPE_LIAISON_BAILLEUR);
          $compteOther->save();
        }
        return $liaison;
    }

    public function isNegociant() {
        return ($this->famille == EtablissementFamilles::FAMILLE_NEGOCIANT);
    }

    public function isViticulteur() {
        return ($this->famille == EtablissementFamilles::FAMILLE_PRODUCTEUR);
    }

    public function isCourtier() {
        return ($this->famille == EtablissementFamilles::FAMILLE_COURTIER);
    }
    public function isRepresentant() {
        return ($this->famille == EtablissementFamilles::FAMILLE_REPRESENTANT);
    }



    public function getFamilleType() {
        $familleType = array(EtablissementFamilles::FAMILLE_PRODUCTEUR => 'vendeur',
            EtablissementFamilles::FAMILLE_NEGOCIANT => 'acheteur',
            EtablissementFamilles::FAMILLE_COURTIER => 'mandataire');
        return $familleType[$this->famille];
    }

    public function getDepartement() {
        if ($this->siege->code_postal) {
            return substr($this->siege->code_postal, 0, 2);
        }
        return null;
    }

    public function getDroit() {
        if (is_null($this->droit)) {

            $this->droit = new EtablissementDroit($this);
        }

        return $this->droit;
    }

    public function hasDroit($droit) {

        return $this->getDroit()->has($droit);
    }

    public function getDroits() {
        return EtablissementFamilles::getDroitsByFamilleAndSousFamille($this->famille, $this->sous_famille);
    }

    public function isInterpro() {
        return ($this->region != EtablissementClient::REGION_HORS_CVO);
    }

    protected function initFamille() {
        if (!$this->famille) {
            $this->famille = EtablissementFamilles::FAMILLE_PRODUCTEUR;
        }
    }

    public function save() {
        $societe = $this->getSociete();
        if(!$this->getCompte()){
            $this->compte = $societe->getMasterCompte()->_id;
        }


        if(!$this->isSameAdresseThanSociete() || !$this->isSameContactThanSociete()){
            if ($this->isSameCompteThanSociete()) {
                $compte = $societe->createCompteFromEtablissement($this);
                $compte->addOrigine($this->_id);
            } else {
                $compte = $this->getMasterCompte();
            }

            $this->pushContactAndAdresseTo($compte);

            $compte->id_societe = $this->getSociete()->_id;
            $compte->nom = $this->nom;

            $this->compte = $compte->_id;
        } else if(!$this->isSameCompteThanSociete()){
            $compteEtablissement = $this->getMasterCompte();
            $compteSociete = $this->getSociete()->getMasterCompte();

            $this->compte = $compteSociete->_id;
            $this->getSociete()->removeContact($compteEtablissement->_id);
            $compteEtablissement = $this->compte;
        }

        if($this->isSameAdresseThanSociete()) {
            $this->pullAdresseFrom($this->getSociete()->getMasterCompte());
        }
        if($this->isSameContactThanSociete()) {
            $this->pullContactFrom($this->getSociete()->getMasterCompte());
        }
        $this->initFamille();
        $this->raison_sociale = $societe->raison_sociale;
        $this->interpro = "INTERPRO-declaration";
        if(class_exists("VracConfiguration") && VracConfiguration::getInstance()->getRegionDepartement() !== false) {
            $this->region = EtablissementClient::getInstance()->calculRegion($this);
        }

        if($this->isNew()) {
            $societe->addEtablissement($this);
        }

        parent::save();

        $societe->save();

        if(!$this->isSameCompteThanSociete()) {
            $compte->save();
        }
    }

    public function delete() {
      $this->getSociete()->removeEtablissement($this);
      parent::delete();
    }

    public function isActif() {
        return $this->statut && ($this->statut == EtablissementClient::STATUT_ACTIF);
    }

     public function isSuspendu() {
        return $this->statut && ($this->statut == SocieteClient::STATUT_SUSPENDU);
    }


    public function setIdSociete($id) {
        $soc = SocieteClient::getInstance()->findSingleton($id);
        if (!$soc)
            throw new sfException("$id n'est pas une société connue");
        $this->_set("id_societe", $id);
    }

    public function __toString() {

        return sprintf('%s (%s)', $this->nom, $this->identifiant);
    }

    public function getBailleurs() {
        $bailleurs = array();
        if (!(count($this->liaisons_operateurs)))
            return $bailleurs;
        $liaisons = $this->liaisons_operateurs;
        foreach ($liaisons as $key => $liaison) {
            if ($liaison->type_liaison == EtablissementClient::TYPE_LIAISON_BAILLEUR)
                $bailleurs[$key] = $liaison;
        }
        return $bailleurs;
    }

    public function findBailleurByNom($nom) {
        $bailleurs = $this->getBailleurs();
        foreach ($bailleurs as $key => $liaison) {
            if ($liaison->libelle_etablissement == str_replace("&", "", $nom))
                return EtablissementClient::getInstance()->find($liaison->id_etablissement);
            if ($liaison->exist('aliases'))
                foreach ($liaison->aliases as $alias) {
                    if (strtoupper($alias) == strtoupper(str_replace("&", "", $nom)))
                        return EtablissementClient::getInstance()->find($liaison->id_etablissement);
                }
        }
        return null;
    }

    public function addAliasForBailleur($identifiant_bailleur, $alias) {
        $bailleurNameNode = EtablissementClient::TYPE_LIAISON_BAILLEUR . '_' . $identifiant_bailleur;
        if (!$this->liaisons_operateurs->exist($bailleurNameNode))
            throw new sfException("La liaison avec le bailleur $identifiant_bailleur n'existe pas");
        if (!$this->liaisons_operateurs->$bailleurNameNode->exist('aliases'))
            $this->liaisons_operateurs->$bailleurNameNode->add('aliases');
        $this->liaisons_operateurs->$bailleurNameNode->aliases->add(str_replace("&amp;", "", $alias), str_replace("&amp;", "", $alias));
    }

    public function getSiegeAdresses() {
        $a = $this->siege->adresse;
        if ($this->siege->exist("adresse_complementaire")) {
            $a .= ' ; ' . $this->siege->adresse_complementaire;
        }
        return $a;
    }

    public function findEmail() {
        $etablissementPrincipal = $this->getSociete()->getEtablissementPrincipal();
        if ($this->_get('email')) {
            return $this->get('email');
        }
        if (($etablissementPrincipal->identifiant == $this->identifiant) || !$etablissementPrincipal->exist('email') || !$etablissementPrincipal->email) {
            return false;
        }
        return $etablissementPrincipal->get('email');
    }

    public function getEtablissementPrincipal() {
        return SocieteClient::getInstance()->findSingleton($this->id_societe)->getEtablissementPrincipal();
    }

    public function hasCompteTeledeclarationActivate() {
        return $this->getSociete()->getMasterCompte()->isTeledeclarationActive();
    }

    public function getEmailTeledeclaration() {
        if ($this->exist('teledeclaration_email') && $this->teledeclaration_email) {
            return $this->teledeclaration_email;
        }
        if ($this->exist('email') && $this->email) {
            return $this->email;
        }
        return null;
    }

    public function setEmailTeledeclaration($email) {
        $this->add('teledeclaration_email', $email);
    }

    public function hasRegimeCrd() {
        return $this->exist('crd_regime') && $this->crd_regime;
    }


    public function addCommentaire($s) {
        $c = $this->get('commentaire');
        if ($c) {
            return $this->_set('commentaire', $c . "\n" . $s);
        }
        return $this->_set('commentaire', $s);
    }

    public function getNatureLibelle() {
        if(!$this->exist('nature_inao') || !$this->nature_inao){
            return null;
        }
        return EtablissementClient::getInstance()->getNatureInaoLibelle($this->nature_inao);
    }

    public function hasLegalSignature() {
      return $this->getSociete()->hasLegalSignature();
    }

    public function hasFamille($famille) {

        return $this->famille == $famille;
    }

}