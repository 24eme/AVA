<?php

class EtablissementSecurity implements SecurityInterface {

    const DECLARANT_DREV = 'DECLARANT_DREV';
    const DECLARANT_CONDITIONNEMENT = 'DECLARANT_CONDITIONNEMENT';
    const DECLARANT_TRANSACTION = 'DECLARANT_TRANSACTION';
    const DECLARANT_PARCELLAIRE = 'DECLARANT_PARCELLAIRE';

    protected $user;
    protected $etablissement;

    public static function getInstance($user, $etablissement) {

        return new EtablissementSecurity($user, $etablissement);
    }

    public function __construct($user, $etablissement) {
        $this->user = $user;
        $this->etablissement = $etablissement;
    }

    public function isAuthorized($droits) {
        if(!is_array($droits)) {
            $droits = array($droits);
        }

        /*** DECLARANT ***/
        if(!$this->user->isAdmin() && $this->user->getCompte()->identifiant != $this->etablissement->getSociete()->getMasterCompte()->identifiant && !$this->user->hasDrevAdmin()) {

            return false;
        }

        if(in_array(self::DECLARANT_DREV, $droits) && !$this->etablissement->famille == EtablissementFamilles::FAMILLE_PRODUCTEUR_VINIFICATEUR) {

            return false;
        }

        if(in_array(self::DECLARANT_CONDITIONNEMENT, $droits) && !$this->etablissement->hasFamille(EtablissementFamilles::FAMILLE_NEGOCIANT)) {

            return false;
        }

        if(in_array(self::DECLARANT_TRANSACTION, $droits) && !$this->etablissement->hasFamille(EtablissementFamilles::FAMILLE_NEGOCIANT)) {

            return false;
        }

        return true;
    }

}
