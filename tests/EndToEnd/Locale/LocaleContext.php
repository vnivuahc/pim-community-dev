<?php


namespace Pim\EndToEnd\Locale;


use Behat\Behat\Context\Context;
use Behat\ChainedStepsExtension\Step\Then;
use Behat\ChainedStepsExtension\Step\When;

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
        $steps = [];
        $steps[] = new When('And I filter by "activated" with operator "" and value "yes"');
        $steps[] = new Then('Then the grid should contain 2 elements');

        return $steps;
    }
}