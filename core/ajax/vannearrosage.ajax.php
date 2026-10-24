<?php
try {
  require_once __DIR__ . '/../../../../core/php/core.inc.php';
  include_file('core', 'authentification', 'php');
  if (!isConnect()) throw new Exception(__('401 - Accès non autorisé', __FILE__));
  ajax::init();

  $eq = vannearrosage::byId(init('id'));
  if (!is_object($eq) || $eq->getEqType_name() != 'vannearrosage') throw new Exception(__('Équipement inconnu', __FILE__));
  if (!$eq->hasRight('x')) throw new Exception(__('401 - Accès non autorisé', __FILE__));

  if (init('action') == 'commande') { // ouvrir / fermer depuis la tuile : passe par le plugin pour le double contrôle
    $eq->commande(init('etat') == 1, max(0, min(60, (int)init('duree'))));
    ajax::success();
  }

  if (init('action') == 'saveProg') { // programme de la vanne, depuis la tuile (dashboard, design)
    $p = vannearrosage::progValide(json_decode(init('prog'), true));
    $eq->setConfiguration('prog', $p);
    $eq->save(true);
    $eq->refreshWidget();
    ajax::success($p);
  }

  throw new Exception(__('Aucune méthode correspondante à', __FILE__) . ' : ' . init('action'));
} catch (Throwable $e) {
  ajax::error(displayException($e), $e->getCode());
}
