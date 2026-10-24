<?php
require_once __DIR__ . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect('admin')) {
  include_file('desktop', '404', 'php');
  die();
}
?>
<form class="form-horizontal">
  <fieldset>
    <div class="form-group">
      <label class="col-md-4 control-label">{{Topic de base de Zigbee2MQTT}}
        <sup><i class="fas fa-question-circle tooltips" title="{{Pour la lecture du débitmètre de la vanne. Vide = zigbee2mqtt.}}"></i></sup>
      </label>
      <div class="col-md-6"><input class="configKey form-control" data-l1key="z2m_base" placeholder="zigbee2mqtt"/></div>
    </div>
  </fieldset>
</form>
