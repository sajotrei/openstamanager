<?php

include_once __DIR__.'/../../core.php';

$module = Modules::get($id_module);

if ($module['name'] == 'Fatture di vendita') {
    $dir = 'entrata';
    $tipo_anagrafica = tr('Cliente');
} else {
    $dir = 'uscita';
    $tipo_anagrafica = tr('Fornitore');
}

?>
<form action="" method="post" id="add-form">
	<input type="hidden" name="op" value="add">
	<input type="hidden" name="backto" value="record-edit">
	<input type="hidden" name="dir" value="<?php echo $dir; ?>">

	<div class="row">
		<div class="col-md-6">
			 {[ "type": "date", "label": "<?php echo tr('Data'); ?>", "name": "data", "required": 1, "value": "-now-" ]}
		</div>

		<div class="col-md-6">
			{[ "type": "select", "label": "<?php echo $tipo_anagrafica; ?>", "name": "idanagrafica", "required": 1, "values": "query=SELECT an_anagrafiche.idanagrafica AS id, ragione_sociale AS descrizione FROM an_anagrafiche INNER JOIN (an_tipianagrafiche_anagrafiche INNER JOIN an_tipianagrafiche ON an_tipianagrafiche_anagrafiche.idtipoanagrafica=an_tipianagrafiche.idtipoanagrafica) ON an_anagrafiche.idanagrafica=an_tipianagrafiche_anagrafiche.idanagrafica WHERE descrizione='<?php echo $tipo_anagrafica; ?>' AND deleted=0 ORDER BY ragione_sociale", "value": "<?php echo $idanagrafica; ?>", "icon-after": "add|<?php echo Modules::get('Anagrafiche')['id']; ?>|tipoanagrafica=<?php echo $tipo_anagrafica; ?>" ]}
		</div>
	</div>


	<div class="row">
		<div class="col-md-6">
			{[ "type": "select", "label": "<?php echo tr('Tipo fattura'); ?>", "name": "idtipodocumento", "required": 1, "values": "query=SELECT id, descrizione FROM co_tipidocumento WHERE dir='<?php echo $dir; ?>'", "value": "" ]}
		</div>
	<?php
		if( $dir == "entrata" ){
		
			if (isset($_SESSION[$dir]['idsezionale'])){
				$idsezionale_default = $_SESSION[$dir]['idsezionale'];
			}
			else{
					
				(!empty($_SESSION['idsezionale'])) ? $idsezionale_default = $_SESSION['idsezionale'] :$idsezionale_default = get_var("Sezionale predefinito fatture di vendita");		
			}
		
	?>			
			<div class="col-md-6">
				{[ "type": "select", "label": "<?php echo tr('Sezionale'); ?>", "name": "idsezionale", "required": 1, "class": "", "values": "query=SELECT id, nome AS descrizione FROM co_sezionali WHERE dir='entrata' ORDER BY nome", "value": "<?= $idsezionale_default ?>", "extra": "" ]}
			</div>
	<?php
		}else{
	?>
			<div class="col-md-6">
				{[ "type": "text", "label": "<?php echo tr('Numero fattura del fornitore'); ?>", "name": "numero_esterno", "required": 1, "class": "", "value": "", "extra": "" ]}
			</div>
	<?php
		}
	?>
	</div>

	<!-- PULSANTI -->
	<div class="row">
		<div class="col-md-12 text-right">
			<button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> <?php echo tr('Aggiungi'); ?></button>
		</div>
	</div>
</form>
