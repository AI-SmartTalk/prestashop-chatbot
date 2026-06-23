<?php
/**
 * Quality product seeder for AI SmartTalk module testing.
 *
 * Unlike a random word-salad generator, this builds a COHERENT catalog from a
 * library of real product archetypes: each product sits in the right category,
 * carries a plausible brand, relevant variant axes, a realistic price, and a
 * RICH description that actually talks about the item (materials, key features,
 * intended use, and live stock state) — so it's meaningful to test the LLM /
 * RAG / product search against.
 *
 * It still covers the whole spectrum needed to stress the sync end to end:
 * simple AND multi-variant products, full price range, promotions, in-stock /
 * out-of-stock (+ restock dates), and a VARIED default category per product
 * (exercises id_category_default).
 *
 * Runs INSIDE the PrestaShop container (it boots the PS core):
 *   docker exec prestashop php modules/aismarttalk/tools/seed_products.php [options]
 *
 * Or via Makefile:
 *   make seed-products                 # generate (default 10000)
 *   make seed-products COUNT=50000     # bigger run
 *   make seed-products-purge           # remove EVERYTHING this seeder created
 *
 * Options (CLI flags; Makefile maps COUNT=… → --count=…):
 *   --count=N          number of products            (default 10000)
 *   --variant-ratio=F  fraction with combinations    (default 0.45)
 *   --oos-ratio=F      fraction out of stock         (default 0.18)
 *   --promo-ratio=F    fraction with a discount      (default 0.25)
 *   --no-brand-ratio=F fraction with no manufacturer (default 0.08)
 *   --batch=N          products per DB transaction   (default 200)
 *   --seed=N           PRNG seed for reproducibility (default 1337)
 *   --reindex          rebuild the search index at the end (slow; off by default)
 *   --purge            delete everything previously seeded, then exit
 *
 * Idempotency / safety: every entity is tagged so --purge removes EXACTLY what
 * this script created and nothing else:
 *   - products      → reference 'AISEED_<n>'
 *   - brands        → manufacturer short_description marker 'AISEED_SEED'
 *   - attr. groups  → internal name 'AISeed: <name>' (public name stays clean)
 *   - categories    → all descendants of the single root 'AI Seed Catalog'
 */

// ─── Bootstrap PrestaShop ────────────────────────────────────────────────────
$psRoot = '/var/www/html';
if (!file_exists($psRoot . '/config/config.inc.php')) {
    fwrite(STDERR, "ERROR: PrestaShop not found at $psRoot (run inside the container).\n");
    exit(1);
}
// Surface real errors but mute the deprecation/notice noise the bundled PS
// modules (ps_facebook, ps_accounts, ps_mbo…) spray on every CLI boot.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_STRICT);
ini_set('display_errors', '1');
ini_set('memory_limit', '-1');
set_time_limit(0);
require_once $psRoot . '/config/config.inc.php';

// ─── Markers (used by both generate and purge) ──────────────────────────────
const SEED_REF_PREFIX  = 'AISEED_';
const SEED_BRAND_MARK   = 'AISEED_SEED';
const SEED_ATTR_PREFIX  = 'AISeed: ';
const SEED_ROOT_CAT     = 'AI Seed Catalog';

// ─── Options ─────────────────────────────────────────────────────────────────
$opt = static function (string $name, $default) use ($argv) {
    foreach ($argv as $a) {
        if ($a === "--$name") {
            return true;
        }
        if (strpos($a, "--$name=") === 0) {
            return substr($a, strlen("--$name="));
        }
    }
    return $default;
};

$COUNT         = max(1, (int) $opt('count', 10000));
$VARIANT_RATIO = (float) $opt('variant-ratio', 0.45);
$OOS_RATIO     = (float) $opt('oos-ratio', 0.18);
$PROMO_RATIO   = (float) $opt('promo-ratio', 0.25);
$NOBRAND_RATIO = (float) $opt('no-brand-ratio', 0.08);
$BATCH         = max(1, (int) $opt('batch', 200));
$SEED          = (int) $opt('seed', 1337);
$REINDEX       = (bool) $opt('reindex', false);
$PURGE         = (bool) $opt('purge', false);

mt_srand($SEED);

// ─── Context ─────────────────────────────────────────────────────────────────
$idLang    = (int) Configuration::get('PS_LANG_DEFAULT');
$idShop    = (int) Configuration::get('PS_SHOP_DEFAULT');
$homeCatId = (int) Configuration::get('PS_HOME_CATEGORY');
$languages = Language::getLanguages(false);
Shop::setContext(Shop::CONTEXT_SHOP, $idShop);

function multilang($value, array $languages): array
{
    $out = [];
    foreach ($languages as $l) {
        $out[(int) $l['id_lang']] = $value;
    }
    return $out;
}

function slug(string $s): string
{
    $u = Tools::str2url($s);
    return $u !== false && $u !== '' ? $u : 'item';
}

$db = Db::getInstance();

// ════════════════════════════════════════════════════════════════════════════
// PURGE
// ════════════════════════════════════════════════════════════════════════════
if ($PURGE) {
    echo "\033[1m=== AI SmartTalk seeder — PURGE ===\033[0m\n";
    $p = _DB_PREFIX_;

    // Bulk, set-based deletes scoped by the seed reference — deleting thousands
    // of products one-by-one via ObjectModel is slow and heavy enough to knock
    // over the dev MySQL. The marker LIKE keeps it surgical.
    $like = "pr.reference LIKE '" . SEED_REF_PREFIX . "%'";
    $count = (int) $db->getValue(
        "SELECT COUNT(*) FROM {$p}product pr WHERE pr.reference LIKE '" . SEED_REF_PREFIX . "%'"
    );
    echo "Products to delete: $count\n";
    $joins = [
        "DELETE pac FROM {$p}product_attribute_combination pac
           INNER JOIN {$p}product_attribute pa ON pa.id_product_attribute = pac.id_product_attribute
           INNER JOIN {$p}product pr ON pr.id_product = pa.id_product WHERE $like",
        "DELETE pas FROM {$p}product_attribute_shop pas
           INNER JOIN {$p}product pr ON pr.id_product = pas.id_product WHERE $like",
        "DELETE pa FROM {$p}product_attribute pa
           INNER JOIN {$p}product pr ON pr.id_product = pa.id_product WHERE $like",
        "DELETE sa FROM {$p}stock_available sa
           INNER JOIN {$p}product pr ON pr.id_product = sa.id_product WHERE $like",
        "DELETE sp FROM {$p}specific_price sp
           INNER JOIN {$p}product pr ON pr.id_product = sp.id_product WHERE $like",
        "DELETE cp FROM {$p}category_product cp
           INNER JOIN {$p}product pr ON pr.id_product = cp.id_product WHERE $like",
        "DELETE pl FROM {$p}product_lang pl
           INNER JOIN {$p}product pr ON pr.id_product = pl.id_product WHERE $like",
        "DELETE ps FROM {$p}product_shop ps
           INNER JOIN {$p}product pr ON pr.id_product = ps.id_product WHERE $like",
        "DELETE pr FROM {$p}product pr WHERE $like",
    ];
    foreach ($joins as $sql) {
        $db->execute($sql);
    }

    $grpIds = $db->executeS(
        "SELECT DISTINCT id_attribute_group FROM {$p}attribute_group_lang
         WHERE name LIKE '" . SEED_ATTR_PREFIX . "%'"
    ) ?: [];
    foreach ($grpIds as $row) {
        (new AttributeGroup((int) $row['id_attribute_group']))->delete();
    }
    echo 'Attribute groups deleted: ' . count($grpIds) . "\n";

    $brandIds = $db->executeS(
        "SELECT DISTINCT id_manufacturer FROM {$p}manufacturer_lang
         WHERE short_description = '" . SEED_BRAND_MARK . "'"
    ) ?: [];
    foreach ($brandIds as $row) {
        (new Manufacturer((int) $row['id_manufacturer']))->delete();
    }
    echo 'Brands deleted: ' . count($brandIds) . "\n";

    $rootRow = $db->getRow(
        "SELECT c.id_category FROM {$p}category c
         INNER JOIN {$p}category_lang cl ON cl.id_category = c.id_category
         WHERE cl.name = '" . pSQL(SEED_ROOT_CAT) . "'"
    );
    if ($rootRow) {
        (new Category((int) $rootRow['id_category']))->delete();
        echo "Category tree deleted (root #{$rootRow['id_category']}).\n";
    }

    // Deleting the category tree (above) can leave category_product rows pointing
    // at categories that no longer exist — and any product still linked ONLY to
    // such a dead category syncs with EMPTY categories (no category/brand/attribute
    // facets). Sweep these dangling links so the shop never carries orphans.
    purgeOrphanCategoryLinks($db, $p);

    echo "\033[32mPurge complete.\033[0m\n";
    exit(0);
}

// ════════════════════════════════════════════════════════════════════════════
// CATALOG DEFINITION — coherent archetypes
// ════════════════════════════════════════════════════════════════════════════

// Attribute axes: each value carries a price impact (€, HT) so combinations have
// coherent pricing (a 1 To variant genuinely costs more than 128 Go).
$attrSpec = [
    'Taille'   => ['type' => 'select', 'values' => [['XS', 0], ['S', 0], ['M', 0], ['L', 0], ['XL', 2], ['XXL', 4]]],
    'Pointure' => ['type' => 'select', 'values' => [['36', 0], ['37', 0], ['38', 0], ['39', 0], ['40', 0], ['41', 0], ['42', 0], ['43', 0], ['44', 0], ['45', 0]]],
    'Couleur'  => ['type' => 'color', 'values' => [
        ['Noir', 0, '#1a1a1a'], ['Blanc', 0, '#f5f5f5'], ['Bleu nuit', 0, '#26334d'],
        ['Vert sauge', 0, '#9caf88'], ['Terracotta', 0, '#c66b3d'], ['Gris perle', 0, '#b8bdc4'],
        ['Beige', 0, '#d8c3a5'], ['Rouge', 0, '#b03030'], ['Rose poudré', 0, '#e8b4bc'], ['Jaune moutarde', 0, '#d4a017'],
    ]],
    'Matière'  => ['type' => 'select', 'values' => [['Coton', 0], ['Lin', 5], ['Polyester recyclé', 0], ['Velours', 8], ['Laine', 12], ['Cuir', 25]]],
    'Capacité' => ['type' => 'radio', 'values' => [['128 Go', 0], ['256 Go', 40], ['512 Go', 120], ['1 To', 260], ['2 To', 520]]],
];

// Tone pools per "kind" — the connective tissue that makes a description read
// like marketing copy for THAT family of product.
$KIND = [
    'fashion'   => ['intro' => ['une pièce essentielle du vestiaire', 'un indispensable qui traverse les saisons', 'l’alliance du confort et du style'], 'use' => ['le quotidien comme les occasions', 'un look casual chic', 'se composer facilement avec le reste du dressing'], 'series' => ['Héritage', 'Atelier', 'Saison', 'Origine', 'Capsule', 'Signature', 'Maison'], 'suffix' => ['Classic', 'Slim', 'Confort', 'Oversize', 'Premium', 'Édition']],
    'footwear'  => ['intro' => ['un modèle pensé pour le mouvement', 'le juste équilibre entre maintien et légèreté', 'une silhouette intemporelle'], 'use' => ['la ville comme les longues marches', 'un usage quotidien', 'allier confort et allure'], 'series' => ['Run', 'Trail', 'Street', 'Origine', 'Glide', 'Terra'], 'suffix' => ['Pro', 'Lite', '2.0', 'GTX', 'Confort']],
    'tech'      => ['intro' => ['la technologie au service de l’essentiel', 'des performances pensées pour durer', 'une expérience fluide et sans compromis'], 'use' => ['le travail comme les loisirs', 'rester connecté partout', 'un usage intensif au quotidien'], 'series' => ['Nova', 'Pulse', 'Edge', 'Prime', 'Air', 'Vertex', 'Flux'], 'suffix' => ['Pro', 'Max', 'Lite', 'Plus', 'SE', '2024']],
    'computing' => ['intro' => ['la puissance dans un format maîtrisé', 'conçu pour la productivité', 'fiabilité et performances réunies'], 'use' => ['le télétravail comme la création', 'enchaîner les tâches sans ralentir', 'un setup efficace'], 'series' => ['Core', 'Studio', 'Vertex', 'Prime', 'Air'], 'suffix' => ['Pro', 'Max', 'Ultra', '14', '15']],
    'audio'     => ['intro' => ['un son riche et immersif', 'l’écoute sans distraction', 'la liberté du sans-fil'], 'use' => ['la musique, les appels et le voyage', 's’isoler dans les transports', 'profiter d’un son détaillé'], 'series' => ['Pulse', 'Wave', 'Studio', 'Air', 'Echo'], 'suffix' => ['Pro', 'ANC', 'Lite', '2']],
    'home_kitchen' => ['intro' => ['un allié du quotidien en cuisine', 'la robustesse au service du plaisir de cuisiner', 'pensé pour durer'], 'use' => ['une cuisine du quotidien', 'recevoir comme cuisiner pour soi', 'gagner du temps aux fourneaux'], 'series' => ['Chef', 'Maison', 'Pro', 'Origine', 'Essentiel'], 'suffix' => ['Induction', 'Premium', 'Set', 'Duo']],
    'home_deco' => ['intro' => ['une touche déco qui réchauffe l’intérieur', 'le détail qui change une pièce', 'l’élégance discrète au quotidien'], 'use' => ['habiller un salon ou une chambre', 'créer une ambiance chaleureuse', 'apporter du caractère à la pièce'], 'series' => ['Cosy', 'Nature', 'Bohème', 'Éclat', 'Sérénité'], 'suffix' => ['Deluxe', 'XL', 'Duo', 'Édition']],
    'home_bedding' => ['intro' => ['des nuits douces et confortables', 'la promesse d’un sommeil réparateur', 'le confort au coucher'], 'use' => ['toutes les saisons', 'une chambre cocooning', 'un couchage confortable'], 'series' => ['Nuit', 'Cocon', 'Pure', 'Douceur'], 'suffix' => ['240x220', 'Premium', 'Set']],
    'beauty'    => ['intro' => ['un soin pensé pour la peau', 'l’efficacité au naturel', 'une routine simple et sensorielle'], 'use' => ['une routine matin et soir', 'tous les types de peau', 'prendre soin de soi au quotidien'], 'series' => ['Éclat', 'Hydra', 'Pure', 'Velours', 'Botanic'], 'suffix' => ['Intense', 'Sensitive', 'Bio', '24h']],
    'sport'     => ['intro' => ['l’équipement qui accompagne l’effort', 'pensé pour la performance', 'la motivation au rendez-vous'], 'use' => ['l’entraînement à la maison comme en extérieur', 'progresser à son rythme', 'rester actif'], 'series' => ['Active', 'Endurance', 'Power', 'Boost', 'Flow'], 'suffix' => ['Pro', 'Train', 'Light', 'Race']],
    'stationery' => ['intro' => ['le plaisir d’écrire au quotidien', 'l’essentiel du bureau bien pensé', 'organiser ses idées avec style'], 'use' => ['la prise de notes comme le journaling', 'le bureau ou les études', 'organiser son quotidien'], 'series' => ['Atelier', 'Note', 'Studio', 'Origine'], 'suffix' => ['A5', 'A4', 'Dot', 'Édition']],
    'toys'      => ['intro' => ['des heures de jeu et d’imagination', 'le jeu qui fait grandir', 'amuser et éveiller'], 'use' => ['jouer seul ou en famille', 'éveiller la curiosité', 'partager un bon moment'], 'series' => ['Junior', 'Magic', 'Discovery', 'Fun'], 'suffix' => ['Maxi', 'Mini', 'Deluxe']],
    'food'      => ['intro' => ['le goût du vrai', 'une sélection soignée', 'le plaisir des bons produits'], 'use' => ['un quotidien gourmand', 'recevoir ou se faire plaisir', 'composer de bons moments'], 'series' => ['Terroir', 'Origine', 'Sélection', 'Artisan'], 'suffix' => ['Grand Cru', 'Réserve', 'Bio']],
];

// kind → which brand pool feeds it.
$BRAND_POOL_OF = [
    'fashion' => 'fashion', 'footwear' => 'fashion',
    'tech' => 'tech', 'computing' => 'tech', 'audio' => 'tech',
    'home_kitchen' => 'home', 'home_deco' => 'home', 'home_bedding' => 'home',
    'beauty' => 'beauty', 'sport' => 'sport', 'stationery' => 'office',
    'toys' => 'office', 'food' => 'food',
];
$BRAND_POOLS = [
    'fashion' => ['Maison Lou', 'Brindille', 'Côme', 'Augustin', 'Marée', 'Trame', 'Filature', 'Garance', 'Velinne', 'Lézard'],
    'tech'    => ['Volt', 'Nexus', 'Kairo', 'Lumen', 'Pulsar', 'Orbit', 'Zentek', 'Hertz', 'Cobalt', 'Quanta'],
    'home'    => ['Foyer', 'Terracotta', 'Lin & Co', 'Cosy Home', 'Bastide', 'Aubépine', 'Céladon', 'Maison Sève'],
    'beauty'  => ['Botania', 'Éclatine', 'Pureté', 'Velmé', 'Aurélia', 'Hédoné'],
    'sport'   => ['Kineo', 'Endur', 'Trailix', 'Aerow', 'Forza', 'Vélox'],
    'office'  => ['Papelo', 'Encrier', 'Castor', 'Pixie', 'Crayonne', 'Ludo'],
    'food'    => ['Maison Pélican', 'Terroir & Cie', 'Sélection Roux', 'Bio Champs', 'L’Artisan'],
];

// The archetype library. price in € HT [min,max]; axes drive combinations.
$ARCHETYPES = [
    // ── Mode ──
    ['base' => 'T-shirt en coton bio', 'path' => ['Mode', 'Homme', 'T-shirts'], 'price' => [12, 35], 'axes' => ['Taille', 'Couleur'], 'kind' => 'fashion', 'materials' => ['coton bio 180 g/m²', 'jersey peigné', 'coton-modal'], 'features' => ['coupe regular', 'col rond renforcé', 'sans étiquette grattante', 'certifié OEKO-TEX']],
    ['base' => 'Chemise en lin', 'path' => ['Mode', 'Homme', 'Chemises'], 'price' => [35, 89], 'axes' => ['Taille', 'Couleur'], 'kind' => 'fashion', 'materials' => ['lin lavé', 'lin-coton'], 'features' => ['boutons en nacre', 'coupe droite', 'matière respirante', 'col français']],
    ['base' => 'Jean slim', 'path' => ['Mode', 'Homme', 'Jeans'], 'price' => [39, 110], 'axes' => ['Taille', 'Couleur'], 'kind' => 'fashion', 'materials' => ['denim stretch 11 oz', 'denim brut'], 'features' => ['5 poches', 'stretch confort', 'délavage moyen', 'coupe ajustée']],
    ['base' => 'Sweat à capuche', 'path' => ['Mode', 'Homme', 'T-shirts'], 'price' => [29, 79], 'axes' => ['Taille', 'Couleur'], 'kind' => 'fashion', 'materials' => ['molleton gratté', 'coton bio'], 'features' => ['poche kangourou', 'capuche doublée', 'bords côtelés']],
    ['base' => 'Robe portefeuille', 'path' => ['Mode', 'Femme', 'Robes'], 'price' => [45, 140], 'axes' => ['Taille', 'Couleur'], 'kind' => 'fashion', 'materials' => ['viscose fluide', 'crêpe'], 'features' => ['cache-cœur', 'manches 3/4', 'ceinture à nouer', 'doublure légère']],
    ['base' => 'Top en maille', 'path' => ['Mode', 'Femme', 'Hauts'], 'price' => [25, 69], 'axes' => ['Taille', 'Couleur'], 'kind' => 'fashion', 'materials' => ['maille côtelée', 'coton-cachemire'], 'features' => ['col montant', 'coupe ajustée', 'maille douce']],
    ['base' => 'Pantalon tailleur', 'path' => ['Mode', 'Femme', 'Pantalons'], 'price' => [49, 129], 'axes' => ['Taille', 'Couleur'], 'kind' => 'fashion', 'materials' => ['gabardine', 'laine froide'], 'features' => ['pli marqué', 'taille haute', 'tombé impeccable']],
    ['base' => 'Pull col rond enfant', 'path' => ['Mode', 'Enfant', 'Garçon'], 'price' => [19, 45], 'axes' => ['Taille', 'Couleur'], 'kind' => 'fashion', 'materials' => ['coton doux', 'laine mérinos'], 'features' => ['lavable en machine', 'bords côtelés', 'maille chaude']],
    ['base' => 'Body bébé manches longues', 'path' => ['Mode', 'Enfant', 'Bébé'], 'price' => [9, 25], 'axes' => ['Taille', 'Couleur'], 'kind' => 'fashion', 'materials' => ['coton bio'], 'features' => ['pressions à l’épaule', 'sans couture irritante', 'lot pratique']],
    ['base' => 'Jupe plissée fille', 'path' => ['Mode', 'Enfant', 'Fille'], 'price' => [19, 49], 'axes' => ['Taille', 'Couleur'], 'kind' => 'fashion', 'materials' => ['polyester recyclé'], 'features' => ['taille élastiquée', 'plis marqués', 'tissu léger']],
    // ── Chaussures ──
    ['base' => 'Baskets de running', 'path' => ['Mode', 'Homme', 'Baskets'], 'price' => [59, 160], 'axes' => ['Pointure', 'Couleur'], 'kind' => 'footwear', 'materials' => ['mesh respirant', 'textile recyclé'], 'features' => ['semelle amortissante', 'drop 8 mm', 'légères (250 g)', 'maintien renforcé']],
    ['base' => 'Sneakers en cuir', 'path' => ['Mode', 'Femme', 'Chaussures'], 'price' => [69, 180], 'axes' => ['Pointure', 'Couleur'], 'kind' => 'footwear', 'materials' => ['cuir pleine fleur', 'daim'], 'features' => ['semelle gomme', 'doublure cuir', 'finitions cousues']],
    ['base' => 'Bottines à lacets', 'path' => ['Mode', 'Femme', 'Chaussures'], 'price' => [79, 190], 'axes' => ['Pointure', 'Couleur'], 'kind' => 'footwear', 'materials' => ['cuir', 'nubuck'], 'features' => ['talon 4 cm', 'zip intérieur', 'semelle crantée']],
    // ── Maison / Cuisine ──
    ['base' => 'Poêle anti-adhésive 28 cm', 'path' => ['Maison', 'Cuisine', 'Ustensiles'], 'price' => [19, 79], 'axes' => [], 'kind' => 'home_kitchen', 'materials' => ['aluminium forgé revêtement pierre', 'inox 18/10'], 'features' => ['compatible induction', 'sans PFOA', 'passe au lave-vaisselle', 'manche ergonomique']],
    ['base' => 'Set de couteaux de cuisine', 'path' => ['Maison', 'Cuisine', 'Ustensiles'], 'price' => [29, 149], 'axes' => [], 'kind' => 'home_kitchen', 'materials' => ['acier inoxydable japonais', 'acier damas'], 'features' => ['lames aiguisées', 'manches ergonomiques', 'bloc en bambou']],
    ['base' => 'Service d’assiettes 6 personnes', 'path' => ['Maison', 'Cuisine', 'Vaisselle'], 'price' => [25, 120], 'axes' => ['Couleur'], 'kind' => 'home_kitchen', 'materials' => ['grès émaillé', 'porcelaine'], 'features' => ['passe au micro-ondes', 'empilable', '18 pièces']],
    ['base' => 'Bouilloire électrique', 'path' => ['Maison', 'Cuisine', 'Électroménager'], 'price' => [25, 89], 'axes' => ['Couleur'], 'kind' => 'home_kitchen', 'materials' => ['inox brossé', 'verre borosilicate'], 'features' => ['capacité 1,7 L', 'arrêt automatique', 'ébullition rapide']],
    // ── Maison / Salon ──
    ['base' => 'Coussin décoratif 40x40', 'path' => ['Maison', 'Salon', 'Décoration'], 'price' => [12, 45], 'axes' => ['Couleur', 'Matière'], 'kind' => 'home_deco', 'materials' => ['velours', 'lin lavé', 'coton tissé'], 'features' => ['housse déhoussable', 'garnissage moelleux', 'fermeture zippée']],
    ['base' => 'Plaid en laine', 'path' => ['Maison', 'Salon', 'Décoration'], 'price' => [29, 99], 'axes' => ['Couleur', 'Matière'], 'kind' => 'home_deco', 'materials' => ['laine', 'laine recyclée', 'coton'], 'features' => ['chaud et doux', 'finitions à franges', 'grand format 130x170']],
    ['base' => 'Lampe à poser', 'path' => ['Maison', 'Salon', 'Luminaires'], 'price' => [29, 149], 'axes' => ['Couleur'], 'kind' => 'home_deco', 'materials' => ['céramique', 'métal laqué', 'bois de chêne'], 'features' => ['abat-jour en tissu', 'douille E27', 'interrupteur sur cordon']],
    ['base' => 'Vase en grès', 'path' => ['Maison', 'Salon', 'Décoration'], 'price' => [15, 69], 'axes' => ['Couleur'], 'kind' => 'home_deco', 'materials' => ['grès', 'terre cuite'], 'features' => ['fait main', 'émail réactif unique', 'forme organique']],
    ['base' => 'Canapé 3 places', 'path' => ['Maison', 'Salon', 'Canapés'], 'price' => [399, 1299], 'axes' => ['Couleur', 'Matière'], 'kind' => 'home_deco', 'materials' => ['tissu velours', 'cuir aniline'], 'features' => ['assise mousse haute résilience', 'pieds en bois massif', 'housses déhoussables']],
    // ── Maison / Chambre ──
    ['base' => 'Parure de lit 240x220', 'path' => ['Maison', 'Chambre', 'Literie'], 'price' => [39, 129], 'axes' => ['Couleur', 'Matière'], 'kind' => 'home_bedding', 'materials' => ['coton percale', 'satin de coton', 'lin lavé'], 'features' => ['housse + 2 taies', 'fermeture à glissière', 'toucher doux']],
    ['base' => 'Oreiller ergonomique', 'path' => ['Maison', 'Chambre', 'Literie'], 'price' => [19, 69], 'axes' => [], 'kind' => 'home_bedding', 'materials' => ['mousse à mémoire de forme', 'fibres respirantes'], 'features' => ['soutien cervical', 'housse lavable', 'maintien progressif']],
    ['base' => 'Boîte de rangement (lot de 2)', 'path' => ['Maison', 'Chambre', 'Rangement'], 'price' => [9, 39], 'axes' => ['Couleur'], 'kind' => 'home_bedding', 'materials' => ['tissu pliable', 'rotin'], 'features' => ['pliable', 'poignées renforcées', 'couvercle inclus']],
    // ── High-Tech ──
    ['base' => 'Smartphone 6,5"', 'path' => ['High-Tech', 'Smartphones', 'Android'], 'price' => [199, 899], 'axes' => ['Capacité', 'Couleur'], 'kind' => 'tech', 'materials' => ['verre et aluminium', 'dos finition mate'], 'features' => ['écran AMOLED 120 Hz', 'triple capteur 50 MP', 'batterie 5000 mAh', 'charge rapide 67 W', 'compatible 5G']],
    ['base' => 'Smartphone compact', 'path' => ['High-Tech', 'Smartphones', 'iOS'], 'price' => [499, 1299], 'axes' => ['Capacité', 'Couleur'], 'kind' => 'tech', 'materials' => ['verre trempé', 'châssis en aluminium'], 'features' => ['puce dernière génération', 'photo computationnelle', 'port USB-C', 'écran haute luminosité']],
    ['base' => 'Coque renforcée', 'path' => ['High-Tech', 'Smartphones', 'Accessoires mobiles'], 'price' => [9, 39], 'axes' => ['Couleur'], 'kind' => 'tech', 'materials' => ['TPU antichoc', 'silicone'], 'features' => ['norme militaire anti-chute', 'bords surélevés', 'compatible recharge sans fil']],
    ['base' => 'Chargeur USB-C 65 W', 'path' => ['High-Tech', 'Smartphones', 'Accessoires mobiles'], 'price' => [15, 49], 'axes' => [], 'kind' => 'tech', 'materials' => ['technologie GaN'], 'features' => ['3 ports', 'charge rapide', 'format compact']],
    ['base' => 'Ordinateur portable 14"', 'path' => ['High-Tech', 'Ordinateurs', 'Portables'], 'price' => [499, 1799], 'axes' => ['Capacité', 'Couleur'], 'kind' => 'computing', 'materials' => ['aluminium unibody'], 'features' => ['16 Go de RAM', 'écran IPS Full HD', 'clavier rétroéclairé', 'autonomie 12 h']],
    ['base' => 'Clavier mécanique', 'path' => ['High-Tech', 'Ordinateurs', 'Périphériques'], 'price' => [39, 159], 'axes' => ['Couleur'], 'kind' => 'computing', 'materials' => ['switches mécaniques', 'châssis en aluminium'], 'features' => ['rétroéclairage RGB', 'connexion sans fil', 'touches PBT']],
    ['base' => 'Souris ergonomique sans fil', 'path' => ['High-Tech', 'Ordinateurs', 'Périphériques'], 'price' => [19, 89], 'axes' => ['Couleur'], 'kind' => 'computing', 'materials' => ['plastique soft-touch'], 'features' => ['capteur 16 000 dpi', 'clics silencieux', 'autonomie 70 jours']],
    ['base' => 'SSD externe', 'path' => ['High-Tech', 'Ordinateurs', 'Périphériques'], 'price' => [49, 299], 'axes' => ['Capacité'], 'kind' => 'computing', 'materials' => ['boîtier aluminium'], 'features' => ['USB 3.2', 'jusqu’à 1050 Mo/s', 'résistant aux chocs']],
    ['base' => 'Casque sans fil à réduction de bruit', 'path' => ['High-Tech', 'Audio', 'Casques'], 'price' => [49, 399], 'axes' => ['Couleur'], 'kind' => 'audio', 'materials' => ['coussinets en similicuir', 'arceau aluminium'], 'features' => ['réduction de bruit active', 'autonomie 40 h', 'Bluetooth 5.3', 'connexion multipoint']],
    ['base' => 'Écouteurs true wireless', 'path' => ['High-Tech', 'Audio', 'Casques'], 'price' => [29, 249], 'axes' => ['Couleur'], 'kind' => 'audio', 'materials' => ['plastique mat'], 'features' => ['réduction de bruit', 'boîtier de charge', 'résistance IPX4']],
    ['base' => 'Enceinte Bluetooth portable', 'path' => ['High-Tech', 'Audio', 'Enceintes'], 'price' => [25, 199], 'axes' => ['Couleur'], 'kind' => 'audio', 'materials' => ['tissu acoustique', 'silicone renforcé'], 'features' => ['étanche IP67', 'autonomie 20 h', 'son 360°']],
    // ── Beauté ──
    ['base' => 'Crème hydratante visage', 'path' => ['Beauté', 'Soins', 'Visage'], 'price' => [9, 49], 'axes' => [], 'kind' => 'beauty', 'materials' => ['à l’acide hyaluronique', 'à l’aloe vera'], 'features' => ['hydratation 24 h', 'non comédogène', 'testée dermatologiquement']],
    ['base' => 'Sérum éclat vitamine C', 'path' => ['Beauté', 'Soins', 'Visage'], 'price' => [15, 69], 'axes' => [], 'kind' => 'beauty', 'materials' => ['vitamine C stabilisée', 'niacinamide'], 'features' => ['anti-taches', 'texture légère', 'absorption rapide']],
    ['base' => 'Shampoing nourrissant', 'path' => ['Beauté', 'Soins', 'Cheveux'], 'price' => [6, 29], 'axes' => [], 'kind' => 'beauty', 'materials' => ['à l’huile d’argan', 'à la kératine'], 'features' => ['sans sulfates', 'pour cheveux secs', 'parfum délicat']],
    ['base' => 'Rouge à lèvres mat', 'path' => ['Beauté', 'Maquillage', 'Lèvres'], 'price' => [8, 32], 'axes' => ['Couleur'], 'kind' => 'beauty', 'materials' => ['formule longue tenue'], 'features' => ['fini mat', 'non desséchant', 'application crémeuse']],
    ['base' => 'Palette de fards à paupières', 'path' => ['Beauté', 'Maquillage', 'Yeux'], 'price' => [12, 45], 'axes' => [], 'kind' => 'beauty', 'materials' => ['poudres pressées'], 'features' => ['12 teintes', 'mats et satinés', 'fortement pigmentés']],
    ['base' => 'Fond de teint fini naturel', 'path' => ['Beauté', 'Maquillage', 'Teint'], 'price' => [14, 49], 'axes' => ['Couleur'], 'kind' => 'beauty', 'materials' => ['couvrance modulable'], 'features' => ['fini naturel', 'SPF 15', 'longue tenue']],
    // ── Sport ──
    ['base' => 'Tapis de yoga', 'path' => ['Sport', 'Fitness', 'Cardio'], 'price' => [15, 69], 'axes' => ['Couleur'], 'kind' => 'sport', 'materials' => ['TPE écologique', 'caoutchouc naturel'], 'features' => ['antidérapant', 'épaisseur 6 mm', 'sangle de transport']],
    ['base' => 'Haltères réglables', 'path' => ['Sport', 'Fitness', 'Musculation'], 'price' => [29, 199], 'axes' => [], 'kind' => 'sport', 'materials' => ['fonte', 'revêtement néoprène'], 'features' => ['poids ajustable', 'prise antidérapante', 'gain de place']],
    ['base' => 'Vélo d’appartement', 'path' => ['Sport', 'Fitness', 'Cardio'], 'price' => [149, 699], 'axes' => [], 'kind' => 'sport', 'materials' => ['cadre en acier'], 'features' => ['résistance magnétique', 'écran LCD', 'selle réglable']],
    ['base' => 'Chaussures de trail', 'path' => ['Sport', 'Plein air', 'Randonnée'], 'price' => [69, 179], 'axes' => ['Pointure', 'Couleur'], 'kind' => 'footwear', 'materials' => ['mesh renforcé', 'membrane imperméable'], 'features' => ['accroche tout-terrain', 'protection des orteils', 'maintien du pied']],
    ['base' => 'Tente 2 places', 'path' => ['Sport', 'Plein air', 'Camping'], 'price' => [49, 249], 'axes' => [], 'kind' => 'sport', 'materials' => ['polyester déperlant'], 'features' => ['montage rapide', 'imperméable 3000 mm', 'sac de transport']],
    ['base' => 'Sac à dos de randonnée 30 L', 'path' => ['Sport', 'Plein air', 'Randonnée'], 'price' => [39, 139], 'axes' => ['Couleur'], 'kind' => 'sport', 'materials' => ['nylon ripstop'], 'features' => ['dos ventilé', 'housse anti-pluie', 'multiples poches']],
    // ── Papeterie ──
    ['base' => 'Carnet de notes A5', 'path' => ['Papeterie', 'Bureau', 'Carnets'], 'price' => [6, 24], 'axes' => ['Couleur'], 'kind' => 'stationery', 'materials' => ['papier ivoire 90 g', 'couverture rigide'], 'features' => ['192 pages', 'pages pointillées', 'élastique de fermeture', 'pochette intérieure']],
    ['base' => 'Stylo plume', 'path' => ['Papeterie', 'Bureau', 'Stylos'], 'price' => [12, 79], 'axes' => ['Couleur'], 'kind' => 'stationery', 'materials' => ['résine laquée', 'corps en métal'], 'features' => ['plume en acier', 'convertisseur inclus', 'écriture fluide']],
    ['base' => 'Set de surligneurs pastel', 'path' => ['Papeterie', 'Bureau', 'Stylos'], 'price' => [4, 19], 'axes' => [], 'kind' => 'stationery', 'materials' => ['encre pastel'], 'features' => ['pointe biseautée', 'lot de 6', 'séchage rapide']],
    ['base' => 'Trieur à soufflets', 'path' => ['Papeterie', 'Bureau', 'Classement'], 'price' => [8, 29], 'axes' => ['Couleur'], 'kind' => 'stationery', 'materials' => ['polypropylène'], 'features' => ['13 compartiments', 'fermeture élastique', 'index repositionnables']],
    ['base' => 'Set de peinture acrylique', 'path' => ['Papeterie', 'Art', 'Peinture'], 'price' => [12, 49], 'axes' => [], 'kind' => 'stationery', 'materials' => ['peinture acrylique'], 'features' => ['24 couleurs', 'séchage rapide', 'pigments riches']],
    ['base' => 'Carnet de croquis', 'path' => ['Papeterie', 'Art', 'Dessin'], 'price' => [7, 29], 'axes' => [], 'kind' => 'stationery', 'materials' => ['papier 160 g'], 'features' => ['grain fin', 'reliure spirale', 'idéal techniques sèches']],
    // ── Jouets ──
    ['base' => 'Jeu de construction 500 pièces', 'path' => ['Jouets', 'Jeux', 'Construction'], 'price' => [19, 89], 'axes' => [], 'kind' => 'toys', 'materials' => ['plastique ABS'], 'features' => ['compatible grandes marques', 'boîte de rangement', 'dès 6 ans']],
    ['base' => 'Jeu de société familial', 'path' => ['Jouets', 'Jeux', 'Société'], 'price' => [12, 49], 'axes' => [], 'kind' => 'toys', 'materials' => ['carton renforcé'], 'features' => ['2 à 6 joueurs', 'dès 8 ans', 'parties de 30 min']],
    ['base' => 'Peluche ours', 'path' => ['Jouets', 'Éveil', '0-3 ans'], 'price' => [9, 39], 'axes' => ['Couleur'], 'kind' => 'toys', 'materials' => ['peluche douce'], 'features' => ['lavable en machine', 'rembourrage hypoallergénique', 'coutures renforcées']],
    ['base' => 'Tapis d’éveil', 'path' => ['Jouets', 'Éveil', '0-3 ans'], 'price' => [19, 69], 'axes' => [], 'kind' => 'toys', 'materials' => ['tissu matelassé'], 'features' => ['arches d’activités', 'miroir bébé', 'jouets suspendus']],
    ['base' => 'Trottinette enfant', 'path' => ['Jouets', 'Jeux', 'Plein air'], 'price' => [25, 99], 'axes' => ['Couleur'], 'kind' => 'toys', 'materials' => ['aluminium léger'], 'features' => ['hauteur réglable', 'roues lumineuses', 'frein arrière']],
    // ── Alimentation ──
    ['base' => 'Café en grains 1 kg', 'path' => ['Alimentation', 'Épicerie', 'Boissons'], 'price' => [9, 39], 'axes' => [], 'kind' => 'food', 'materials' => ['100 % arabica', 'torréfaction artisanale'], 'features' => ['notes chocolatées', 'origine Amérique du Sud', 'fraîchement torréfié']],
    ['base' => 'Tablette de chocolat noir 70 %', 'path' => ['Alimentation', 'Épicerie', 'Sucré'], 'price' => [2, 12], 'axes' => [], 'kind' => 'food', 'materials' => ['cacao 70 %'], 'features' => ['pur beurre de cacao', 'commerce équitable', 'fabrication française']],
    ['base' => 'Huile d’olive vierge extra', 'path' => ['Alimentation', 'Épicerie', 'Salé'], 'price' => [7, 29], 'axes' => [], 'kind' => 'food', 'materials' => ['première pression à froid'], 'features' => ['fruité intense', 'AOP', 'bouteille 75 cl']],
    ['base' => 'Thé vert bio', 'path' => ['Alimentation', 'Épicerie', 'Boissons'], 'price' => [5, 25], 'axes' => [], 'kind' => 'food', 'materials' => ['feuilles entières'], 'features' => ['20 sachets', 'agriculture biologique', 'riche en antioxydants']],
    ['base' => 'Miel de fleurs (500 g)', 'path' => ['Alimentation', 'Frais', 'Crémerie'], 'price' => [6, 22], 'axes' => [], 'kind' => 'food', 'materials' => ['récolte locale'], 'features' => ['non pasteurisé', 'texture crémeuse', 'récolte de l’année']],
    ['base' => 'Plateau de fromages affinés', 'path' => ['Alimentation', 'Frais', 'Traiteur'], 'price' => [15, 49], 'axes' => [], 'kind' => 'food', 'materials' => ['affinage traditionnel'], 'features' => ['4 fromages', 'idéal apéritif', 'sélection du fromager']],
];

// ════════════════════════════════════════════════════════════════════════════
// GENERATE
// ════════════════════════════════════════════════════════════════════════════
echo "\033[1m=== AI SmartTalk seeder — GENERATE ===\033[0m\n";
echo 'PrestaShop: ' . _PS_VERSION_ . " | products=$COUNT | variants=$VARIANT_RATIO "
    . "| oos=$OOS_RATIO | promo=$PROMO_RATIO | batch=$BATCH | seed=$SEED\n\n";

$pick = static function (array $arr) {
    return $arr[mt_rand(0, count($arr) - 1)];
};
$chance = static function (float $p): bool {
    return (mt_rand() / mt_getrandmax()) < $p;
};

// Self-heal: `make seed-products` APPENDS (it does not purge first), so repeated
// runs — or an interrupted purge — can leave products linked to categories that
// were later deleted. Such a product syncs with EMPTY categories (no facets).
// Sweep any dangling category_product link before building so the resulting
// catalog is always facet-clean, regardless of prior runs.
// NB: this is top-level GENERATE scope — _DB_PREFIX_ directly (no $p var here,
// unlike purge() which defines its own $p = _DB_PREFIX_).
purgeOrphanCategoryLinks($db, _DB_PREFIX_);

// ─── 1. Category tree (derived from archetype paths → guaranteed coherent) ────
echo "Building category tree…\n";
$rootCatId = createCategory(SEED_ROOT_CAT, $homeCatId, $languages);
$catIdByPath = []; // "Dept/Family/Leaf" → id
foreach ($ARCHETYPES as $arch) {
    $acc = '';
    $parent = $rootCatId;
    foreach ($arch['path'] as $segment) {
        $acc = $acc === '' ? $segment : "$acc/$segment";
        if (!isset($catIdByPath[$acc])) {
            $catIdByPath[$acc] = createCategory($segment, $parent, $languages);
        }
        $parent = $catIdByPath[$acc];
    }
}
echo '  ' . count($catIdByPath) . " categories created.\n";

// ─── 2. Brands (per pool, tagged via short_description marker) ────────────────
echo "Building brands…\n";
$brandIdsByPool = [];
foreach ($BRAND_POOLS as $poolKey => $names) {
    foreach ($names as $bn) {
        $m = new Manufacturer();
        $m->name = $bn;
        $m->active = true;
        $m->short_description = multilang(SEED_BRAND_MARK, $languages);
        $m->add();
        $brandIdsByPool[$poolKey][] = (int) $m->id;
    }
}
echo '  ' . array_sum(array_map('count', $brandIdsByPool)) . " brands created.\n";

// ─── 3. Attribute groups + values (with price impacts) ───────────────────────
echo "Building attribute groups…\n";
$attrGroups = []; // name → ['id'=>, 'attrs'=>[ ['id','label','impact'], … ]]
foreach ($attrSpec as $gname => $spec) {
    $ag = new AttributeGroup();
    $ag->name = multilang(SEED_ATTR_PREFIX . $gname, $languages);
    $ag->public_name = multilang($gname, $languages);
    $ag->group_type = $spec['type'];
    $ag->is_color_group = $spec['type'] === 'color';
    $ag->add();

    $attrs = [];
    foreach ($spec['values'] as $val) {
        $label = $val[0];
        $impact = (float) $val[1];
        $a = new ProductAttribute(); // PS9: legacy `Attribute` clashes with PHP 8's built-in
        $a->id_attribute_group = (int) $ag->id;
        $a->name = multilang($label, $languages);
        if (isset($val[2])) {
            $a->color = $val[2];
        }
        $a->add();
        $attrs[] = ['id' => (int) $a->id, 'label' => $label, 'impact' => $impact];
    }
    $attrGroups[$gname] = ['id' => (int) $ag->id, 'attrs' => $attrs];
}
echo '  ' . count($attrGroups) . " attribute groups created.\n";

// ─── 4. Generate products in transactional batches ───────────────────────────
echo "\nGenerating $COUNT products…\n";
$created = $withVariants = $outOfStock = $promo = 0;
$startTs = time();

for ($i = 1; $i <= $COUNT; $i++) {
    if (($i - 1) % $BATCH === 0) {
        $db->execute('START TRANSACTION');
    }

    $arch = $ARCHETYPES[array_rand($ARCHETYPES)];
    $kind = $arch['kind'];
    $tone = $KIND[$kind];

    // --- categories: leaf (+ often family, sometimes dept) with a VARIED default ---
    $leafPath = implode('/', $arch['path']);
    $leafId = $catIdByPath[$leafPath];
    $familyId = $catIdByPath[implode('/', array_slice($arch['path'], 0, 2))] ?? $leafId;
    $deptId = $catIdByPath[$arch['path'][0]] ?? $leafId;
    $cats = [$leafId];
    if ($chance(0.7)) {
        $cats[] = $familyId;
    }
    if ($chance(0.3)) {
        $cats[] = $deptId;
    }
    $cats = array_values(array_unique($cats));
    $defaultCat = $cats[array_rand($cats)];

    // --- name (coherent series + suffix, optional dominant colour for simple) ---
    $series = $pick($tone['series']);
    $suffix = $pick($tone['suffix']);
    $name = trim("{$arch['base']} {$series} {$suffix}");

    // --- decide shape ---
    $brandId = $chance($NOBRAND_RATIO) ? 0 : ($pick($brandIdsByPool[$BRAND_POOL_OF[$kind]] ?? $brandIdsByPool['office']));
    $brandName = $brandId ? Manufacturer::getNameById($brandId) : null;
    $isOos = $chance($OOS_RATIO);
    $hasVariants = !empty($arch['axes']) && $chance($VARIANT_RATIO);

    // --- price (realistic for the archetype, with a .90/.95/.99 ending) ---
    $whole = mt_rand($arch['price'][0], $arch['price'][1]);
    $cents = $chance(0.6) ? 0.99 : ($chance(0.5) ? 0.95 : 0.90);
    $price = round(max(0.99, $whole - 1 + $cents), 2);

    // Dominant colour for a simple product whose archetype is colour-based.
    $dominantColor = null;
    if (!$hasVariants && in_array('Couleur', $arch['axes'], true)) {
        $dominantColor = $attrGroups['Couleur']['attrs'][array_rand($attrGroups['Couleur']['attrs'])]['label'];
        $name .= " — $dominantColor";
    }

    // --- create product ---
    $p = new Product();
    $p->name = multilang($name, $languages);
    $p->link_rewrite = multilang(slug($name) . '-' . $i, $languages);
    $p->reference = SEED_REF_PREFIX . $i;
    $p->price = $price;
    $p->id_category_default = $defaultCat;
    if ($brandId) {
        $p->id_manufacturer = $brandId;
    }
    $p->id_tax_rules_group = 0;
    $p->active = true;
    $p->available_for_order = true;
    $p->show_price = true;
    $p->minimal_quantity = 1;
    $p->state = 1;
    if ($isOos && $chance(0.6)) {
        $p->available_date = date('Y-m-d', strtotime('+' . mt_rand(5, 60) . ' days'));
    }

    // Description is built AFTER we know the variant axes (so it can announce
    // "décliné en 5 tailles et 4 coloris") — set placeholders, fill below.
    $p->add();
    $p->addToCategories($cats);

    // --- stock / variants ---
    $axesSummary = [];
    if ($hasVariants) {
        $axesSummary = buildCombinations($p, $arch['axes'], $attrGroups, $idShop, $isOos, $chance, $i);
        $withVariants++;
    } else {
        $qty = $isOos ? 0 : mt_rand(1, 5000);
        StockAvailable::setQuantity((int) $p->id, 0, $qty, $idShop);
    }

    // --- coherent, informative description ---
    [$desc, $short] = buildCopy(
        $name, $arch, $tone, $brandName, $dominantColor,
        $axesSummary, $isOos, $p->available_date ?? null, $pick, $chance
    );
    $p->description = multilang($desc, $languages);
    $p->description_short = multilang($short, $languages);
    $p->update();

    // --- promotions ---
    if ($chance($PROMO_RATIO)) {
        $sp = new SpecificPrice();
        $sp->id_product = (int) $p->id;
        $sp->id_shop = 0;
        $sp->id_currency = 0;
        $sp->id_country = 0;
        $sp->id_group = 0;
        $sp->id_customer = 0;
        $sp->id_product_attribute = 0;
        $sp->from_quantity = 1;
        $sp->price = -1;
        if ($chance(0.5)) {
            $sp->reduction_type = 'percentage';
            $sp->reduction = mt_rand(5, 50) / 100;
        } else {
            $sp->reduction_type = 'amount';
            $sp->reduction = min(round($price * 0.4, 2), (float) mt_rand(1, 30));
            $sp->reduction_tax = 1;
        }
        $sp->from = '0000-00-00 00:00:00';
        $sp->to = '0000-00-00 00:00:00';
        $sp->add();
        $promo++;
    }

    if ($isOos) {
        $outOfStock++;
    }
    $created++;
    unset($p);

    if ($i % $BATCH === 0 || $i === $COUNT) {
        $db->execute('COMMIT');
        $pct = round($i / $COUNT * 100);
        $elapsed = max(1, time() - $startTs);
        $rate = round($i / $elapsed);
        echo "  $i/$COUNT ({$pct}%) — {$rate}/s — variants=$withVariants oos=$outOfStock promo=$promo\n";
    }
}

if ($REINDEX) {
    echo "\nRebuilding search index (this can take a while)…\n";
    Search::indexation(true);
    echo "  done.\n";
}

$elapsed = time() - $startTs;
echo "\n\033[32m=== Done in {$elapsed}s ===\033[0m\n";
echo "Products: $created (variants=$withVariants, out-of-stock=$outOfStock, promo=$promo)\n";
echo 'Archetypes: ' . count($ARCHETYPES) . ' | Categories: ' . (count($catIdByPath) + 1)
    . ' | Brands: ' . array_sum(array_map('count', $brandIdsByPool))
    . ' | Attribute groups: ' . count($attrGroups) . "\n";
echo "Purge later with: make seed-products-purge\n";

// ════════════════════════════════════════════════════════════════════════════
// Helpers
// ════════════════════════════════════════════════════════════════════════════

/**
 * Delete category_product rows that point at a category which no longer exists.
 * These orphans (left by deleting a category without cleaning its product links)
 * are invisible in the back-office but make the affected products sync to AI
 * SmartTalk with EMPTY categories — i.e. no category/brand/attribute facets, so
 * they cannot be filtered or surfaced by attribute questions. Global on purpose:
 * it heals the whole shop, not just this seeder's products.
 *
 * @param Db     $db
 * @param string $p  table prefix (_DB_PREFIX_)
 */
function purgeOrphanCategoryLinks($db, string $p): void
{
    $orphans = (int) $db->getValue(
        "SELECT COUNT(*) FROM {$p}category_product cp
           LEFT JOIN {$p}category c ON c.id_category = cp.id_category
         WHERE c.id_category IS NULL"
    );
    if ($orphans > 0) {
        $db->execute(
            "DELETE cp FROM {$p}category_product cp
               LEFT JOIN {$p}category c ON c.id_category = cp.id_category
             WHERE c.id_category IS NULL"
        );
        echo "Orphaned category_product links removed: {$orphans}.\n";
    }
}

function createCategory(string $name, int $idParent, array $languages): int
{
    static $seq = 0;
    $seq++;
    $c = new Category();
    $c->name = multilang($name, $languages);
    $c->link_rewrite = multilang(slug($name) . '-aiseed-' . $seq, $languages);
    $c->id_parent = $idParent;
    $c->active = true;
    $c->add();
    return (int) $c->id;
}

/**
 * Build combinations across the archetype's axes with coherent pricing (impact
 * sums from the chosen attribute values) and a mix of in/out-of-stock. Returns
 * a per-axis count summary (e.g. ['Taille'=>5,'Couleur'=>4]) for the copy.
 *
 * @param array<int,string> $axes
 * @param array             $attrGroups name → ['id','attrs'=>[['id','label','impact']]]
 * @return array<string,int>
 */
function buildCombinations(
    Product $product,
    array $axes,
    array $attrGroups,
    int $idShop,
    bool $productOos,
    callable $chance,
    int $idx
): array {
    $perAxis = [];
    $summary = [];
    foreach ($axes as $axisName) {
        $all = $attrGroups[$axisName]['attrs'];
        if ($axisName === 'Taille' || $axisName === 'Pointure' || $axisName === 'Capacité') {
            // Ordered axes → take a contiguous, realistic range.
            $start = mt_rand(0, max(0, count($all) - 2));
            $len = min(count($all) - $start, mt_rand(2, 5));
            $chosen = array_slice($all, $start, $len);
        } else {
            $shuf = $all;
            shuffle($shuf);
            $chosen = array_slice($shuf, 0, min(count($shuf), mt_rand(2, 4)));
        }
        $perAxis[] = $chosen;
        $summary[$axisName] = count($chosen);
    }

    // Cartesian product, capped.
    $combos = [[]];
    foreach ($perAxis as $axisValues) {
        $next = [];
        foreach ($combos as $combo) {
            foreach ($axisValues as $attr) {
                $next[] = array_merge($combo, [$attr]);
            }
        }
        $combos = $next;
    }
    if (count($combos) > 16) {
        $combos = array_slice($combos, 0, 16);
    }

    $first = true;
    foreach ($combos as $k => $attrs) {
        $impact = 0.0;
        $ids = [];
        foreach ($attrs as $attr) {
            $impact += $attr['impact'];
            $ids[] = $attr['id'];
        }
        $cmb = new Combination();
        $cmb->id_product = (int) $product->id;
        $cmb->reference = SEED_REF_PREFIX . $idx . '-' . ($k + 1);
        $cmb->ean13 = sprintf('%013d', mt_rand(1000000000, 9999999999));
        $cmb->price = round($impact, 2);
        $cmb->minimal_quantity = 1;
        $cmb->default_on = $first ? 1 : null;
        $cmb->add();
        $cmb->setAttributes($ids);

        $qty = $productOos ? 0 : ($chance(0.2) ? 0 : mt_rand(1, 2000));
        StockAvailable::setQuantity((int) $product->id, (int) $cmb->id, $qty, $idShop);

        $first = false;
        unset($cmb);
    }

    Product::updateDefaultAttribute((int) $product->id);
    return $summary;
}

/**
 * Build a coherent, informative description + short description for the product.
 *
 * @param array<string,int> $axesSummary
 * @return array{0:string,1:string} [description, descriptionShort]
 */
function buildCopy(
    string $name,
    array $arch,
    array $tone,
    ?string $brandName,
    ?string $dominantColor,
    array $axesSummary,
    bool $isOos,
    $availableDate,
    callable $pick,
    callable $chance
): array {
    // Pick 2–3 distinct features + a material, specific to this archetype.
    $feats = $arch['features'];
    shuffle($feats);
    $feats = array_slice($feats, 0, min(count($feats), $chance(0.5) ? 3 : 2));
    $material = $pick($arch['materials']);

    $signature = $brandName ? "$name, signé $brandName" : $name;
    $intro = ucfirst($pick($tone['intro']));
    // Material lead that reads naturally per family (a phone isn't "fabriqué en").
    $matLead = [
        'tech' => 'Conçu avec ', 'computing' => 'Conçu avec ', 'audio' => 'Conçu avec ',
        'beauty' => 'Formule : ', 'food' => 'Composition : ',
    ][$arch['kind']] ?? 'Fabriqué en ';
    $matSentence = $matLead . $material . '.';
    $featSentence = 'Points forts : ' . implode(', ', $feats) . '.';
    $useSentence = 'Idéal pour ' . $pick($tone['use']) . '.';

    // Variant / colour announcement.
    $variantSentence = '';
    if (!empty($axesSummary)) {
        $parts = [];
        foreach ($axesSummary as $axis => $n) {
            $label = [
                'Taille' => 'tailles', 'Pointure' => 'pointures', 'Couleur' => 'coloris',
                'Capacité' => 'capacités', 'Matière' => 'matières',
            ][$axis] ?? strtolower($axis);
            $parts[] = "$n $label";
        }
        $variantSentence = 'Décliné en ' . implode(' et ', $parts) . '.';
    } elseif ($dominantColor) {
        $variantSentence = "Coloris : $dominantColor.";
    }

    // Live stock state — useful to query against ("qu'est-ce qui est dispo ?").
    if ($isOos) {
        $stockSentence = $availableDate
            ? 'Temporairement en rupture — réapprovisionnement prévu le ' . $availableDate . '.'
            : 'Temporairement en rupture de stock.';
    } else {
        $stockSentence = 'En stock, expédié sous 24 à 48 h.';
    }

    $desc = trim(implode(' ', array_filter([
        "$signature : $intro.",
        $matSentence,
        $featSentence,
        $variantSentence,
        $useSentence,
        $stockSentence,
    ])));

    $short = $arch['base'] . ($brandName ? " $brandName" : '') . ' — ' . ($feats[0] ?? $pick($tone['use'])) . '.';

    return [$desc, $short];
}
