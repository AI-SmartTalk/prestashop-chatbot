# AI SmartTalk — Standard du document « produit » (canonical v1)

> Version du schéma : **1** (`payloadVersion: "1"`) · Statut : actif · Dernière mise à jour : 2026‑07‑14 (DEV‑857)

Ce document définit le **format unifié des documents de type produit** envoyés à
AI SmartTalk par tous les connecteurs e‑commerce. L'objectif : que le **tool de
search** et le **LLM** raisonnent sur un produit de manière identique quelle que
soit la plateforme source, et que l'on puisse construire des recherches avancées
(filtrer par prix, par disponibilité, proposer une date de réappro, trier…) sur
une base commune.

**PrestaShop est le connecteur de référence** : `CanonicalProductMapper` est
l'implémentation canonique du contrat. WooCommerce, Shopify et Joomla **doivent
répliquer exactement la même forme**. La source de vérité est le code
(`classes/CanonicalProductMapper.php`, `classes/StockStatusHelper.php`) et les
tests qui la figent (`tests/CanonicalProductMapperTest.php`) — pas ce document ;
en cas de doute, le code fait foi.

## Conventions transverses

- **camelCase** pour tous les noms de champs (`descriptionShort`, `externalId`,
  `restockDate`…).
- **Les identifiants sont des strings** (`externalId`, `defaultCategoryExternalId`,
  `parentExternalId`) même quand la plateforme les stocke en entier.
- **Les prix sont des objets `Money`** en **unités mineures entières (centimes)** —
  jamais des strings ni des floats. Voir §4.
- **Gestion des null selon le niveau :**
  - **Au niveau produit**, les clés optionnelles restent **présentes avec la
    valeur `null`** quand elles sont vides (ex. `reference`, `brand`, `url`,
    `image`, `restockDate`, `defaultCategoryExternalId`). Elles ne sont PAS
    retirées du JSON. Le backend les traite comme absentes (voir Zod ci-dessous).
  - **Au niveau variant** (`variants[]`), les clés optionnelles nulles sont au
    contraire **physiquement retirées** (`mapVariant()` applique un `array_filter`).
    Restent toujours : `externalId`, `attributes`, `availability`, `quantity`.
  - Champs de structure toujours présents au niveau produit (voir la colonne
    « Obligatoire » du §3) : `type`, `externalId`, `title`, `availability`,
    `quantity`, `attributes` (`[]` si vide), `variants` (`[]` si produit simple).
- Le backend valide avec un schéma **Zod `.nullable().optional()` + `.passthrough()`** :
  une clé optionnelle à `null` est équivalente à une clé absente, et les clés
  inconnues sont tolérées (compatibilité ascendante). Les champs et types
  documentés ici sont en revanche contrôlés strictement ; ajouter un champ additif
  ne casse rien.

---

## 1. Enveloppe HTTP

**Point d'entrée unique, commun à tous les connecteurs** (pas de route par
plateforme) :

`POST /api/v1/products`

Auth : en‑têtes `Authorization: Bearer <token>` + `x-chat-model-id` (le chatModel
est résolu côté backend, il n'est pas répété dans le body). La plateforme est
portée par le body via `source`.

```jsonc
{
  "payloadVersion": "1",
  "source": "prestashop",         // "prestashop" | "woocommerce" | "shopify" | "joomla"
  "siteIdentifier": "…",          // identifiant du site émetteur
  "documents": [ /* documents produit — max 50 par batch */ ]
}
```

| Champ            | Type       | Notes                                                          |
| ---------------- | ---------- | -------------------------------------------------------------- |
| `payloadVersion` | string     | Version du schéma, `"1"`.                                       |
| `source`         | string     | Plateforme émettrice (`prestashop` pour ce connecteur).        |
| `siteIdentifier` | string     | Identifiant du site (multi‑boutique inclus).                   |
| `documents`      | array      | Documents produit, **50 max par requête** (`postToApi()`).     |

### Réponse — fire‑and‑forget

L'endpoint valide **uniquement l'enveloppe** de façon synchrone, met le batch en
file (RabbitMQ) et répond **`202 Accepted`** (un `200 ok` est aussi accepté). La
**validation canonique par produit s'exécute de façon asynchrone** dans le worker
backend : il n'y a donc **pas de liste `rejected` synchrone** à traiter côté
connecteur. Tout `2xx` est un succès ; seuls une erreur de transport ou un
`status: "error"` dans le corps interrompent la synchronisation.

> Batching : le connecteur découpe le flux en lots de 50 documents (voir
> `SynchProductsToAiSmartTalk::__invoke()` / `postToApi()`).

---

## 2. Exemple complet (produit variable, en promo)

Exemple réaliste d'un t‑shirt avec deux déclinaisons, une remise de 25 %, deux
catégories et des `Money` en centimes (EUR, 2 décimales) :

```json
{
  "type": "product",
  "externalId": "1234",
  "title": "T-shirt col rond en coton bio",
  "description": "T-shirt unisexe en coton biologique 180 g/m², coupe droite.",
  "descriptionShort": "T-shirt coton bio, coupe droite.",
  "reference": "TSHIRT-BIO",
  "brand": "Acme",
  "attributes": [
    { "name": "Matériau", "value": "Coton biologique" },
    { "name": "Coupe", "value": "Droite" }
  ],
  "price": { "amount": 1500, "currency": "EUR", "display": "15.00 €", "decimals": 2 },
  "availability": "in_stock",
  "quantity": 42,
  "restockDate": null,
  "categories": [
    { "externalId": "3", "name": "Vêtements", "parentExternalId": null },
    { "externalId": "8", "name": "Homme", "parentExternalId": "3" }
  ],
  "defaultCategoryExternalId": "8",
  "url": "https://myshop.com/1234-t-shirt-bio.html",
  "image": "https://myshop.com/img/p/1234-large.jpg",
  "originalPrice": { "amount": 2000, "currency": "EUR", "display": "20.00 €", "decimals": 2 },
  "discountPercent": 25,
  "discountAmount": { "amount": 500, "currency": "EUR", "display": "5.00 €", "decimals": 2 },
  "discountType": "percentage",
  "variants": [
    {
      "externalId": "42",
      "sku": "TSHIRT-BIO-M",
      "gtin": "1234567890123",
      "price": { "amount": 1500, "currency": "EUR", "display": "15.00 €", "decimals": 2 },
      "image": "https://myshop.com/img/p/1234-v42-large.jpg",
      "attributes": [ { "name": "Taille", "value": "M" } ],
      "availability": "in_stock",
      "quantity": 30
    },
    {
      "externalId": "43",
      "sku": "TSHIRT-BIO-L",
      "gtin": "1234567890130",
      "price": { "amount": 1500, "currency": "EUR", "display": "15.00 €", "decimals": 2 },
      "image": "https://myshop.com/img/p/1234-v43-large.jpg",
      "attributes": [ { "name": "Taille", "value": "L" } ],
      "availability": "in_stock",
      "quantity": 12
    }
  ]
}
```

> Les clés `originalPrice`, `discountPercent`, `discountAmount`, `discountType`
> **n'apparaissent que si le produit est en promo** (`priceInfo->hasDiscount`).
> Un produit simple, non remisé, émet `"variants": []` et aucune clé de remise.

---

## 3. Document produit — champ par champ

Réf. : `CanonicalProductMapper::map()`. **Toutes les clés produit ci-dessous sont
toujours présentes dans le JSON** : la colonne « Oblig. » indique si la valeur est
garantie non-nulle (`oui`) ou si elle peut valoir `null` quand la donnée est vide
(`null ok`). Contrairement aux variants, aucune clé produit n'est retirée.

| Champ                       | Type              | Oblig. | Source PrestaShop / règle                                                                                  |
| --------------------------- | ----------------- | :----: | ---------------------------------------------------------------------------------------------------------- |
| `type`                      | string            |  oui   | Constante `"product"`.                                                                                     |
| `externalId`                | string            |  oui   | `id_product` (casté en string).                                                                            |
| `title`                     | string            |  oui   | `product.name`.                                                                                            |
| `description`               | string \| null    | null ok | `product.description`, **HTML strippé** (`strip_tags`). `null` si vide.                                    |
| `descriptionShort`          | string \| null    | null ok | `product.description_short`, HTML strippé. `null` si vide.                                                 |
| `reference`                 | string \| null    | null ok | `product.reference` (SKU parent). `null` si vide.                                                          |
| `brand`                     | string \| null    | null ok | `Manufacturer::getNameById(id_manufacturer)`. `null` si absent.                                            |
| `attributes`                | array `{name,value}` | oui | **Features PrestaShop** produit (Matériau, Style…) via `productFeatures()`. `[]` si aucune. Facetables même sur un produit simple. |
| `price`                     | `Money`           |  oui   | `priceInfo->finalPrice` (prix final TTC) → `Money`. Voir §4.                                               |
| `availability`              | enum string       |  oui   | `"in_stock"` si `quantity > 0`, sinon `"out_of_stock"` (`availability()`).                                 |
| `quantity`                  | int               |  oui   | Stock total parent : `StockAvailable::getQuantityAvailableByProduct`, sommé sur les boutiques résolues.    |
| `restockDate`               | string \| null    |  oui   | `null` si en stock ; sinon date de réappro `available_date` normalisée `YYYY-MM-DD` (voir §5). Émis même à `null`. |
| `categories`                | array `CategoryRef` | oui  | `productCategories()` → objets `CategoryRef` (§6). `[]` si aucune.                                          |
| `defaultCategoryExternalId` | string \| null    |  oui   | `id_category_default` (string) si `> 1`, sinon `null` (Root virtuel). Catégorie principale du produit.     |
| `url`                       | string \| null    | null ok | Lien produit (`Link::getProductLink`). `null` si vide.                                                     |
| `image`                     | string \| null    | null ok | URL de l'image de couverture. `null` si absente.                                                           |
| `variants`                  | array `ProductVariant` | oui | Déclinaisons (`CombinationHelper::getVariants`) → variants canoniques (§3.1). `[]` si produit simple.   |

### Champs de remise (produit)

Présents **uniquement si `priceInfo->hasDiscount`** :

| Champ             | Type            | Source                                                                 |
| ----------------- | --------------- | ---------------------------------------------------------------------- |
| `originalPrice`   | `Money`         | `priceInfo->originalPrice` (prix barré, avant remise).                 |
| `discountPercent` | int             | `priceInfo->discountPercent`.                                          |
| `discountAmount`  | `Money`         | `priceInfo->discountAmount` (montant de la remise).                    |
| `discountType`    | enum \| null    | `"percentage"` \| `"amount"` \| `null` (`discountType()` — tout autre label PrestaShop, ex. `computed`/`none`, devient `null`). |

### 3.1. `ProductVariant`

Réf. : `CanonicalProductMapper::mapVariant()`. Une combinaison PrestaShop devient
un variant ; le backend développe chaque variant en un document produit
indépendamment filtrable. Les optionnels null sont omis ; `externalId`,
`attributes`, `availability`, `quantity` restent toujours présents.

| Champ          | Type              | Oblig. | Source PrestaShop / règle                                                      |
| -------------- | ----------------- | :----: | ------------------------------------------------------------------------------ |
| `externalId`   | string            |  oui   | `id_product_attribute` (casté en string).                                      |
| `sku`          | string            |  non   | `reference` de la combinaison. Omis si vide.                                    |
| `gtin`         | string            |  non   | `ean13`, **fallback `upc`** si l'ean13 est vide. Omis si les deux sont vides.  |
| `price`        | `Money`           |  non\* | Prix de la combinaison → `Money`. Omis si non calculable.                       |
| `image`        | string            |  non   | URL de l'image de la combinaison. Omis si absente.                             |
| `attributes`   | array `{name,value}` | oui | Attributs de combinaison (Taille, Couleur…) — `{group,value}` renommé `{name,value}`. `[]` si aucun. |
| `availability` | enum string       |  oui   | Idem produit, dérivé de la `quantity` du variant.                              |
| `quantity`     | int               |  oui   | Stock de la combinaison.                                                        |

\* `price` est optionnel au niveau du schéma (omis si `toMoney` renvoie `null`),
mais en pratique toujours renseigné pour une combinaison valide.

Champs de remise du variant (présents seulement si la combinaison a un
`original_price`) : `originalPrice` (`Money`), `discountPercent` (int),
`discountAmount` (`Money`), `discountType` (`"percentage"` \| `"amount"` \|
`null`) — mêmes règles qu'au niveau produit.

---

## 4. Objet `Money`

Réf. : `CanonicalProductMapper::toMoney()`. **Tout montant** (price, originalPrice,
discountAmount, au niveau produit comme variant) est un objet `Money`, jamais un
string ni un float.

```jsonc
{
  "amount": 1999,        // int — unités MINEURES (centimes) : 19,99 € => 1999
  "currency": "EUR",     // code ISO-4217, majuscules
  "display": "19.99 €",  // chaîne prête à afficher (format + éventuel signe)
  "decimals": 2          // précision de la devise : EUR=2, JPY=0, LYD=3…
}
```

| Champ      | Type   | Règle                                                                                              |
| ---------- | ------ | -------------------------------------------------------------------------------------------------- |
| `amount`   | int    | `round(montant × 10^decimals)`. **Entier en unités mineures** — permet au backend de filtrer/trier par prix sans ambiguïté de virgule flottante. |
| `currency` | string | Code ISO‑4217, `strtoupper(trim())`.                                                               |
| `display`  | string | Montant formaté (`PriceFormatter::format`) + signe de devise éventuel.                             |
| `decimals` | int    | Précision de la devise, pour reformater `amount` → affichage localisé côté backend sans re‑déduction. |

`toMoney()` renvoie `null` (⇒ champ omis) si le montant est `null`, `''` ou non
numérique. La précision `decimals` gère les devises non décimales : `12,500 LYD`
⇒ `amount = 12500`, `decimals = 3`.

---

## 5. Disponibilité & `restockDate`

Réf. : `CanonicalProductMapper::availability()` + `StockStatusHelper`.

- `availability` est piloté **uniquement par la quantité** : `quantity > 0` ⇒
  `"in_stock"`, sinon `"out_of_stock"`.
- `restockDate` n'a de sens **que sur un produit en rupture** : pour un produit en
  stock il vaut toujours `null` (rien à réapprovisionner).
- `restockDate` est normalisé en **jour ISO‑8601 nu (`YYYY-MM-DD`)** ou `null`.
  Toute valeur vide, date « zéro » (`""`, `0000-00-00`, datetime nul) ou date
  calendaire impossible est ramenée à `null` (`StockStatusHelper::normalizeDate` —
  jamais de `"0000-00-00"` dans le payload). Un composant horaire éventuel est
  tronqué au jour.

| Cas                | `quantity` | `available_date` | → `availability` | → `restockDate` |
| ------------------ | ---------- | ---------------- | ---------------- | --------------- |
| En stock           | `12`       | n'importe        | `in_stock`       | `null`          |
| Rupture, sans date | `0`        | `null` / zéro    | `out_of_stock`   | `null`          |
| Rupture, avec date | `0`        | `2026-07-01`     | `out_of_stock`   | `2026-07-01`    |

`StockStatusHelper::normalize()` produit la même logique sous forme d'un bloc
`{status, quantity, restock_date}` unitairement testable et portable ; les autres
connecteurs doivent en reproduire le comportement à l'identique.

---

## 6. Objet `CategoryRef`

Réf. : `CanonicalProductMapper::productCategories()` / `rowsToCategoryRefs()`.
Chaque entrée de `categories[]` est un `CategoryRef` : le backend peut ainsi
upserter les entités Category, reconstruire la hiérarchie et auto‑attacher le
produit.

```jsonc
{
  "externalId": "8",         // id_category (string)
  "name": "Homme",           // nom de la catégorie dans la langue par défaut
  "parentExternalId": "3"    // id du parent, ou null si le parent est le Root virtuel
}
```

| Champ              | Type           | Règle                                                                                      |
| ------------------ | -------------- | ------------------------------------------------------------------------------------------ |
| `externalId`       | string         | `id_category`.                                                                             |
| `name`             | string         | Nom localisé (langue par défaut). Les entrées sans nom sont ignorées.                      |
| `parentExternalId` | string \| null | `id_parent` en string, ou `null` si le parent est le **Root virtuel** (id 1), qu'on n'émet jamais. |

Le **Root virtuel** PrestaShop (id 1) est exclu (aucune valeur métier). La vraie
racine boutique (ex. « Accueil ») est conservée et émise avec
`parentExternalId = null` pour ancrer l'arbre. `defaultCategoryExternalId`
(niveau produit) pointe la catégorie principale (`id_category_default`) : le
backend l'utilise plutôt que la première entrée de `categories`, souvent la
racine boutique.

> Rétro‑compatibilité : `map()` tolère encore des catégories fournies sous forme
> de simples chaînes de nom (`filterCategoryRefs()` les normalise en
> `{name}`), pour les anciens points d'appel — mais le chemin nominal PrestaShop
> émet toujours des `CategoryRef` complets.

---

## 7. Mapping par plateforme (à répliquer)

Les autres connecteurs alimentent le même schéma depuis leurs sources natives :

| Plateforme  | `quantity`                                                                | `restockDate` (source)                             |
| ----------- | ------------------------------------------------------------------------- | -------------------------------------------------- |
| PrestaShop  | `StockAvailable::getQuantityAvailableByProduct`, sommé sur les boutiques  | `product.available_date`                           |
| WooCommerce | `get_stock_quantity()` (ou `is_in_stock()` si non suivi ⇒ dériver l'enum) | méta de réappro (ex. `_restock_date`) / champ custom |
| Shopify     | `inventory_quantity` (somme des `InventoryLevel`)                         | métafield (ex. `custom.restock_date`)              |
| Joomla      | champ stock du composant (HikaShop / VirtueMart)                          | champ de disponibilité du composant                |

> Quand une plateforme ne suit pas le stock, dériver `availability` de
> l'indicateur booléen natif de disponibilité.

---

## 8. Compatibilité & versionnage

- Le schéma est validé côté backend par un **Zod `.passthrough()`** : les champs
  additifs inconnus sont ignorés, l'ancien payload reste valide.
- Toute évolution **incompatible** incrémente `payloadVersion` (en tête de ce
  document et dans `postToApi()`) et doit être répercutée dans
  `tests/CanonicalProductMapperTest.php`.
- Le contrat est figé par les tests unitaires du mapper ; ce document les décrit
  mais **le code reste la source de vérité**.
