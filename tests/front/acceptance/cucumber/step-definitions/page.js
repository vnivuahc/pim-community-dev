const {Given, Then} = require('cucumber');
const {answerJson} = require('./tools');
const assert = require('assert');

Then('the title of the page should be {string}', async function (string) {
    const titleElement = await this.page.waitForSelector('.AknTitleContainer-title');
    const pageTitle = await (await titleElement.getProperty('innerText')).jsonValue();
    assert.equal(pageTitle.trim(), string);
});