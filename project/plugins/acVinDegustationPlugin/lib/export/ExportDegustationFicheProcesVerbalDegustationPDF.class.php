<?php

class ExportDegustationFicheProcesVerbalDegustationPDF extends ExportPDF {

    protected $degustation = null;

    public function __construct($degustation, $type = 'pdf', $use_cache = false, $file_dir = null, $filename = null) {
        $this->degustation = $degustation;

        if (!$filename) {
            $filename = $this->getFileName(true);
        }
        parent::__construct($type, $use_cache, $file_dir, $filename);
    }

    public function create() {
      $etablissements = array();
      $nbLotTotal = 0;
      foreach ($this->degustation->getLotsByNumDossierNumCuve() as $numDossier => $lotsEtablissement) {
				$etablissement = EtablissementClient::getInstance()->findByIdentifiant($lotsEtablissement[array_key_first($lotsEtablissement)]->declarant_identifiant);
        $nbLotTotal += count($lotsEtablissement);
        $etablissements[$numDossier] = $etablissement;
      }
      foreach ($this->degustation->getLotsDegustesByAppelation() as $appellation => $lotsDegustes) {
        @$this->printable_document->addPage(
          $this->getPartial('degustation/ficheProcesVerbalDegustationPdf',
          array(
            'degustation' => $this->degustation,
            'etablissements' => $etablissements,
            'nbLotTotal' => $nbLotTotal,
            "appellation" => $appellation,
            "nbTables" => $this->degustation->getLastNumeroTable(),
            "nbDegustateurs" => count($this->degustation->getDegustateursConfirmes()),
            "nbDegustateursPresents" => count($this->degustation->getDegustateursATable()),
            'lotsDegustes' => $lotsDegustes
          )
        ));
      }
    }


    public function output() {
        if($this->printable_document instanceof PageableHTML) {
            return parent::output();
        }

        return file_get_contents($this->getFile());
    }

    public function getFile() {

        if($this->printable_document instanceof PageableHTML) {
            return parent::getFile();
        }

        return sfConfig::get('sf_cache_dir').'/pdf/'.$this->getFileName(true);
    }

    protected function getHeaderTitle() {
        $titre = sprintf("Syndicat des Vins IGP de %s PROCÈS VERBAL DE DEGUSTATION", $this->degustation->getOdg());

        return $titre;
    }

    protected function getHeaderSubtitle() {

        $header_subtitle = sprintf("%s\n\n", $this->degustation->lieu
        );

        return $header_subtitle;
    }


    protected function getFooterText() {
        $footer= sprintf("Syndicat des Vins IGP de %s  %s\n\n", $this->degustation->getOdg(), $this->degustation->lieu);
        return $footer;
    }

    protected function getConfig() {

        return new ExportDegustationFicheProcesVerbalDegustationPDFConfig();
    }

    public function getFileName($with_rev = false) {

        return self::buildFileName($this->degustation, true);
    }

    public static function buildFileName($degustation, $with_rev = false) {
        $filename = sprintf("proces_verbal_global_%s", $degustation->_id);


        if ($with_rev) {
            $filename .= '_' . $degustation->_rev;
        }


        return $filename . '.pdf';
    }

}
