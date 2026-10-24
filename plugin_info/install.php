<?php
require_once __DIR__ . '/../../../core/php/core.inc.php';

function vannearrosage_install() {
}

function vannearrosage_update() {
  foreach (eqLogic::byType('vannearrosage') as $eq) $eq->save(); // crée les commandes ajoutées par la nouvelle version
}

function vannearrosage_remove() {
  config::remove('z2m_base', 'vannearrosage');
}
