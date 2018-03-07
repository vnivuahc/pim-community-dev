const {Given, Then} = require('cucumber');
const {answerJson} = require('../tools');
const assert = require('assert');

Given(/^the edit form for association type is displayed$/, async function() {
  await this.page.evaluate(() => {
    const FormBuilder = require('pim/form-builder');

    return FormBuilder.build('pim-association-type-edit-form').then((form) => {
        console.log('app', document.getElementById('app'))

        // createAssociationType for fixtures
        const data = {"code":"X_SELL","labels":{"en_US":"Cross sell","fr_FR":"Vente croisée"},"meta":{"id":1,"form":"pim-association-type-edit-form","model_type":"association_type","created":{"id":498,"author":"system - Removed user","resource_id":"1","snapshot":{"code":"X_SELL","label-en_US":"Cross sell","label-fr_FR":"Vente croisée"},"changeset":{"code":{"old":"","new":"X_SELL"},"label-en_US":{"old":"","new":"Cross sell"},"label-fr_FR":{"old":"","new":"Vente croisée"}},"context":null,"version":1,"logged_at":"03/07/2018 09:35 AM","pending":false},"updated":{"id":498,"author":"system - Removed user","resource_id":"1","snapshot":{"code":"X_SELL","label-en_US":"Cross sell","label-fr_FR":"Vente croisée"},"changeset":{"code":{"old":"","new":"X_SELL"},"label-en_US":{"old":"","new":"Cross sell"},"label-fr_FR":{"old":"","new":"Vente croisée"}},"context":null,"version":1,"logged_at":"03/07/2018 09:35 AM","pending":false}}}

        form.setData(data);
        // form.trigger('pim_enrich:form:entity:post_fetch', data);
        form.setElement(document.getElementById('app')).render();

        return form;
    })
  });

  await this.page.waitFor('.AknTitleContainer-title');
});