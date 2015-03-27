<?php
/**
 * Model for DegustationPrelevement
 *
 */

class DegustationPrelevement extends BaseDegustationPrelevement {

    public function generateAnonymatPrelevementComplet() {
        if(!$this->anonymat_prelevement) {
            $this->anonymat_prelevement_complet = null;
        }

        $code_cepage = $this->getCodeCepage();

        if(!$code_cepage) {
            $code_cepage = '__';
        }

        $this->anonymat_prelevement_complet = sprintf("%s %03d %03X", $code_cepage, $this->anonymat_prelevement, $this->anonymat_prelevement + 2560);
    }

    public function setAnonymatPrelevement($value) {
        $return = $this->_set('anonymat_prelevement', $value);

        $this->generateAnonymatPrelevementComplet();

        return $return;
    }

    public function getCodeCepage() {

        return substr($this->hash_produit, -2);
    }
}