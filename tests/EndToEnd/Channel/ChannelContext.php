<?php


namespace Pim\EndToEnd\Channel;


use Behat\Behat\Context\Context;

class ChannelContext implements Context
{

    /**
     * @Given /^the following "([^"]*)" channel with locales? "([^"]*)"$/
     */
    public function theFollowingChannel(string $channelCode, string $localeCodes)
    {

    }

    /**
     * @Then /^I remove the locale "([^"]*)" from the "([^"]*)" channel$/
     */
    public function iRemoveTheLocaleFromTheChannel(string $localeCode, string $channelCode)
    {

    }

    /**
     * @When I add the locale :localeCode from the :channelCode channel
     */
    public function iAddTheLocaleFromTheChannel($localeCode, $channelCode)
    {

    }
}