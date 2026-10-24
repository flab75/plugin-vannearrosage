<?php
/* Contrôles sans effet sur la box : règle et validation du programme, double contrôle, données de chaque vanne.
   Usage : sudo -u www-data php tests/selftest.php */
require_once __DIR__ . '/../core/class/vannearrosage.class.php';
$ok = function ($v, $m) { if (!$v) { echo "ECHEC : $m\n"; exit(1); } }; // assert() est désactivé en production

$lun7h = strtotime('2026-10-19 07:00:00'); // un lundi
$p = ['actif' => 1, 'freq' => 'j', 'heure' => '07:00', 'heureFin' => '20:00', 'jours' => [], 'duree' => 10, 'fin' => 0];
$ok(vannearrosage::progOk($p, $lun7h), 'tous les jours 07:00');
$ok(!vannearrosage::progOk($p, $lun7h + 60), 'pas à 07:01');
$ok(!vannearrosage::progOk(['actif' => 0] + $p, $lun7h), 'désactivé');
$ok(!vannearrosage::progOk(['fin' => ($lun7h - 1) * 1000] + $p, $lun7h), 'programme expiré');
$ok(vannearrosage::progOk(['freq' => 's', 'jours' => [1]] + $p, $lun7h) && !vannearrosage::progOk(['freq' => 's', 'jours' => [2]] + $p, $lun7h), 'certains jours');
$h = ['freq' => 'h', 'heure' => '06:30', 'heureFin' => '08:00'] + $p;
$ok(vannearrosage::progOk($h, $lun7h + 1800) && !vannearrosage::progOk($h, $lun7h) && !vannearrosage::progOk($h, $lun7h + 5400), 'toutes les heures 06:30 → 08:00');
$ok(vannearrosage::progOk(['heure' => '06:30', 'heureFin' => '07:30'] + $h, $lun7h - 1800 + 3600) && !vannearrosage::progOk(['heureFin' => '07:15'] + $h, $lun7h + 1800), 'fin de plage en minutes');
$ok(!vannearrosage::progOk(null, $lun7h) && !vannearrosage::progOk('x', $lun7h), 'programme absent');
echo "programme OK\n";

$v = vannearrosage::progValide(['actif' => '1', 'freq' => 's', 'heure' => '07:05', 'heureFin' => '20:00', 'jours' => ['3', 9, 3, '<b>'], 'duree' => '15', 'fin' => -5, 'x' => 'y']);
$ok($v == ['actif' => 1, 'freq' => 's', 'heure' => '07:05', 'heureFin' => '20:00', 'jours' => [3], 'duree' => 15, 'fin' => 0], 'nettoyage du programme');
foreach ([['freq' => 'x'], ['heure' => '7:5'], ['heure' => '24:00'], ['duree' => 0], ['duree' => 61], ['heure' => ['07:00']]] as $mauvais) {
  try { vannearrosage::progValide($mauvais + $v); $ok(false, 'accepté : ' . json_encode($mauvais)); } catch (Exception $e) {}
}
try { vannearrosage::progValide(null); $ok(false, 'null accepté'); } catch (Exception $e) {}
echo "validation OK\n";

$t = 1000000;
$ok(vannearrosage::suivi(['etat' => 0, 't' => $t], false, 0) && !vannearrosage::suivi(['etat' => 0, 't' => $t], true, $t + 5), 'fermeture suivie / non suivie');
$ok(vannearrosage::suivi(['etat' => 1, 't' => $t], true, 0) && vannearrosage::suivi(['etat' => 1, 't' => $t], false, $t + 30), 'ouverture suivie (ouverte, ou déjà refermée)');
$ok(!vannearrosage::suivi(['etat' => 1, 't' => $t], false, $t - 600), 'ouverture non suivie');
$ok(!vannearrosage::tropLongue($t, 10, $t + 12 * 60) && vannearrosage::tropLongue($t, 10, $t + 12 * 60 + 1), 'fermeture de sécurité à durée + 2 min');
$ok(!vannearrosage::tropLongue($t, 0, $t + 62 * 60) && vannearrosage::tropLongue($t, 0, $t + 62 * 60 + 1), 'durée inconnue = 60 min');
echo "double contrôle OK\n";


$admin = null; foreach (user::all() as $u) if ($u->getProfils() == 'admin') { $admin = $u; break; }
foreach (eqLogic::byType('vannearrosage') as $eq) {
  $d = $eq->donnees($admin);
  $ok(is_array($d['c']) && is_array($d['k']) && json_encode($d, JSON_HEX_TAG) !== false, 'données de ' . $eq->getHumanName());
  $ok(count($d['c']) > 0 && is_array($d['prog'] ?? []), 'vanne lue sans commandes : ' . $eq->getHumanName());
  echo 'données OK : ' . $eq->getHumanName() . ' (' . count($d['c']) . " commandes)\n";
}
echo "TOUT OK\n";
