<?php
require_once __DIR__ . '/../../../../core/php/core.inc.php';

/* Une tuile = un équipement : pilote une vanne Zigbee2MQTT (Sonoff SWV) lue sur un équipement z2m,
   affiche son état et exécute chaque minute le programme saisi dans la tuile. */
class vannearrosage extends eqLogic {
  const ON = 'json::{"state":"ON"}', OFF = 'json::{"state":"OFF"}', DUREE = 'json::{"manual_irrigation_duration":"#slider#"}';
  const MARGE = 2, ESSAIS = 3; // min après la durée réglée avant fermeture de sécurité ; envois d'un ordre avant alerte

  public static function cron() { // une tuile en erreur n'empêche pas les autres
    foreach (self::byType('vannearrosage', true) as $eq) {
      try { $eq->vanne(); } catch (Throwable $e) { log::add('vannearrosage', 'error', $eq->getHumanName() . ' : ' . $e->getMessage()); }
    }
  }

  private function source() { // équipement Zigbee2MQTT de la vanne
    $eq = eqLogic::byId($this->getConfiguration('source'));
    return is_object($eq) && $eq->getEqType_name() == 'z2m' ? $eq : null;
  }

  /* programme de la vanne : {actif, freq: h|j|s, heure: HH:MM, heureFin: HH:MM (freq h), jours: [1..7] (1 = lundi), duree: min, fin: timestamp ms (0 = toujours)}
     même règle que match() dans vannearrosage.js (à garder identiques) */
  public static function progOk($p, $t) {
    if (!is_array($p) || empty($p['actif'])) return false;
    if (!empty($p['fin']) && $t * 1000 > $p['fin']) return false;
    [$H, $M] = array_map('intval', explode(':', $p['heure']));
    $h = (int)date('G', $t); $m = (int)date('i', $t);
    if ($p['freq'] == 'h') { [$FH, $FM] = array_map('intval', explode(':', $p['heureFin'])); return $m == $M && $h >= $H && $h * 60 + $m <= $FH * 60 + $FM; }
    return $h == $H && $m == $M && ($p['freq'] == 'j' || in_array((int)date('N', $t), $p['jours']));
  }

  // programme reçu de la tuile : seuls ces champs, dans ces formes
  public static function progValide($p) {
    $hm = function ($x) { if (!is_string($x) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $x)) throw new Exception(__('Heure invalide', __FILE__)); return $x; };
    if (!is_array($p) || !in_array($p['freq'] ?? '', ['h', 'j', 's'], true)) throw new Exception(__('Programme invalide', __FILE__));
    $d = (int)($p['duree'] ?? 0);
    if ($d < 1 || $d > 60) throw new Exception(__('Durée invalide', __FILE__));
    $j = array_values(array_unique(array_filter(array_map('intval', (array)($p['jours'] ?? [])), function ($x) { return $x >= 1 && $x <= 7; })));
    return ['actif' => empty($p['actif']) ? 0 : 1, 'freq' => $p['freq'], 'heure' => $hm($p['heure'] ?? ''), 'heureFin' => $hm($p['heureFin'] ?? ''),
      'jours' => $j, 'duree' => $d, 'fin' => max(0, (int)($p['fin'] ?? 0))];
  }

  private function vanne() {
    $s = $this->source();
    if (!$s) return;
    $c = function ($k) use ($s) { return cmd::byEqLogicIdAndLogicalId($s->getId(), $k); };
    $etat = $c('state');
    if (!is_object($etat)) return;
    $ouverte = $etat->execCmd() == 1;
    // le débitmètre de la Sonoff SWV ne pousse pas ses valeurs : on les demande chaque minute pendant l'arrosage, puis une fois après
    if ($this->getConfiguration('debit') && class_exists('mqtt2') && ($ouverte || $this->getCache('ouverte'))) {
      mqtt2::publish(config::byKey('z2m_base', 'vannearrosage', 'zigbee2mqtt') . '/' . $s->getLogicalId() . '/get',
        json_encode(['real_time_irrigation_volume' => '', 'real_time_irrigation_duration' => '']));
    }
    $this->setCache('ouverte', $ouverte);
    $depuis = strtotime($etat->getValueDate()); // dernier changement d'état
    // double contrôle : l'ordre envoyé a-t-il été suivi ? sinon on le renvoie, puis alerte (et on continue de fermer)
    $o = $this->getCache('ordre');
    if (is_array($o) && time() - $o['t'] >= 50) {
      if (self::suivi($o, $ouverte, $depuis)) {
        $this->setCache('ordre', null);
        $this->alerte('');
      } elseif ($o['n'] < self::ESSAIS) {
        log::add('vannearrosage', 'warning', $this->getHumanName() . ' : ' . ($o['etat'] ? __('ouverture', __FILE__) : __('fermeture', __FILE__)) . ' ' . __('non confirmée, nouvel essai', __FILE__) . ' ' . ($o['n'] + 1));
        $this->commande($o['etat'], $o['duree']);
        return;
      } else {
        $this->alerte($o['etat'] ? __('La vanne ne s\'est pas ouverte', __FILE__) : __('La vanne ne se ferme pas', __FILE__));
        if ($o['etat']) $this->setCache('ordre', null);
        else { $this->commande(false); return; } // fermeture : on insiste chaque minute, l'alerte reste jusqu'à confirmation
      }
    }
    // sécurité : ouverte au-delà de la durée réglée (+ marge) sans fermeture en cours → on ferme, quelle que soit l'origine de l'ouverture
    $d = $c('manual_irrigation_duration');
    if ($ouverte && !(is_array($o) && !$o['etat']) && self::tropLongue($depuis, is_object($d) ? (float)$d->execCmd() : 0, time())) {
      log::add('vannearrosage', 'warning', $this->getHumanName() . ' : ' . __('ouverte trop longtemps, fermeture de sécurité', __FILE__));
      $this->commande(false);
      return;
    }
    $p = $this->getConfiguration('prog');
    if ($ouverte || !self::progOk($p, time())) return;
    $this->commande(true, $p['duree']);
    log::add('vannearrosage', 'info', $this->getHumanName() . ' : ' . __('arrosage programmé', __FILE__) . ' ' . $p['duree'] . ' min');
  }

  // ordre suivi ? fermeture : la vanne est fermée ; ouverture : ouverte, ou a changé d'état depuis l'ordre (courte durée déjà finie)
  public static function suivi($o, $ouverte, $depuis) { return $o['etat'] ? $ouverte || $depuis >= $o['t'] : !$ouverte; }

  // ouverte depuis $depuis plus longtemps que la durée réglée (min ; inconnue = 60) + MARGE ?
  public static function tropLongue($depuis, $duree, $t) { return $t > $depuis + (($duree > 0 ? $duree : 60) + self::MARGE) * 60; }

  // ouvre (pour $duree min, sinon la durée réglée dans la vanne) ou ferme, et note l'ordre : cron() vérifie la minute suivante qu'il est suivi
  public function commande($ouvrir, $duree = 0) {
    $s = $this->source();
    if (!$s) throw new Exception(__('Équipement lu introuvable', __FILE__));
    $c = function ($k) use ($s) { return cmd::byEqLogicIdAndLogicalId($s->getId(), $k); };
    $a = $c($ouvrir ? self::ON : self::OFF);
    if (!is_object($a)) throw new Exception(__('Commande de la vanne introuvable', __FILE__));
    if ($ouvrir && $duree > 0 && is_object($c(self::DUREE))) {
      $c(self::DUREE)->execCmd(['slider' => $duree]); // la vanne se referme seule après cette durée
      sleep(3);
    }
    $a->execCmd();
    $o = $this->getCache('ordre');
    $n = is_array($o) && $o['etat'] == ($ouvrir ? 1 : 0) ? $o['n'] + 1 : 1; // renvoi du même ordre = essai suivant
    $this->setCache('ordre', ['etat' => $ouvrir ? 1 : 0, 'duree' => $duree, 't' => time(), 'n' => $n]);
  }

  // alerte affichée sur la tuile et, à son apparition, dans le centre de messages ; '' = effacée
  private function alerte($m) {
    if ($m === $this->getCache('alerte', '')) return;
    $this->setCache('alerte', $m);
    if ($m !== '') {
      log::add('vannearrosage', 'error', $this->getHumanName() . ' : ' . $m);
      message::add('vannearrosage', $this->getHumanName() . ' : ' . $m);
    }
    $this->refreshWidget();
  }

  private static function lib() { // ponytail: bibliothèque recopiée dans chaque tuile (gardée par window.vannearrosage) ; fichier servi à part si le poids gêne
    static $js = [];
    $l = translate::getLanguage();
    return $js[$l] ??= translate::exec(file_get_contents(__DIR__ . '/../template/vannearrosage.js'), 'plugins/vannearrosage/core/template/vannearrosage.js');
  }

  // données de la tuile : commandes de l'équipement lu, réglages, programme
  public function donnees($_user = null) {
    $k = []; $c = [];
    $src = $this->source();
    if ($src && $src->hasRight('r', $_user)) {
      foreach ($src->getCmd() as $cmd) {
        $id = $cmd->getId(); $lid = $cmd->getLogicalId();
        $c[$id] = ['id' => $id, 'n' => $cmd->getName(), 'u' => $cmd->getUnite(), 'v' => $cmd->getType() == 'info' ? $cmd->execCmd() : null];
        if ($lid !== '' && !isset($k[$lid])) $k[$lid] = $id;
        if (!isset($k[$cmd->getName()])) $k[$cmd->getName()] = $id;
      }
    }
    $cf = [];
    foreach (['durees' => '5,10,15,20,30,45,60', 'rapide' => 15] as $x => $def) {
      $cf[$x] = $this->getConfiguration($x, '') === '' ? $def : $this->getConfiguration($x);
    }
    return ['t' => 'vanne', 'id' => $this->getId(), 'k' => $k, 'c' => $c, 'cf' => $cf, 'prog' => $this->getConfiguration('prog', null), 'alerte' => $this->getCache('alerte', '')];
  }

  public function toHtml($_version = 'dashboard') {
    $replace = $this->preToHtml($_version, [], true); // sans cache : la tuile embarque les valeurs de l'équipement lu
    if (!is_array($replace)) return $replace;
    $d = $this->donnees();
    $json = json_encode($d, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR); // inséré après template_replace : les # ne sont pas touchés
    $replace['#cmd#'] = '';
    $replace += ['#eqLogic_class#' => 'eqLogic_layout_default', '#background-color#' => ''];
    $html = template_replace($replace, getTemplate('core', 'dashboard', 'tuile', 'vannearrosage')); // ponytail: même gabarit en mobile
    return $this->postToHtml($_version, strtr($html, ['@@LIB@@' => self::lib(), '@@DATA@@' => $json]));
  }
}

class vannearrosageCmd extends cmd {
  public function execute($_options = []) {
  }
}
