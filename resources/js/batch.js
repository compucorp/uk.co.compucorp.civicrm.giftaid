CRM.$(function($) {
    var container = $(document.getElementsByClassName('gift-aid'));

    /**
     * Base class
     *
     * @type {Object}
     */
    var Base = {
        /**
         * @param config
         * @returns {Object}
         */
        create: function (config) {
            var instance = Object.create(this);
            instance.config = config || {};

            return instance;
        },
        /**
         * Setup
         */
        setup: function () {
            this.configure();
            this.init();
        },
        /**
         * Set variables and other configurations to be used in the later stages
         */
        configure: function () {
        },
        /**
         * Initialise
         */
        init: function () {
        }
    };

    /**
     * Batch operations class
     */
    var BatchOperations = Base.create();

    BatchOperations.configure = function (config) {
        Base.configure(config);

        this.contributions = container.find('.contribution');
        this.lineItems = container.find('.line-items');
    };

    BatchOperations.init = function () {
        this.contributions.addClass('collapsed');
        this.lineItems.toggle();
    };

    BatchOperations.setup();
});
