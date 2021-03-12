<?php use_helper("Date"); ?>
<?php use_helper('Float') ?>
<?php use_helper('Lot') ?>

<?php include_partial('degustation/breadcrumb', array('degustation' => $degustation)); ?>
<?php include_partial('degustation/step', array('degustation' => $degustation, 'active' => DegustationEtapes::ETAPE_LOTS)); ?>

<div class="page-header no-border">
    <h2>Prélèvement des lots <small class="text-muted">Campagne <?php echo $degustation->campagne; ?></small></h2>
</div>
<div class="alert alert-info" role="alert">
  <table class="table table-condensed">
    <tbody>
      <tr class="vertical-center">
        <td class="col-xs-3" >Nombre total de <strong>lots prévus&nbsp;:</strong></td>
        <td class="col-xs-9"><strong id="nbLotsSelectionnes"><?php echo $infosDegustation["nbLots"]; ?></strong></td>
      </tr>
      <tr class="vertical-center">
        <td class="col-xs-3" >Nombre total <strong>d'adhérents prélevés&nbsp;:</strong></td>
        <td class="col-xs-9"><strong id="nbAdherentsAPrelever"><?php echo $infosDegustation["nbAdherents"]; ?></strong></td>
      </tr>
    </tbody>
  </table>
</div>

<p>Sélectionnez l'ensemble des lots à prélever pour la dégustation</p>
<form action="<?php echo url_for("degustation_prelevement_lots", $degustation) ?>" method="post" class="form-horizontal degustation prelevements">
	<?php echo $form->renderHiddenFields(); ?>

    <div class="bg-danger">
    <?php echo $form->renderGlobalErrors(); ?>
    </div>

    <table class="table table-bordered table-condensed table-striped">
        <thead>
            <tr>
                <th class="col-xs-1">Degustation voulue<br/> à partir du</th>
                <th class="col-xs-2">Opérateur</th>
                <th class="col-xs-1">Logement</th>
                <th class="col-xs-3">Produit (millésime, spécificité)</th>
                <th class="col-xs-1">Volume</th>
                <th class="col-xs-1">À prélever?</th>
            </tr>
        </thead>
		<tbody>
		<?php
            $dates = $form->getDateDegustParDrev();
			foreach (DegustationClient::getInstance()->getLotsPrelevables() as $key => $lot):
			if (isset($form['lots'][$key])):
		?>
			<tr class="vertical-center cursor-pointer" data-adherent="<?php echo $lot->numero_dossier; ?>">
        <td><?php echo DateTime::createFromFormat('Ymd', $dates[$lot->id_document])->format('d/m/Y') ?></td>
        <td><?php echo $lot->declarant_nom; ?></td>
				<td><?php echo $lot->numero_logement_operateur; ?></td>
				<td>
          <?php echo showProduitLot($lot) ?>
        </td>
        <td class="text-right"><?php echoFloat($lot->volume); ?><small class="text-muted">&nbsp;hl</small></td>
            	<td class="text-center" data-hash="<?php echo $lot->declarant_nom; ?>">
                	<div style="margin-bottom: 0;" class="form-group <?php if($form['lots'][$key]['preleve']->hasError()): ?>has-error<?php endif; ?>">
                    	<?php echo $form['lots'][$key]['preleve']->renderError() ?>
                        <div class="col-xs-12">
			            	<?php echo $form['lots'][$key]['preleve']->render(array('class' => "degustation bsswitch", "data-preleve-adherent" => "$lot->numero_dossier", "data-preleve-lot" => "$lot->numero_logement_operateur", 'data-size' => 'small', 'data-on-text' => "<span class='glyphicon glyphicon-ok-sign'></span>", 'data-off-text' => "<span class='glyphicon'></span>", 'data-on-color' => "success")); ?>
                        </div>
                    </div>
            	</td>
            </tr>
        <?php  endif; endforeach; ?>
        </tbody>
	</table>

	<div class="row row-margin row-button">
        <div class="col-xs-4"><a href="<?php echo url_for("degustation") ?>" class="btn btn-default btn-upper"><span class="glyphicon glyphicon-chevron-left"></span> Retour</a></div>
        <div class="col-xs-4 text-center">
        </div>
        <div class="col-xs-4 text-right"><button type="submit" class="btn btn-primary btn-upper">Valider <span class="glyphicon glyphicon-chevron-right"></span></button></div>
    </div>
</form>
</div>
