<?php

class tirageComponents extends sfComponents {

    public function executeMonEspace(sfWebRequest $request) {
        $this->etablissement = $this->getUser()->getEtablissement();
        $campagne = ConfigurationClient::getInstance()->getCampagneManager()->getCurrent();

        $this->nbDeclaration = TirageClient::getInstance()->getLastNumero($this->etablissement->identifiant, $campagne);
        $nextNumero = $this->nbDeclaration + 1;
        $this->nieme = '';
        if ($nextNumero > 1) {
            $this->nieme = $nextNumero."ème";
        }

        $this->tirage = TirageClient::getInstance()->find('TIRAGE-' . $this->etablissement->identifiant . '-' . $campagne. sprintf("%02d", $this->nbDeclaration));
        
        if($this->tirage && $this->tirage->validation){
            $this->tirage = TirageClient::getInstance()->find('TIRAGE-' . $this->etablissement->identifiant . '-' . $campagne. sprintf("%02d", $nextNumero));
        }
        $this->tiragesHistory = array();
    }

}