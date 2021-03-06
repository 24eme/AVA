<?php require_once('../config/inc.php'); ?>
<?php 
	$template = 1;
	$cat_current = "wysiwyg";
	$cat_title = "Dégustation conseil";
	$page_title = "Dégustation conseil";
?>
<?php require(INCLUDES_PATH.'_header.php'); ?>


<!-- #content -->
<section id="content" class="container">

	<ol class="breadcrumb-steps">
		<li class="visited">
			<div class="step">
				<a href="#">Exploitation</a>
			</div>
		</li>
		<li class="visited">
			<div class="step">
				<a href="#">Revendication</a>
			</div>
		</li>
		<li class="active">
			<div class="step">
				<a href="#">Dégustation conseil</a>
			</div>
		</li>
		<li>
			<div class="step">
				<a href="#">Contrôle externe</a>
			</div>
		</li>
		<li>
			<div class="step">
				<a href="#">Validation</a>
			</div>
		</li>
	</ol>

	<ul class="nav nav-tabs" role="tablist">
		<li>
			<a href="#" role="tab">AOC Alsace blanc</a>
		</li>
		<li>
			<a href="#" role="tab">AOC Lieu-dit</a>
		</li>
		<li class="active">
			<a href="#" role="tab">AOC Alsace Communale</a>
		</li>
		<li>
			<a href="#" role="tab">AOC Grands Cru</a>
		</li>
	</ul>

	<div class="tab-content">
		<div class="tab-pane active">

			<p>Veuillez indiquer le nombre de lots par produits</p>

			<table class="table table-striped table-condensed">
				<thead>
					<tr>
						<th class="col-xs-6"></th>
						<th class="col-xs-2 text-center">Nom VT / SGN(lot)</th>
						<th class="col-xs-2 text-center">VT / SGN(lot)</th>
						<th class="col-xs-2 text-center">Total</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td class="col-xs-6">
							<div class="row">
								<div class="col-xs-6">
									Riesling
								</div>
								<div class="col-xs-6">
									(vol. sur place : 123,12 hl)
								</div>
							</div>
						</td>
						<td class="col-xs-2">
							<div class="form-group">
								<div class="col-xs-10 col-xs-offset-1">
									<input class="form-control input-sm input-rounded" type="text" />
								</div>
							</div>
						</td>
						<td class="col-xs-2"></td>
						<td class="col-xs-2"></td>
					</tr>
					<tr>
						<td class="col-xs-6">
							<div class="row">
								<div class="col-xs-6">
									Pinot Gris
								</div>
								<div class="col-xs-6">
									(vol. sur place : 123,12 hl)
								</div>
							</div>
						</td>
						<td class="col-xs-2">
							<div class="col-xs-10 col-xs-offset-1">
								<input class="form-control input-sm input-rounded" type="text" />
							</div>
						</td>
						<td class="col-xs-2"></td>
						<td class="col-xs-2"></td>
					</tr>
					<tr>
						<td class="col-xs-6">Pinot Noir</td>
						<td class="col-xs-2">
							<div class="col-xs-10 col-xs-offset-1">
								<input class="form-control input-sm input-rounded" type="text" />
							</div>
						</td>
						<td class="col-xs-2"></td>
						<td class="col-xs-2"></td>
					</tr>
				</tbody>
			</table>

			<button class="btn btn-default btn-plus" type="button">Ajouter un produit</button>

			<div class="row row-margin">
				<div class="col-xs-6">
					<a href="#" class="btn btn-default btn-prev"><span class="eleganticon arrow_carrot-left"></span> Retourner à l'appellation précédente</a>
				</div>
				
				<div class="col-xs-6 text-right">
					<a href="#" class="btn btn-default btn-next">Valider et passer à l'appellation <span class="eleganticon arrow_carrot-right"></span></a>
				</div>
			</div>
		</div>
	</div>

	<div class="row row-margin">
		<div class="col-xs-4"><a href="#" class="btn btn-primary btn-lg btn-block btn-prev"><span class="eleganticon arrow_carrot-left"></span> étape précendente</a></div>
		<div class="col-xs-4 col-xs-offset-4"><a href="#" class="btn btn-primary btn-lg btn-block btn-next">étape suivante <span class="eleganticon arrow_carrot-right"></span></a></div>
	</div>


	
	
</section>
<!-- end #content -->

<?php require(INCLUDES_PATH.'_footer.php'); ?>