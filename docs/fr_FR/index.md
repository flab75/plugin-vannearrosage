# Vanne d'arrosage

Une tuile pour une vanne d'arrosage **Sonoff SWV** appairée dans Zigbee2MQTT. **Une tuile = un équipement du plugin**,
posé sur l'objet de son choix.

Affiche : état, ouvrir / fermer, durée, bouton « Arroser N min », durée et volume arrosés, batterie, défaut, **programme**.

## Créer une tuile

1. Plugins › Nature › Vanne d'arrosage › **Ajouter**, donner un nom.
2. Choisir l'**objet parent**, cocher **Activer** et **Visible**.
3. Choisir l'**équipement lu** (la vanne dans Zigbee2MQTT) et les réglages proposés.
4. Sauvegarder. La tuile apparaît sur le dashboard de son objet.

## Réglages

- Durées proposées, bouton « Arroser N min » (0 = sans), lecture du débitmètre.
- Configuration du plugin : topic de base de Zigbee2MQTT (`zigbee2mqtt` par défaut), pour le débitmètre.

## Fonctionnement

- **Ouvrir** ouvre la vanne pour la durée réglée ; elle se referme seule.
- **Programme** : toutes les heures sur une plage, tous les jours ou certains jours, pour une durée, pendant une période.
  Le programme est gardé dans l'équipement et exécuté par le plugin chaque minute (rien à créer). Il ne relance pas une vanne déjà ouverte.
- **Lire le débitmètre** : la Sonoff SWV ne pousse pas la durée et le volume arrosés. Le plugin les demande à Zigbee2MQTT chaque minute pendant
  l'arrosage, puis une fois après (plugin MQTT Manager requis).
- Firmware 1.0.7 : Zigbee2MQTT lit la durée et le volume **octets inversés** (1 min → 16777216). Correctif à mettre dans le *calcul* de ces
  commandes, dans l'équipement Zigbee2MQTT :
  `min(#value#, (#value# % 256) * 16777216 + floor(#value# / 256) % 256 * 65536 + floor(#value# / 65536) % 256 * 256 + floor(#value# / 16777216))`
- Les valeurs de la vanne suivent en direct. Le plugin ne crée aucune commande : la tuile lit l'équipement Zigbee2MQTT.

## Double contrôle (ouverture et surtout fermeture)

- Ouvrir / Fermer depuis la tuile et le programme passent par le plugin, qui note l'ordre envoyé. La minute suivante, il vérifie l'état
  rapporté par la vanne : ordre non suivi → renvoyé (3 envois en tout), puis **alerte** (sur la tuile, dans le centre de messages et le log).
  Pour une fermeture, le plugin continue ensuite d'essayer chaque minute.
- **Fermeture de sécurité** : une vanne ouverte plus longtemps que la durée réglée + 2 min (60 min si la durée est inconnue) est
  refermée par le plugin, quelle que soit l'origine de l'ouverture (bouton de la vanne, autre scénario, Zigbee2MQTT).
- Le contrôle repose sur l'état rapporté par la vanne : une vanne hors de portée qui garde son dernier état connu « ouverte » déclenche
  les renvois puis l'alerte.

## Limites connues

- Le programme part à la minute exacte : si le cron de Jeedom saute cette minute (machine très chargée), l'arrosage est perdu.
- « Prochain arrosage » est calculé à l'heure du navigateur, le programme à l'heure de Jeedom : les deux doivent être dans le même fuseau.
