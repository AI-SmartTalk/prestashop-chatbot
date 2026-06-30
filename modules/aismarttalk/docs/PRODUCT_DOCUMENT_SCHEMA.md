# AI SmartTalk — Standard du document « produit »

> Version du schéma : **1** · Statut : actif · Dernière mise à jour : 2026‑06‑01 (DEV‑857)

Ce document définit le **format unifié des documents de type produit** envoyés à
AI SmartTalk par tous les connecteurs e‑commerce (PrestaShop, WooCommerce,
Shopify, Joomla, …). L'objectif : que le **tool de search** et le **LLM**
raisonnent sur un produit de manière identique quelle que soit la plateforme
source, et que l'on puisse construire des actions et des recherches avancées
(filtrer par disponibilité, proposer une date de réappro, etc.) sur une base
commune.

Chaque connecteur **doit produire la même forme**. Un connecteur qui ne sait pas
renseigner un champ optionnel doit envoyer `null` (jamais une valeur factice).

---

## 1. Endpoint

**Point d'entrée unique, commun à tous les connecteurs** (pas de route par plateforme) :

`POST /api/v1/products`

Auth : en-têtes `Authorization: Bearer <token>` + `x-chat-model-id` (le chatModel est
résolu côté backend ; il n'est pas répété dans le body). La plateforme est dans le
body via `source` ∈ `prestashop | woocommerce | shopify | joomla`.

```jsonc
{
  "payloadVersion": "1",
  "source": "prestashop",
  "siteIdentifier": "…",
  "documents": [ /* tableau de documents produit, max 50 par batch */ ]
}
```

Le backend répond `200` (`ok`), `207` (`partial`, certains produits rejetés par la
validation canonique — la liste est dans `rejected`), ou `4xx/5xx` (`error`).

Nettoyage des produits retirés : `POST /api/v1/products/cleanup` avec
`{ "source": "prestashop", "siteIdentifier": "…", "mode": "delete-ids" | "keep-only", "externalIds": [ … ] }`.

## 2. Document produit

```jsonc
{
  // — Identité —
  "id": 42,                       // identifiant produit dans la plateforme source
  "title": "T‑shirt bleu",
  "description": "…",             // HTML strippé
  "description_short": "…",       // HTML strippé
  "reference": "TSHIRT-42",       // SKU / référence, ou null

  // — Prix —
  "price": "29.99",               // prix final TTC, string (préserve les décimales)
  "price_decimals": 2,
  "currency": "EUR",
  "currency_sign": "€",
  "original_price": "39.99",      // présent uniquement si remise, sinon null
  "discount_percent": 25,         // sinon null
  "discount_amount": "10.00",     // sinon null
  "discount_type": "percentage",  // "percentage" | "amount" | null
  "has_special_price": true,
  "price_from": "2026-06-01",     // début de promo, ou null
  "price_to": "2026-06-30",       // fin de promo, ou null

  // — Liens / média —
  "url": "https://…",
  "image_url": "https://…",       // ou null

  // — Déclinaisons —
  "variants": [ /* … */ ],        // [] si produit simple

  // — Stock (bloc unifié, ajouté en v1) —
  "stock": {
    "status": "out_of_stock",     // "in_stock" | "out_of_stock"   (REQUIS)
    "quantity": 0,                // entier, ou null si non suivi par la plateforme
    "restock_date": "2026-06-15"  // date ISO‑8601 "YYYY-MM-DD", ou null
  }
}
```

---

## 3. Bloc `stock` — règles de normalisation

C'est le cœur du besoin Dotit (DEV‑857). Règles **identiques pour tous les
connecteurs** :

| Champ          | Type            | Règle                                                                                 |
| -------------- | --------------- | ------------------------------------------------------------------------------------- |
| `status`       | string (enum)   | `quantity > 0` ⇒ `in_stock`, sinon `out_of_stock`. Toujours présent.                  |
| `quantity`     | int \| null     | Quantité physique totale. `null` si la plateforme ne suit pas le stock.               |
| `restock_date` | string \| null  | Date de réappro **uniquement quand `out_of_stock`**. Format `YYYY-MM-DD`. Sinon `null`. |

Précisions :

- `restock_date` est **toujours `null` quand le produit est `in_stock`** : il n'y
  a rien à réapprovisionner.
- Toute date « zéro » / vide / non parsable (`""`, `0000-00-00`, datetime nul,
  date calendaire impossible) doit être normalisée en `null` — jamais de
  `"0000-00-00"` dans le payload.
- Un éventuel composant horaire est tronqué : on n'expose qu'un **jour** ISO.

### Implémentation de référence

`StockStatusHelper::normalize($quantity, $availableDate)` (PrestaShop) est
l'implémentation de référence ; les autres connecteurs doivent reproduire son
comportement à l'identique. Les 3 cas d'acceptation sont figés dans
`tests/StockStatusHelperTest.php` et `tests/ApiContractTest.php`.

| Cas                       | `quantity` | `available_date` | → `status`     | → `restock_date` |
| ------------------------- | ---------- | ---------------- | -------------- | ---------------- |
| En stock                  | `12`       | n'importe        | `in_stock`     | `null`           |
| Rupture, sans date        | `0`        | `null` / zéro    | `out_of_stock` | `null`           |
| Rupture, avec date        | `0`        | `2026-06-15`     | `out_of_stock` | `2026-06-15`     |

---

## 4. Mapping par plateforme

Comment chaque connecteur alimente le bloc `stock` :

| Plateforme  | `quantity`                                                              | `restock_date` (source)                                |
| ----------- | ---------------------------------------------------------------------- | ------------------------------------------------------ |
| PrestaShop  | `StockAvailable::getQuantityAvailableByProduct` sommé sur tous les shops | `product.available_date`                               |
| WooCommerce | `get_stock_quantity()` (ou `is_in_stock()` si non suivi ⇒ `quantity=null`) | méta de réappro (ex. `_restock_date`) / champ custom    |
| Shopify     | `inventory_quantity` (somme des `InventoryLevel`)                      | métafield (ex. `custom.restock_date`)                  |
| Joomla      | dépend du composant e‑commerce (HikaShop / VirtueMart : champ stock)   | champ de disponibilité du composant                    |

> Quand une plateforme ne suit pas le stock, envoyer `quantity: null` **et**
> dériver `status` depuis l'indicateur booléen de disponibilité natif.

---

## 5. Prompt système (côté backend AI SmartTalk)

Le prompt système doit exploiter le bloc `stock` pour formuler la réponse :

- `stock.status == "out_of_stock"` **et** `stock.restock_date != null` →
  « Indisponible actuellement, réappro prévu le {restock_date}. »
- `stock.status == "out_of_stock"` **et** `stock.restock_date == null` →
  « Indisponible actuellement (pas de date de réapprovisionnement communiquée). »
- `stock.status == "in_stock"` → comportement nominal.

> ⚠️ À implémenter dans le repo backend `aismarttalk` (hors de ce module) — non
> couvert par ce connecteur.

---

## 6. Compatibilité & versionnage

- Le bloc `stock` est **additif** : les connecteurs et le backend non encore mis à
  jour ignorent les champs inconnus, l'ancien payload reste valide.
- Toute évolution incompatible incrémente la « version du schéma » en tête de ce
  document et doit être répercutée dans `tests/ApiContractTest.php`.
