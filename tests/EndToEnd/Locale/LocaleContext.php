<?php


namespace Pim\EndToEnd\Locale;


use Behat\Behat\Context\Context;

class LocaleContext implements Context
{
    /**
     * @Given /^the following locales? "([^"]*)"$/
     */
    public function theFollowingLocale(string $localeCodes)
    {

    }

    /**
     * @Then /^I should have activated locales "([^"]*)"$/
     */
    public function iShouldHaveActivatedLocales(string $localeCodes)
    {

    }
}