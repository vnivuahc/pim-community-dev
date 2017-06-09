/**
 * Basic view that simply renders a template.
 *
 * @author    Yohan Blain <yohan.blain@akeneo.com>
 * @copyright 2017 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
'use strict';

define([
    'jquery',
    'underscore',
    'backbone',
    'oro/translator',
    'pim/form'
], function (
    $,
    _,
    Backbone,
    __,
    BaseForm
) {
    return BaseForm.extend({
        config: {},
        template: null,

        /**
         * {@inheritdoc}
         */
        initialize: function (meta) {
            this.config = meta.config;

            BaseForm.prototype.initialize.apply(this, arguments);
        },

        /**
         * {@inheritdoc}
         *
         * Waiting for the template to be required.
         */
        configure: function () {
            if (undefined === this.config.template) {
                throw new Error('The view "' + this.code + '" must be configured with a template.');
            }

            var promise = $.Deferred();

            require(['text!' + this.config.template], function (template) {
                this.template = template;
                promise.resolve().promise();
            }.bind(this));

            return $.when(
                promise,
                BaseForm.prototype.configure.apply(this, arguments)
            );
        },

        /**
         * {@inheritdoc}
         */
        render: function () {
            var templateParams = this.config.templateParams || {};
            templateParams = _.extend({}, {__: __}, templateParams);

            this.$el.html(
                _.template(this.template)(templateParams)
            );

            this.renderExtensions();
        }
    });
});
