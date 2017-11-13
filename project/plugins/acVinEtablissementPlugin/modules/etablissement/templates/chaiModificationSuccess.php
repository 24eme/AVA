<?php use_helper('Compte') ?>
<ol class="breadcrumb">
    <li><a href="<?php echo url_for('societe') ?>">Contacts</a></li>
    <li><a href="<?php echo url_for('societe_visualisation', array('identifiant' => $societe->identifiant)); ?>"><span class="<?php echo comptePictoCssClass($societe->getRawValue()) ?>"></span> <?php echo $societe->raison_sociale; ?></a></li>
    <li><a href="<?php echo url_for('etablissement_visualisation', array('identifiant' => $etablissement->identifiant)); ?>"><span class="<?php echo comptePictoCssClass($etablissement->getRawValue()) ?>"></span> <?php echo $etablissement->nom; ?></a></li>
    <li class="active"><a href="<?php echo url_for('etablissement_edition_chai', array('identifiant' => $etablissement->identifiant, 'num' => $num)); ?>"><?php echo "Chai n°".($num+1)." - ".$chai->nom; ?></a></li>
</ol>

<div class="row">
  <form action="<?php echo url_for("etablissement_edition_chai", array('identifiant' => $etablissement->identifiant, 'num' => $num)) ?>" method="post" class="form-horizontal">
    <?php echo $form->renderHiddenFields(); ?>
    <?php echo $form->renderGlobalErrors(); ?>
    <div class="col-xs-8">
        <div class="panel panel-default">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-9">
                        <h4>Modification du chai</h4>
                    </div>
                    <div class="col-xs-3 text-muted text-right">
                        <div class="btn-group">

                        </div>
                    </div>
                </div>
            </div>
            <div class="panel-body" style="border-right: 6px solid #9f0038;">
                <h2><span class="<?php echo comptePictoCssClass($etablissement->getRawValue()) ?>"></span>  <?php echo $etablissement->nom; ?></h2>
                <h4> Chai n°<?php echo $num + 1; ?></h4>
                <hr/>

                <div class="row" style="padding-top:10px;">
                  <div class="form-group">
                      <?php echo $form['nom']->renderError(); ?>
                      <?php echo $form['nom']->renderLabel(null, array("class" => "col-xs-4 control-label")); ?>
                      <div class="col-xs-7"><?php echo $form['nom']->render(); ?></div>
                  </div>
                </div>
                <div class="row" style="padding-top:10px;">
                  <div class="form-group">
                      <?php echo $form['adresse']->renderError(); ?>
                      <?php echo $form['adresse']->renderLabel(null, array("class" => "col-xs-4 control-label")); ?>
                      <div class="col-xs-7"><?php echo $form['adresse']->render(); ?></div>
                  </div>
                </div>
                <div class="row" style="padding-top:10px;">
                  <div class="form-group">
                      <?php echo $form['commune']->renderError(); ?>
                      <?php echo $form['commune']->renderLabel(null, array("class" => "col-xs-4 control-label")); ?>
                      <div class="col-xs-7"><?php echo $form['commune']->render(); ?></div>
                  </div>
                </div>
                <div class="row" style="padding-top:10px;">
                  <div class="form-group">
                      <?php echo $form['code_postal']->renderError(); ?>
                      <?php echo $form['code_postal']->renderLabel(null, array("class" => "col-xs-4 control-label")); ?>
                      <div class="col-xs-7"><?php echo $form['code_postal']->render(); ?></div>
                  </div>
                </div>
                <div class="row" style="padding-top:10px;">
                  <div class="form-group">
                    <?php echo $form['partage']->renderError() ?>
                    <?php echo $form['partage']->renderLabel(null, array('class' => 'col-xs-4  control-label')); ?>
                    <div class="col-xs-6 text-right"><?php echo $form['partage']->render(); ?></div>
    						   </div>
              </div>
              <div class="row" style="padding-top:10px;">
                <div class="form-group">
                  <div class="col-xs-4 control-label">
                    <h4><strong>Attributs :</strong></h4>
                  </div>
                </div>
              </div>
              <div class="row" style="padding-top:10px;">
                <div class="">
    							<?php $attributsWidget = $form['attributs']; ?>
    							<ul class="list-unstyled" >
    								<?php foreach($attributsWidget->getWidget()->getChoices() as $key => $value): ?>
                      <li>
    										<div class="form-group">
    											<div class="col-xs-4 control-label">
    												<strong><?php echo EtablissementClient::$chaisAttributsLibelles[$key]." :"; ?></strong>
    											</div>
    											<div class="col-xs-6 text-right">
    												<input class="" type="checkbox" id="<?php echo $attributsWidget->renderId() ?>_<?php echo $key ?>" name="<?php echo $attributsWidget->renderName() ?>[]" value="<?php echo $key ?>" <?php if(is_array($attributsWidget->getValue()) && in_array($key, array_keys($attributsWidget->getValue()))): ?>checked="checked"<?php endif; ?> />
    											</div>
    										</div>
    									</li>
    								<?php endforeach; ?>
    							</ul>
    						</div>
              </div>
                <hr />
                <div class="row">
                  <div class="col-xs-4">
                    <a href="<?php echo url_for('etablissement_visualisation', array('identifiant' => $etablissement->identifiant)); ?>" class="btn btn-default">Annuler</a>
                  </div>
                  <div class="col-xs-4 text-center">
                    <a onclick='return confirm("Étes vous sur de vouloir supprimer ce chai ?")' href="<?php echo url_for('etablissement_suppression_chai', array('identifiant' => $etablissement->identifiant, 'num' => $num)); ?>" class="btn btn-default">Supprimer ce chai</a>
                  </div>
                  <div class="col-xs-4 text-right">
                        <button id="btn_valider" type="submit" class="btn btn-success">
                            Valider le chai
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
  </form>
    <div class="col-xs-4">
        <?php include_component('societe', 'sidebar', array('societe' => $societe, 'activeObject' => $etablissement)); ?>
    </div>
</div>