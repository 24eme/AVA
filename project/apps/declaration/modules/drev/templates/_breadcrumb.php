<ol class="breadcrumb">
  <li><a href="<?php echo url_for('accueil'); ?>">Déclarations</a></li>
  <li><a href="<?php echo url_for('declaration_etablissement', array('identifiant' => $drev->identifiant, 'campagne' => $drev->campagne)); ?>"><?php echo $drev->getEtablissementObject()->getNom() ?> (<?php echo $drev->getEtablissementObject()->identifiant ?>)</a></li>
  <li class="active"><a href="">DRev de <?php echo $drev->getCampagne(); ?></a></li>
</ol>
