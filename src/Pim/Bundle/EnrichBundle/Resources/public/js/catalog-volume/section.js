'use strict';

define(
    [
        'underscore',
        'pim/form',
        'oro/translator',
        'pim/template/catalog-volume/section'
    ],
    function (
        _,
        BaseForm,
        __,
        template
    ) {
        return BaseForm.extend({
            template: _.template(template),

            /**
             * @param {Object} meta
             */
            initialize: function (meta) {
                // this.config = _.extend({}, meta.config);
                // this.config.modelDependent = false;

                return BaseForm.prototype.initialize.apply(this, arguments);
            },

            /**
             * {@inheritdoc}
             */
            configure: function () {

                return BaseForm.prototype.configure.apply(this, arguments);
            },

            /**
             * {@inheritdoc}
             */
            render: function () {
                this.$el.html(this.template());

                this.renderExtensions();
            }
        });
    }
);
