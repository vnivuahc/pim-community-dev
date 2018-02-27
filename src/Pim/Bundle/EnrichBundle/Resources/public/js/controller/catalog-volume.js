'use strict';

define(
    [
        'jquery',
        'underscore',
        'oro/translator',
        'pim/controller/front',
        'pim/form-builder',
        'pim/page-title',
        'routing'
    ],
    function ($, _, __, BaseController, FormBuilder, PageTitle, Routing) {
        return BaseController.extend({
            /**
             * {@inheritdoc}
             */
            renderForm: function () {
                return $.when(
                    FormBuilder.build('pim-catalog-volume-index'),
                    // $.get(Routing.generate('oro_config_configuration_system_get'))
                ).then((form, response) => {
                    this.on('pim:controller:can-leave', function (event) {
                        form.trigger('pim_enrich:form:can-leave', event);
                    });


                    const dummyData = {

                    };

                    form.setData(dummyData);
                    form.setElement(this.$el).render();

                    return form;
                });
            }
        });
    }
);