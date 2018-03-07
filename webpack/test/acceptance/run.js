const runner = require('./cucumber-runner');
const cucumber = require('cucumber');

runner(cucumber, ['./tests/front/acceptance/cucumber/step-definitions/']);