(function () {
    return function (parameters, TagManager) {

        // Same as the core Matomo Configuration variable, plus the Ecommerce Enhanced option
        this.get = function () {
            var config = {};
            for (var i in parameters) {
                if (i === 'document' || i === 'window' || i === 'container' || i === 'variable' || TagManager.utils.isFunction(parameters[i])) {
                    continue;
                }

                if (TagManager.utils.hasProperty(parameters, i)) {
                    config[i] = parameters.get(i);
                }
            }

            if (config.enableEcommerceEnhanced) {
                var win = parameters.window;
                if (win.Matomo && win.Matomo.EcommerceEnhanced) {
                    win.Matomo.EcommerceEnhanced.enable();
                } else {
                    win._paq = win._paq || [];
                    win._paq.push(['EcommerceEnhanced::enable']);
                }
            }

            return config;
        };

        this.toString = function () {
            return '';
        };
    };
})();
