/*!
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/**
 * The "Item" scope of the Custom Dimensions page, see ItemDimensions.php. The page only knows the visit and action
 * scopes: item dimensions use the visit form, saved through the Ecommerce Enhanced API, with a "dataLayer key" field
 * next to the name (the name is the label displayed in the reports, the key the item parameter the tracker reads).
 * The "how to track" box shows the dataLayer example instead of setCustomDimension, and the box explaining how to
 * get more dimensions gives the command of the Item scope too.
 */
(function () {
    if (!/[?&]module=CustomDimensions(&|$)/.test(window.location.search) || !window.$) {
        return;
    }

    // The page only renders its form for the visit and action scopes, scope=item leaves it blank (its title calls
    // ucfirst on an empty scope). The page is therefore given scope=visit&eeItem=1, the visit form marked as an item
    // one, while the address bar shows scope=item. The page reads the URL kept by MatomoUrl, set when it loads and
    // from each hashchange event, so the internal hash is given to it there, and the address bar is then swapped
    // back with replaceState, which fires no event.
    var INTERNAL_SCOPE = 'scope=visit&eeItem=1';
    var PUBLIC_SCOPE = 'scope=item';

    function replaceHash(hash) {
        if (hash !== window.location.hash) {
            window.history.replaceState(window.history.state, '', window.location.pathname + window.location.search + hash);
        }
    }

    function toInternalHash() {
        if (window.location.hash.indexOf(PUBLIC_SCOPE) === -1) {
            return false;
        }

        replaceHash(window.location.hash.replace(PUBLIC_SCOPE, INTERNAL_SCOPE));
        return true;
    }

    function toPublicHash() {
        if (window.location.hash.indexOf(INTERNAL_SCOPE) !== -1) {
            replaceHash(window.location.hash.replace(INTERNAL_SCOPE, PUBLIC_SCOPE));
        }
    }

    // A URL opened with scope=item: MatomoUrl has already read it, it is given the internal one before the page renders
    var matomoUrl = window.CoreHome && window.CoreHome.MatomoUrl;
    if (toInternalHash() && matomoUrl && matomoUrl.url) {
        matomoUrl.url.value = new URL(window.location.href);
    }

    // Back button or edited address: captured before MatomoUrl, the event is sent again with the internal URL
    window.addEventListener('hashchange', function (event) {
        if (toInternalHash()) {
            event.stopImmediatePropagation();
            window.dispatchEvent(new HashChangeEvent('hashchange', {oldURL: event.oldURL, newURL: window.location.href}));
        }
    }, true);

    function itemHash(idDimension) {
        return '#?idDimension=' + idDimension + '&' + INTERNAL_SCOPE;
    }

    function isItemForm() {
        return /[?&](eeItem=1|scope=item)(&|$)/.test(window.location.hash);
    }

    function isLocked() {
        return window.piwik && window.piwik.ecommerceEnhancedItemDimensionsLocked === true;
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

        // Without Premium the scope is shown but nothing in it opens
        if (isLocked() && (button || link)) {
            event.preventDefault();
            event.stopPropagation();
            return;
        }

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

        var url = options.url
            .replace('method=CustomDimensions.configureNewCustomDimension', 'method=EcommerceEnhanced.addItemDimension')
            .replace('method=CustomDimensions.configureExistingCustomDimension', 'method=EcommerceEnhanced.updateItemDimension');
        var keyField = document.querySelector('input#eeItemParameter');
        if (url !== options.url && keyField) {
            url += '&itemParameter=' + encodeURIComponent(keyField.value);
        }

        options.url = url;
    });

    function translate(key) {
        return window._pk_translate ? window._pk_translate(key) : key;
    }

    function currentIdDimension() {
        var match = /[?&]idDimension=(\d+)/.exec(window.location.hash);
        return match ? match[1] : '0';
    }

    // The dataLayer key of an existing dimension, from the list the page shows, which carries it
    var keys = null;
    function loadKeys(callback) {
        if (keys) {
            callback(keys);
            return;
        }

        var idSite = (/[?&]idSite=(\d+)/.exec(window.location.search) || [])[1];
        var body = new URLSearchParams({token_auth: window.piwik.token_auth, force_api_session: '1'});
        window.fetch('index.php?module=API&format=json&method=CustomDimensions.getConfiguredCustomDimensions&idSite=' + idSite, {method: 'POST', body: body})
            .then(function (response) { return response.json(); })
            .then(function (dimensions) {
                keys = {};
                (Array.isArray(dimensions) ? dimensions : []).forEach(function (dimension) {
                    if (dimension.item_parameter) {
                        keys[String(dimension.idcustomdimension)] = dimension.item_parameter;
                    }
                });
                callback(keys);
            });
    }

    function renderKeyField() {
        var nameField = document.querySelector('input#name');
        var existing = document.querySelector('.eeItemParameterField');
        if (!isItemForm() || !nameField) {
            if (existing) {
                existing.parentNode.removeChild(existing);
            }
            return;
        }

        // Built again when another dimension is opened, the page keeps its form between them
        if (existing && existing.getAttribute('data-id-dimension') === currentIdDimension()) {
            return;
        }

        if (existing) {
            existing.parentNode.removeChild(existing);
        }

        // Same markup as the fields of the page: input on the left, help on the right
        var nameRow = nameField.closest('.form-group') || nameField.parentNode;
        var row = document.createElement('div');
        row.className = 'form-group row matomo-form-field eeItemParameterField';
        row.setAttribute('data-id-dimension', currentIdDimension());

        var inputColumn = document.createElement('div');
        inputColumn.className = 'col s12 input-field m6';

        var input = document.createElement('input');
        input.type = 'text';
        input.id = 'eeItemParameter';
        input.className = 'control_text';
        input.maxLength = 40;
        input.required = true;
        input.placeholder = 'color';
        input.addEventListener('input', update);

        var label = document.createElement('label');
        label.setAttribute('for', 'eeItemParameter');
        label.className = 'active';
        label.textContent = translate('EcommerceEnhanced_ItemDimensionKey');

        inputColumn.appendChild(input);
        inputColumn.appendChild(label);

        var helpColumn = document.createElement('div');
        helpColumn.className = 'col s12 m6';
        var help = document.createElement('div');
        help.className = 'form-help';
        var helpText = document.createElement('span');
        helpText.className = 'inline-help';
        helpText.textContent = translate('EcommerceEnhanced_ItemDimensionKeyHelp');
        help.appendChild(helpText);
        helpColumn.appendChild(help);

        row.appendChild(inputColumn);
        row.appendChild(helpColumn);
        nameRow.parentNode.insertBefore(row, nameRow.nextSibling);

        if (isEditingItem()) {
            var idDimension = currentIdDimension();
            loadKeys(function (loaded) {
                if (!input.value && loaded[idDimension]) {
                    input.value = loaded[idDimension];
                    update();
                }
            });
        }
    }

    // Lines appended to the code blocks of the box explaining how to get more dimensions: the Item scope in both, and
    // the visit scope in the "several at once" one, which only shows the action scope, so that each block lists
    // every scope
    var COMMANDS = [
        ['./console customdimensions:add-custom-dimension --scope=item'],
        [
            './console customdimensions:add-custom-dimension --scope=visit --count=5',
            './console customdimensions:add-custom-dimension --scope=item --count=5'
        ]
    ];

    function renderMoreDimensionsCommand() {
        var block = document.querySelector('#customDimensionsCreateMoreDimensions');
        if (!block || !document.querySelector('.scope-item')) {
            return;
        }

        block.querySelectorAll('pre code').forEach(function (code, index) {
            (COMMANDS[index] || []).forEach(function (command) {
                if (code.textContent.indexOf(command) === -1) {
                    code.textContent = code.textContent.replace(/\s+$/, '') + '\n' + command;
                }
            });
        });
    }

    // Product examples in the name and description fields of an item dimension, the page's own ones otherwise
    function renderPlaceholders() {
        [
            ['input#name', 'EcommerceEnhanced_ItemDimensionNamePlaceholder'],
            ['textarea#description, input#description', 'EcommerceEnhanced_ItemDimensionDescriptionPlaceholder']
        ].forEach(function (field) {
            var element = document.querySelector(field[0]);
            if (!element) {
                return;
            }

            if (!element.hasAttribute('data-ee-placeholder')) {
                element.setAttribute('data-ee-placeholder', element.getAttribute('placeholder') || '');
            }

            var placeholder = isItemForm() ? translate(field[1]) : element.getAttribute('data-ee-placeholder');
            if (element.getAttribute('placeholder') !== placeholder) {
                element.setAttribute('placeholder', placeholder);
            }
        });
    }

    // The list of the Item scope shows the dataLayer key instead of the technical id (900000 + slot)
    var reloadedFor = {};
    function renderKeyColumn() {
        var table = document.querySelector('.scope-item table');
        if (!table) {
            return;
        }

        var header = table.querySelector('th.index');
        var title = translate('EcommerceEnhanced_ItemDimensionKeyColumn');
        if (header && header.textContent !== title) {
            header.textContent = title;
        }

        var cells = table.querySelectorAll('tr.customdimension td.index');
        if (!cells.length) {
            return;
        }

        loadKeys(function (loaded) {
            cells.forEach(function (cell) {
                var match = /customdimension-(\d+)/.exec(cell.parentNode.className);
                var key = match ? loaded[match[1]] : null;
                if (key === undefined && match && !reloadedFor[match[1]]) {
                    // A dimension created since the keys were loaded, they are loaded again once for it
                    reloadedFor[match[1]] = true;
                    keys = null;
                    window.setTimeout(update, 0);
                    return;
                }

                if (key && cell.textContent !== key) {
                    cell.textContent = key;
                }
            });
        });
    }

    // The Item scope without Premium: what it does is visible, its button and edit links are disabled
    function renderLock() {
        var scope = document.querySelector('.scope-item');
        if (!scope || !isLocked()) {
            return;
        }

        scope.classList.add('eeItemDimensionsLocked');
        scope.querySelectorAll('.tableActionBar button').forEach(function (button) {
            button.disabled = true;
        });

        if (scope.querySelector('.eeItemDimensionsLockNotice')) {
            return;
        }

        var notice = document.createElement('div');
        notice.className = 'alert alert-info eeItemDimensionsLockNotice';
        var text = document.createElement('p');
        text.textContent = translate('EcommerceEnhanced_ItemDimensionsLocked');
        var link = document.createElement('a');
        link.href = window.piwik.ecommerceEnhancedPremiumUrl;
        link.target = '_blank';
        link.rel = 'noreferrer noopener';
        link.className = 'btn';
        link.textContent = translate('EcommerceEnhanced_PremiumLinkText');
        notice.appendChild(text);
        notice.appendChild(link);

        var table = scope.querySelector('table');
        (table ? table.parentNode : scope).insertBefore(notice, table || null);
    }

    function update() {
        renderLock();
        // The form has read the internal hash, the address bar can show the item scope
        if (isItemForm() && document.querySelector('input#name')) {
            toPublicHash();
        }

        renderKeyColumn();
        renderKeyField();
        renderMoreDimensionsCommand();
        renderPlaceholders();

        // Compared with the text, not marked once: the page renders the title again after loading the dimension
        var form = document.querySelector('input#name');
        var card = form && form.closest('.card');
        var title = card && card.querySelector('.card-title');
        var itemTitle = translate('EcommerceEnhanced_ItemDimensionCreateTitle');
        if (title && isItemForm() && title.textContent !== itemTitle) {
            title.textContent = itemTitle;
        }

        var box = document.querySelector('.howToTrackInfo');
        if (!box) {
            return;
        }

        var note = document.querySelector('.eeItemDimensionHowTo');
        box.style.display = isItemForm() ? 'none' : '';
        if (!isItemForm()) {
            if (note) {
                note.parentNode.removeChild(note);
            }
            return;
        }

        // Follows the dataLayer key as it is typed: a new dimension shows the push without it until one is typed,
        // an existing one waits for its key, loaded after the form
        var keyField = document.querySelector('input#eeItemParameter');
        var name = keyField ? keyField.value.trim() : '';
        if (note && note.getAttribute('data-key') === name) {
            return;
        }

        if (note) {
            note.parentNode.removeChild(note);
        }

        if (!keyField || (!name && !isCreatingItem())) {
            return;
        }

        note = document.createElement('div');
        note.className = 'alert alert-info eeItemDimensionHowTo';
        note.setAttribute('data-key', name);

        var heading = document.createElement('strong');
        heading.textContent = translate('EcommerceEnhanced_ItemDimensionHowToTitle');
        var text = document.createElement('p');
        text.textContent = translate('EcommerceEnhanced_ItemDimensionHowTo');

        // "%1$s...%2$s" of the translation is the link to the GA4 ecommerce guide, built as an element, not as HTML
        var example = document.createElement('p');
        var parts = translate('EcommerceEnhanced_ItemDimensionHowToExample').split(/%[12]\$s/);
        example.appendChild(document.createTextNode(parts[0] || ''));
        if (parts.length === 3) {
            var link = document.createElement('a');
            link.href = 'https://developers.google.com/analytics/devguides/collection/ga4/ecommerce?client_type=gtm#implementation';
            link.target = '_blank';
            link.rel = 'noreferrer noopener';
            link.textContent = parts[1];
            example.appendChild(link);
            example.appendChild(document.createTextNode(parts[2]));
        }

        var code = document.createElement('pre');
        code.appendChild(document.createTextNode("dataLayer.push({\n  event: 'add_to_cart',\n  ecommerce: {\n    items: [{\n"
            + "      item_id: 'SKU_123',\n      item_name: 'Organic tee',\n      price: 30,\n      quantity: 1"));
        if (name) {
            code.appendChild(document.createTextNode(",\n      "));
            var keyLine = document.createElement('strong');
            keyLine.className = 'eeItemDimensionKeyLine';
            keyLine.textContent = name + ": 'value'";
            code.appendChild(keyLine);
        }
        code.appendChild(document.createTextNode("\n    }]\n  }\n});"));

        note.appendChild(heading);
        note.appendChild(text);
        note.appendChild(example);
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
