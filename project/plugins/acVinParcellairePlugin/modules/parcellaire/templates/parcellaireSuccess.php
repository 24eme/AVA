<?php use_helper("Date"); ?>
<?php $last = null; ?>

<?php if($sf_user->hasTeledeclaration()): ?>
    <ol class="breadcrumb">
      <li><a href="<?php echo url_for('accueil'); ?>">Déclarations</a></li>
      <li><a href="<?php echo url_for('declaration_etablissement', array('identifiant' => $parcellaire->identifiant)); ?>"><?php echo $parcellaire->getEtablissementObject()->getNom() ?> (<?php echo $parcellaire->getEtablissementObject()->identifiant ?>)</a></li>
      <li class="active"><a href="">Parcellaire au <?php echo $parcellaire->getDateFr(); ?></a></li>
    </ol>
<?php else: ?>
<ol class="breadcrumb">
  <li><a href="<?php echo url_for('parcellaire'); ?>">Parcellaire</a></li>
  <?php if($parcellaire): ?><li><a href="<?php echo url_for('parcellaire_declarant', $parcellaire->getEtablissementObject()); ?>">Parcellaire de <?php echo $parcellaire->getEtablissementObject()->getNom() ?> (<?php echo $parcellaire->getEtablissementObject()->identifiant ?>) </a></li><?php endif;?>
</ol>
<?php endif; ?>
<div class="page-header no-border">
    <?php if($parcellaire): ?>
    <h2>Parcellaire au <?php echo Date::francizeDate($parcellaire->date); ?> <small class="text-muted"><?= $parcellaire->source ?></small></h2>
    <?php else: ?>
    <h2>Parcellaire</h2>
    <?php endif;?>
</div>
<?php if(!$sf_user->hasTeledeclaration()): ?>
<div class="clearfix">
  <a href="<?= url_for('parcellaire_scrape_douane', $etablissement) ?>" class="btn btn-warning pull-right" style="margin-bottom: 10px;">
      <i class="glyphicon glyphicon-refresh"></i> Mettre à jour via Prodouane
  </a>
</div>
<?php endif;?>

<?php if ($sf_user->hasFlash('erreur_import')): ?>
<div class="alert alert-danger" role="alert">
    <strong>Erreur :</strong> <?= $sf_user->getFlash('erreur_import') ?>
</div>
<?php endif; ?>
<?php if ($sf_user->hasFlash('success_import')): ?>
<div class="alert alert-success" role="alert">
    <strong>Succès :</strong> <?= $sf_user->getFlash('success_import') ?>
</div>
<?php endif; ?>

<?php if(isset($form)): ?>
<div class="row row-margin">
    <div class="col-xs-12">
        <?php include_partial('etablissement/formChoice', array('form' => $form, 'action' => url_for('parcellaire_etablissement_selection'),  'noautofocus' => true)); ?>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-xs-12">
        <?php if($parcellaire): ?>
            <div class="well">
                <?php include_partial('etablissement/blocDeclaration', array('etablissement' => $parcellaire->getEtablissementObject())); ?>
            </div>
        <?php else: ?>
            <p>Aucun parcellaire n'existe pour <?php echo $etablissement->getNom() ?></p>
        <?php endif; ?>
    </div>
</div>
<?php $parcellaire_client = ParcellaireClient::getInstance();
if($parcellaire && $parcellaire_client->getParcellaireGeoJson($parcellaire->getEtablissementObject()->getIdentifiant(), $parcellaire->getEtablissementObject()->getCvi()) != false): ?>
    <div>
        <?php include_partial('parcellaire/parcellaireMap', array('parcellaire' => $parcellaire)); ?>
    </div>
<?php endif; ?>
<?php $list_communes = [];?>
<?php if ($parcellaire && count($parcellaire->declaration) > 0): ?>
    <div class="row">
        <div class="col-xs-12">
            <?php foreach ($parcellaire->declaration->getParcellesByCommune() as $commune => $parcelles): ?>
            	<h3><?php echo $commune ?></h3>
                <div class="clearfix">
                    <a onclick="zoomOnMap()" class="pull-right" href="#" style="margin-bottom: 1em">
                        <i class="glyphicon glyphicon-map-marker"></i> Voir les parcelles
                    </a>
                </div>

                <table class="table table-bordered table-condensed table-striped tableParcellaire">
                  <thead>
		        	<tr>
		                <th class="col-xs-2">Lieu-dit</th>
                    <th class="col-xs-1" style="text-align: right;">Section</th>
                    <th class="col-xs-1">N° parcelle</th>
                    <th class="col-xs-3">Cépage</th>
                    <th class="col-xs-1" style="text-align: center;">Année plantat°</th>
                    <th class="col-xs-1" style="text-align: right;">Surface <span class="text-muted small">(ha)</span></th>
                    <th class="col-xs-1">Écart Pieds</th>
                    <th class="col-xs-1">Écart Rang</th>
                    <th class="col-xs-1">Carte</th>
		            </tr>
                  </thead>
                    <tbody>



                        <?php
                        foreach ($parcelles as $detail):
                            $classline = '';
                            $styleline = '';
                            $styleproduit = '';
                            $styleparcelle = '';
                            $classparcelle = '';
                            $classsuperficie = '';
                            $stylesuperficie = '';
                            if (isset($diff) && $diff) {
                                if ($last && !$last->exist($detail->getHash())) {
                                    $styleline = 'border-style: solid; border-width: 1px; border-color: darkgreen;';
                                } else {
                                    if ($last && $detail->getParcelleIdentifiant() != $last->get($detail->getHash())->getParcelleIdentifiant()) {
                                        $styleparcelle = 'border-style: solid; border-width: 1px; border-color: darkorange;';
                                    }
                                    if ($last && $detail->getSuperficie() != $last->get($detail->getHash())->getSuperficie()) {
                                        $styleline = (!$detail->superficie) ? 'text-decoration: line-through; border-style: solid; border-width: 1px; border-color: darkred' : '';
                                        $classline = (!$detail->superficie) ? 'danger' : '';
                                        $stylesuperficie = (!$detail->superficie) ? 'border-style: solid; border-width: 1px; border-color: darkgreen' : 'border-style: solid; border-width: 1px; border-color: darkgreen';
                                    }
                                }
                                if (!$detail->getSuperficie()) {
                                    $stylesuperficie = 'border-style: solid; border-width: 1px; border-color: darkred';
                                }

                                if (!$detail->isAffectee()) {
                                    $styleline="opacity: 0.4;";
                                    $styleproduit="text-decoration: line-through;";
                                    $styleparcelle="text-decoration: line-through;";
                                    $stylesuperficie="text-decoration: line-through;";
                                    $classline="";
                                    $classsuperficie="";
                                    $classparcelle="";
                                }
                            }
                            $classecart = '';
                            $classcepage = '';
                            if ($detail->hasProblemExpirationCepage()) {
                              $classline .=  ' warning';
                              $classcepage .= ' text-warning strong';
                            }
                            if ($detail->hasProblemEcartPieds()) {
                              $classline .=  ' danger';
                              $classecart .= ' text-danger strong';
                            }
                            if ($detail->hasProblemCepageAutorise()) {
                              $classline .= ' danger';
                              $classcepage .= ' text-danger strong';
                            }
                            ?>
                            <tr class="<?php echo $classline ?>" style="<?php echo $styleline; ?>">

                                <td style="<?php echo $styleproduit; ?>"><?php echo $detail->lieu; ?></td>
                                <td class="" style="text-align: right;"><?php echo $detail->section; $list_communes[$detail["code_commune"]] = $detail["code_commune"];?></td>
                                <td class=""><?php echo $detail->numero_parcelle; ?></td>
                                <td class="<?php echo $classcepage; ?>" style="<?php echo $styleproduit; ?>" ><span class="text-muted"><?php echo $detail->produit->getLibelle(); ?></span> <?php echo $detail->cepage; ?></td>
                                <td class="" style="text-align: center;"><?php echo $detail->campagne_plantation; ?></td>
                                <td class="" style="text-align: right;"><?php echo $detail->superficie; ?></td>
                                <td class="<?php echo $classecart; ?>" style="text-align: center;" ><?php echo ($detail->exist('ecart_pieds'))? $detail->get('ecart_pieds') : '&nbsp;'; ?></td>
                                <td class="<?php echo $classecart; ?>" style="text-align: center;" ><?php echo ($detail->exist('ecart_rang'))? $detail->get('ecart_rang') : '&nbsp;'; ?></td>
                                <td>
                                    <div id="par" class="clearfix">
                                        <a href="#parcelle<?php echo $detail->numero_parcelle; ?>" onclick="showParcelle('<?php echo $detail->idu; ?>')" class="pull-right">
                                            <i class="glyphicon glyphicon-map-marker"></i> Voir la parcelle
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php
                        endforeach;

                        ?>
                    </tbody>
                </table>
    <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if($sf_user->hasTeledeclaration()): ?>
<div class="row row-margin row-button">
    <div class="col-xs-4">
        <a href="<?php echo url_for("declaration_etablissement", array('identifiant' => $parcellaire->identifiant)); ?>" class="btn btn-default btn-upper"><span class="glyphicon glyphicon-chevron-left"></span> Retour</a>
    </div>
</div>
<?php endif;?>

