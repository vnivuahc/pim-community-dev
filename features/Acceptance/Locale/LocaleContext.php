<?php

declare(strict_types=1);

namespace Pim\Acceptance\Locale;

use Akeneo\Component\StorageUtils\Factory\SimpleFactoryInterface;
use Akeneo\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Behat\Behat\Context\Context as BehatContext;
use PHPUnit\Framework\Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class LocaleContext implements BehatContext
{
    private $localeRepository;
    /**
     * @var LocaleBuilder
     */
    private $localeBuilder;

    public function __construct(
        InMemoryLocaleRepository $localeRepository,
        LocaleBuilder $localeBuilder
    ) {
        $this->localeRepository = $localeRepository;
        $this->localeBuilder = $localeBuilder;
    }

    /**
     * @Given /^the following locales? "([^"]*)"$/
     */
    public function theFollowingLocale(string $localeCodes)
    {
        $localeCodes = explode(',', $localeCodes);
        foreach ($localeCodes as $localeCode) {
            $localeCode = trim($localeCode);

            $locale = $this->localeBuilder->build(['code' => $localeCode]);

            $this->localeRepository->save($locale);
        }
    }

    /**
     * @Then /^I should have activated locales "([^"]*)"$/
     */
    public function iShouldHaveActivatedLocales(string $localeCodes)
    {
        foreach (explode(',', $localeCodes) as $localeCode) {
            $locale = $this->localeRepository->findOneByIdentifier($localeCode);
            Assert::assertTrue($locale->isActivated());
        }
    }
}
