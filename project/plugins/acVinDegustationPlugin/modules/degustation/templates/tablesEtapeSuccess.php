<?php use_helper('Float') ?>

<?php include_partial('degustation/breadcrumb', array('degustation' => $degustation)); ?>
<?php include_partial('degustation/step', array('degustation' => $degustation, 'active' => DegustationEtapes::ETAPE_TABLES)); ?>


<div class="page-header no-border">
  <h2>Organisation des tables</h2>
</div>

<div class="row">
  <div class="col-xs-12">
    <div class="panel panel-default" style="min-height: 160px">
      <div class="panel-heading">
        <h2 class="panel-title">
          <div class="row">
            <div class="col-xs-12">Tables des échantillons</div>
          </div>
        </h2>
      </div>
      <div class="panel-body">
        <div class="row">
          <div class="col-xs-12">
            <strong>Organisation des tables</strong>
            <br/>
            <br/>
        </div>

        <div class="col-xs-12">
            <a href="<?php echo url_for('degustation_fiche_echantillons_preleves_pdf', $degustation) ?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-file"></span>&nbsp;Fiche lots ventilés (Anonymisés)</a>
            <a href="<?php echo url_for('degustation_fiche_echantillons_preleves_table_pdf', $degustation) ?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-file"></span>&nbsp;Fiche lots ventilés (Anonymisés par table)</a>
            <a href="<?php echo url_for('degustation_etiquette_anonymes_pdf', $degustation) ?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-file"></span>&nbsp;Tableau des étiquettes (Anonymisés)</a>
            <br/>
            <br/>
        </div>

        </div>

        <div class="row">
          <div class="col-xs-7">
            <strong class="lead"><?php echo $infosDegustation["nbTables"]; ?></strong> Tables prévues :</br>
            <?php if($infosDegustation["nbTables"]): ?>
              <?php foreach ($degustation->getTablesWithFreeLots() as $numTable => $table): ?>
                <strong class="lead"><?php echo DegustationClient::getNumeroTableStr($numTable); ?></strong> <strong><?php echo count($table->lots); ?> lots</strong><?php if($numTable < count($degustation->getTablesWithFreeLots())):?>, <?php endif;?>
              <?php endforeach; ?>
              </br>
            <?php else: ?>
              <strong>Aucune tables</strong></br>
            <?php endif; ?>
            <strong class="lead"><?php echo ($infosDegustation["nbFreeLots"])? $infosDegustation["nbFreeLots"] : 'Aucun' ?></strong> <strong>Échantillon<?php echo ($infosDegustation["nbFreeLots"]>1)? 's' : '' ?></strong> sans table
          </div>
          <div class="col-xs-5 text-right">
            <a class="btn btn-default btn-sm" href="<?php echo url_for('degustation_organisation_table', $degustation) ?>" >&nbsp;Échantillons par table&nbsp;<span class="glyphicon glyphicon-pencil"></span></a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="row row-button">
			<div class="col-xs-4"><a href="<?php echo url_for("degustation") ?>" class="btn btn-default btn-upper"><span class="glyphicon glyphicon-chevron-left"></span> Retour</a></div>
			<div class="col-xs-4 text-center">
			</div>
			<div class="col-xs-4 text-right"><a class="btn btn-primary btn-upper" href="<?php echo url_for('degustation_anonymats_etape', $degustation) ?>" >Valider&nbsp;<span class="glyphicon glyphicon-chevron-right"></span></a></div>
	</div>
