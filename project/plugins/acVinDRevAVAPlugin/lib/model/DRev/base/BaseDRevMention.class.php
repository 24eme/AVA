<?php
/**
 * BaseDRevMention
 * 
 * Base model for DRevMention

 * @property string $libelle

 * @method string getLibelle()
 * @method string setLibelle()
 
 */

abstract class BaseDRevMention extends _DRevDeclarationNoeud {
                
    public function configureTree() {
       $this->_root_class_name = 'DRev';
       $this->_tree_class_name = 'DRevMention';
    }
                
}