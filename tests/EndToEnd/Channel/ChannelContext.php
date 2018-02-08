<?php


namespace Pim\EndToEnd\Channel;


use Behat\Behat\Context\Context;
use Behat\ChainedStepsExtension\Step\Given;
use Behat\ChainedStepsExtension\Step\When;
use Behat\Behat\Hook\Call\BeforeScenario;

class ChannelContext implements Context
{

    /**
     * @Given the following channel with locales
     */
    public function theFollowingChannel()
    {
        $steps = [new When('I set the "English (United States), French (France)" locales to the "ecommerce" channel')];

        return $steps;
    }

    /**
     * @Then /^I remove the locale "([^"]*)" from the "([^"]*)" channel$/
     */
    public function iRemoveTheLocaleFromTheChannel(string $localeCode, string $channelCode)
    {
        $steps = [new When('I set the "English (United States)" locales to the "ecommerce" channel')];

        return $steps;
    }

    /**
     * @When I add the locale :localeCode from the :channelCode channel
     */
    public function iAddTheLocaleFromTheChannel($localeCode, $channelCode)
    {
        $steps = [new When('I set the "English (United States), French (France)" locales to the "ecommerce" channel')];

        return $steps;
    }

    /**
     * @BeforeScenario
     */
    public function beforeScenario()
    {
        $steps = [];
        $steps[] = new Given('a "default" catalog configuration');
        $steps[] = new Given('I am logged in as "admin"');

        return $steps;

    }
}