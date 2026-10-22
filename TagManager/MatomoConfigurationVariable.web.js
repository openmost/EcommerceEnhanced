(function () {
    // Extension of the Matomo Configuration variable, called with the config built by the core template (and by
    // the other plugins extending it): turns on Ecommerce Enhanced when the option is checked
    return function (config, parameters, TagManager) {
        if (!config.enableEcommerceEnhanced) {
            return;
        }

        var win = parameters.window;
        if (win.Matomo && win.Matomo.EcommerceEnhanced) {
            win.Matomo.EcommerceEnhanced.enable({tagManager: true});
        } else {
            win._paq = win._paq || [];
            win._paq.push(['EcommerceEnhanced::enable', {tagManager: true}]);
        }
    };
})();
