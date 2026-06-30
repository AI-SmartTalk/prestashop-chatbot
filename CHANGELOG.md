# Changelog

All notable changes to the AI SmartTalk PrestaShop module.
Versioning follows [SemVer](https://semver.org/). Dates are ISO-8601.

## [3.8.0] — 2026-05-23

Option « inclure les produits hors stock » dans la synchronisation produits.

### Added

- **Toggle « Include out-of-stock products »** dans la section *Product Filters*
  de la page de configuration. Par défaut **désactivé** : seuls les produits
  actifs avec un stock > 0 dans au moins une boutique sont envoyés à AI
  SmartTalk — comportement historique préservé. Activé, **tous** les produits
  actifs sont synchronisés indépendamment de leur niveau de stock, et un
  produit qui tombe à zéro n'est plus purgé du knowledge base. Le webhook
  `ps_on_product_out_of_stock` reste indépendant et continue de notifier les
  ruptures.
- **`SyncFilterHelper::shouldProductBeKept(int $idProduct)`** — orchestrateur
  unique de la décision keep/purge sur changement d'état (update produit,
  combination, quantité). Les hooks correspondants délèguent désormais à cette
  méthode au lieu d'appeler `isProductActiveInAnyShop` en dur, ce qui rend le
  comportement uniforme entre la requête SQL en masse et les hooks unitaires.
- **`MultistoreHelper::isProductActiveOnlyInAnyShop`** /
  **`isProductActiveOnlyInShop`** — variantes sans contrainte de stock du
  helper existant, utilisées par le mode « include out-of-stock ». Les helpers
  historiques (`isProductActiveInAnyShop`, `isProductActiveInShop`) restent
  inchangés.
- Script `upgrade/upgrade-3.8.0.php` qui initialise explicitement la nouvelle
  clé `AI_SMART_TALK_SYNC_INCLUDE_OUT_OF_STOCK` à `0` sur les installations
  existantes, pour rendre la valeur visible/auditable dans `ps_configuration`.

## [3.7.0] — 2026-05-17

Sortie majeure axée e-commerce : déclinaisons, devises non-EUR, promotions
et durcissement du rendu des cartes produit dans le chatbot embed.

### Added

- **Synchronisation des déclinaisons (combinations)** — un produit dont les
  ventes passent uniquement par ses variantes (taille, couleur, etc.) est
  désormais correctement synchronisé. Chaque variante part dans le payload
  avec son SKU, son prix, son stock, ses attributs lisibles et son image.
  *(Cas client libyen : tous les produits sont des déclinaisons.)*
- **Devise & précision décimale** — le prix est désormais émis sous forme de
  string formatée selon la précision exacte de la devise de la boutique :
  3 décimales pour LYD/BHD/JOD, 2 pour EUR/USD, 0 pour JPY. Fini les
  `12.000 LYD` qui devenaient `12` après `json_encode` sur un float.
  Nouveaux champs payload : `price_decimals`, `currency`, `currency_sign`.
- **Promotions** — détection automatique des `ps_specific_price` (et des
  réductions catalogue/groupe cumulées) avec calcul du prix de référence,
  du montant et du pourcentage de remise. Nouveaux champs payload :
  `original_price`, `discount_percent`, `discount_amount`, `discount_type`
  (`percentage`, `amount`, `computed`). Côté chatbot, les cartes peuvent
  afficher un prix barré et un badge `-N%`.
- **Hooks variantes** — enregistrement de `actionProductAttributeCreate`,
  `actionProductAttributeUpdate` et `actionProductAttributeDelete` pour
  resyncer le produit parent dès qu'une variante est modifiée seule
  (ex : ajout d'une taille, changement de prix d'impact). Le script
  d'upgrade `upgrade-3.7.0.php` ajoute ces hooks aux installations
  existantes — aucune action manuelle requise.
- **Helper `PriceCalculator`** — nouveau service interne qui isole le
  calcul prix-final vs prix-original via deux appels à
  `Product::getPriceStatic` et la capture de `$specificPriceOutput`.
  Réutilisé identiquement pour les produits parents et les variantes.
- **Helper `PriceFormatter`** — formatage déterministe des prix en string
  honorant la précision de la devise. Survit au round-trip JSON.

### Fixed

- **`Db::getRow` ajoute toujours `LIMIT 1` automatiquement** — corrigé un
  bug 500 sur le staging client (`SQLSTATE 1064 ... near 'LIMIT 1'`) en
  retirant les `LIMIT 1` que nous ajoutions dans `CombinationHelper`.
- **Éligibilité combo-only** — un produit en stock uniquement via ses
  combinaisons (ligne `id_product_attribute > 0` dans `ps_stock_available`)
  est désormais éligible à la sync. Auparavant le check ne regardait que
  `id_product_attribute = 0` et excluait ce cas. Idem dans
  `MultistoreHelper::isProductActiveInShop` qui pilotait les hooks de
  cleanup.

### Compatibility

- PrestaShop **1.7.5.1 → 9.x** — validé par smoke tests réels sur 3
  versions (PHP 7.2.34, 7.4.33, 8.4.10). Le contrat
  `Product::getPriceStatic` (16 args + `&$specific_price_output`) est
  stable depuis PS 1.7.0.
- Aucune migration DB. Tous les nouveaux champs payload sont **additifs
  et optionnels** : les boutiques sans déclinaisons et/ou sans promotions
  envoient un payload identique à 3.6.x.

### Testing

- 269 tests PHPUnit unit (+11 nouveaux `PriceCalculator`)
- 10 tests E2E Playwright sur PrestaShop 9 réel, dont un produit avec
  combinaisons + promo `-20%` active
- 46 smoke tests par version PS (×3 versions)
- Tests compagnons sur les repos `aismarttalk` (back) et `chatbot-front`
  (widget embed).

### Companion changes

- **Backend `aismarttalk`** — `GetProductsTools` remonte au LLM les champs
  promo et devise ; le prompt système instruit explicitement les
  conversions `original_price → originalPrice` et `discount_percent → -N%` ;
  `embed-config` expose désormais `shopCurrency` / `shopCurrencySign` /
  `shopPriceDecimals`. Cf. PR DEV-842.
- **Widget `chatbot-front`** — les cartes acceptent et formatent `currency`,
  `originalPrice` et `badge`. Auto-badge `-N%` calculé côté front quand le
  LLM oublie le champ (4ᵉ couche de défense). Cf. PR DEV-842.

---

## [3.6.1] — 2026-04

- CDN : publication automatique de `aismarttalk.zip` sur `cdn.aismarttalk.tech`
  à chaque merge sur `main`.
- PS 1.7.5.1 : finalisation du support PHP 7.2 + cache clear dans
  `init-test-ps1751`.

## [3.6.0] — 2026-04

- PR + gestion PrestaShop 1.5 / 1.7.5.1 (PHP 7.2).

## [3.5.0]

- Chiffrement des payloads toujours actif (suppression du flag opt-in).
- Avatar récupéré depuis la config embed plateforme (suppression du
  cache local d'URL d'avatar).

## [3.3.0]

- Multi-site : calcul et stockage d'un `site_identifier` par boutique.

[3.7.0]: https://github.com/AI-SmartTalk/prestashop-chatbot/releases/tag/v3.7.0
[3.6.1]: https://github.com/AI-SmartTalk/prestashop-chatbot/releases/tag/v3.6.1
[3.6.0]: https://github.com/AI-SmartTalk/prestashop-chatbot/releases/tag/v3.6.0
[3.5.0]: https://github.com/AI-SmartTalk/prestashop-chatbot/releases/tag/v3.5.0
[3.3.0]: https://github.com/AI-SmartTalk/prestashop-chatbot/releases/tag/v3.3.0
