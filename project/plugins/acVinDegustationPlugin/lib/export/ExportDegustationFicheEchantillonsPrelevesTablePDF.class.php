<?php

class ExportDegustationFicheEchantillonsPrelevesTablePDF extends ExportPDF {

    protected $degustation = null;
    protected $table = null;

    public function __construct($degustation, $type = 'pdf', $use_cache = false,  $table, $file_dir = null, $filename = null) {
        $this->degustation = $degustation;
        $this->table = $table;

        if (!$filename) {
            $filename = $this->getFileName(true);
        }
        parent::__construct($type, $use_cache, $file_dir, $filename);
    }

    public function create() {
      $lots = [];
      foreach ($this->degustation->getLotsByNumDossier() as $numero_dossier => $lotInfo) {
        foreach ($lotInfo as $ano => $lot) {
          if($lot->numero_table == $this->table){
            $lots[$numero_dossier][$ano] = $lot;
          }
        }
      }
        @$this->printable_document->addPage(
          $this->getPartial('degustation/ficheEchantillonsPrelevesTablePdf',
          array(
            'degustation' => $this->degustation,
            'table' => $this->table,
            'lots' => $lots
          )
        ));
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
        $titre = sprintf("Syndicat des Vins IGP de %s", $this->degustation->getOdg());

        return $titre;
    }

    protected function getHeaderSubtitle() {

        $header_subtitle = sprintf("%s\n\n", $this->degustation->lieu)."LISTE DES LOTS VENTILES";

        return $header_subtitle;
    }


    protected function getFooterText() {
        $footer= sprintf("Syndicat des Vins IGP de %s  %s\n\n", $this->degustation->getOdg(), $this->degustation->lieu);
        return $footer;
    }

    protected function getConfig() {

        return new ExportDegustationFicheEchantillonsPrelevesTablePDFConf();
    }

    public function getFileName($with_rev = false) {

        return self::buildFileName($this->degustation, true);
    }

    public static function buildFileName($degustation, $with_rev = false) {
        $filename = sprintf("fiche_echantillons_preleves_table_%s", $degustation->_id);


        if ($with_rev) {
            $filename .= '_' . $degustation->_rev;
        }


        return $filename . '.pdf';
    }

}
