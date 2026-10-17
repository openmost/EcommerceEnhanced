/*!
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/**
 * The "Item" scope of the Custom Dimensions page, see ItemDimensions.php. The page only knows the visit and action
 * scopes: item dimensions use the visit form, saved through the Ecommerce Enhanced API, and the "how to track"
 * box shows the dataLayer example instead of setCustomDimension.
 */
(function () {
    if (!/[?&]module=CustomDimensions(&|$)/.test(window.location.search) || !window.$) {
        return;
    }

    // The page opens its form for the visit and action scopes only: item dimensions use the visit form, marked
    function itemHash(idDimension) {
        return '#?idDimension=' + idDimension + '&scope=visit&eeItem=1';
    }

    function isItemForm() {
        return /[?&]eeItem=1(&|$)/.test(window.location.hash);
    }

    function isCreatingItem() {
        return isItemForm() && /[?&]idDimension=0(&|$)/.test(window.location.hash);
    }

    function isEditingItem() {
        return isItemForm() && !isCreatingItem();
    }

    document.addEventListener('click', function (event) {
        var target = event.target && event.target.closest ? event.target : null;
        if (!target) {
            return;
        }

        var button = target.closest('.scope-item .tableActionBar button');
        var link = target.closest('.scope-item a[href*="idDimension="]');
        var match = link ? /idDimension=(\d+)/.exec(link.getAttribute('href')) : null;
        if ((button && !button.disabled) || match) {
            event.preventDefault();
            event.stopPropagation();
            window.location.hash = itemHash(match ? match[1] : 0);
        }
    }, true);

    // The form of an item dimension saves through the Ecommerce Enhanced API, never through Custom Dimensions
    window.$.ajaxPrefilter(function (options) {
        if (!isItemForm() || !options.url) {
            return;
        }

        options.url = options.url
            .replace('method=CustomDimensions.configureNewCustomDimension', 'method=EcommerceEnhanced.addItemDimension')
            .replace('method=CustomDimensions.configureExistingCustomDimension', 'method=EcommerceEnhanced.updateItemDimension');
    });

    function translate(key) {
        return window._pk_translate ? window._pk_translate(key) : key;
    }

    function update() {
        var title = document.querySelector('.editCustomDimension h2, .manageCustomDimensions h2');
        if (title && isItemForm() && title.getAttribute('data-ee-item') !== '1') {
            title.setAttribute('data-ee-item', '1');
            title.textContent = translate('EcommerceEnhanced_ItemDimensionCreateTitle');
        }

        var box = document.querySelector('.howToTrackInfo');
        if (!box) {
            return;
        }

        var note = document.querySelector('.eeItemDimensionHowTo');
        box.style.display = isEditingItem() ? 'none' : '';
        if (!isEditingItem()) {
            if (note) {
                note.parentNode.removeChild(note);
            }
            return;
        }

        if (note) {
            return;
        }

        var nameField = document.querySelector('input#name');
        var name = nameField && nameField.value ? nameField.value : 'size';

        note = document.createElement('div');
        note.className = 'alert alert-info eeItemDimensionHowTo';

        var heading = document.createElement('strong');
        heading.textContent = translate('EcommerceEnhanced_ItemDimensionHowToTitle');
        var text = document.createElement('p');
        text.textContent = translate('EcommerceEnhanced_ItemDimensionHowTo');
        var code = document.createElement('pre');
        code.textContent = "dataLayer.push({\n  event: 'add_to_cart',\n  ecommerce: {\n    items: [{item_id: 'SKU_123', item_name: 'Organic tee', price: 30, quantity: 1, "
            + name + ": 'value'}]\n  }\n});";

        note.appendChild(heading);
        note.appendChild(text);
        note.appendChild(code);
        box.parentNode.insertBefore(note, box.nextSibling);
    }

    var scheduled = false;
    new MutationObserver(function () {
        if (!scheduled) {
            scheduled = true;
            window.setTimeout(function () {
                scheduled = false;
                update();
            }, 50);
        }
    }).observe(document.documentElement, {childList: true, subtree: true});
    window.addEventListener('hashchange', update);
})();
