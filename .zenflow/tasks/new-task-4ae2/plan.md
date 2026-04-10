# Auto

## Configuration
- **Artifacts Path**: {@artifacts_path} → `.zenflow/tasks/{task_id}`

---

## Agent Instructions

Ask the user questions when anything is unclear or needs their input. This includes:
- Ambiguous or incomplete requirements
- Technical decisions that affect architecture or user experience
- Trade-offs that require business context

Do not make assumptions on important decisions — get clarification first.

---

## Workflow Steps

### [x] Step: Implementation

**Corrections effectuées sur la template `electro` :**

#### Problèmes identifiés et corrigés :

1. **`templates/frontOffice/electro/layout.tpl`**
   - Suppression du conflit Bootstrap 3.3.6 JS vs Bootstrap 5 (les deux étaient chargés simultanément)
   - Suppression de bootbox.js (incompatible Bootstrap 5)
   - Mise à jour jquery.validate vers CDN jsdelivr v1.19.5
   - Internationalisation de la section "features" (Fast Delivery, Best Prices, etc.) via `{intl}`
   - Ajout du hook `main.features-blocks` pour permettre une personnalisation future

2. **`templates/frontOffice/electro/partials/header.html`**
   - Liens "Support/Delivery/Warranty" statiques → remplacés par numéro de téléphone dynamique (`{config key="store_phone"}`) et liens vers les contenus du dossier `information_folder_id`
   - Badge de notification hardcodé "1" → supprimé
   - Lien "My Orders" conditionné à l'authentification cliente
   - Compteur panier dynamique via `{cart attr="count_item"}`

3. **`templates/frontOffice/electro/partials/navbar.html`**
   - Suppression des références aux modales `#userModal` et `#userModalLogin` (inexistantes)
   - Navigation mobile : liens dynamiques selon état de connexion (`{loop type="auth"}`)
   - Compteur panier dynamique dans la barre mobile

4. **`local/modules/HookCart/templates/frontOffice/electro/mini-cart.html`**
   - Réécriture complète en Bootstrap 5 propre (suppression du style Bootstrap 3)
   - Utilisation des prix TTC (`$TAXED_PRICE`, `$TAXED_PROMO_PRICE`)
   - Gestion d'état vide/non-vide avec messages traduits via `{intl}`
   - Lien de suppression correct via `{token_url}`

5. **`local/modules/HookNavigation/templates/frontOffice/electro/main-footer-top.html`**
   - Suppression complète des classes Tailwind CSS (incompatibles avec la template)
   - Remplacement par Bootstrap 5
   - Contenu 100% dynamique : catégories via loop Thelia, liens de compte selon authentification, marques, contact

6. **`local/modules/HookProductsNew/templates/frontOffice/electro/home-body.html`**
   - Suppression des URLs AJAX hardcodées `/web/index_dev.php/cart/add` (URLs de dev !)
   - Remplacement par formulaire POST standard Thelia `{url path="/cart/add"}`
   - Gestion produits multi-PSE : lien "Voir" vs bouton "Ajouter"
   - Prix TTC corrects (`$TAXED_PRICE`, `$TAXED_PROMO_PRICE`)

7. **`local/modules/HookProductsOffer/templates/frontOffice/electro/home-body.html`**
   - Même corrections que HookProductsNew (URLs dev, prix TTC, PSE multiples)

8. **`local/modules/HookCurrency/templates/frontOffice/electro/main-header-top.html`**
   - Correction de `{$SYMBOL}` (variable non définie hors loop) → `{currency attr="symbol"}`

9. **`local/modules/HookCustomer/templates/frontOffice/electro/main-navbar-secondary-right.html`**
   - Suppression des `data-bs-toggle="modal"` pointant vers des modales inexistantes
   - Liens directs vers `/login` et `/register`

10. **`local/modules/Carousel/templates/frontOffice/electro/carousel.html`**
    - Section bannières statiques hardcodées → rendue conditionnelle via `{config key="carousel_banner_folder_id"}`
    - Utilisation du loop `content` pour des bannières dynamiques configurables par l'admin
    - Images de catégories avec `{ifloop}` correct
    - Textes traduits via `{intl}`

