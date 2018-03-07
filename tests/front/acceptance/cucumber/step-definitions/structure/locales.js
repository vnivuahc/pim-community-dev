const {Given, Then} = require('cucumber');
const {createLocale} = require('../fixtures');
const {answerJson, csvToArray} = require('../tools');

Given('the locales {string}', async function(csvLocaleCodes) {
  const locales = csvToArray(csvLocaleCodes).map(localeCode => createLocale(localeCode));
  this.page.on('request', request => {
    if (request.url().includes('/configuration/locale/rest')) {
      answerJson(request, locales);
    }
  });
});