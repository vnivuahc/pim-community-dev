<?php

declare(strict_types=1);

namespace Pim\Acceptance\Locale;

use Akeneo\Component\StorageUtils\Factory\SimpleFactoryInterface;
use Akeneo\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Behat\Behat\Context\Context as BehatContext;
use PHPUnit\Framework\Assert;
use Pim\Acceptance\src\InMemoryLocaleRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class LocaleContext implements BehatContext
{
    private $localeFactory;
    private $localeUpdater;
    private $localeRepository;
    private $validator;

    public function __construct(
        SimpleFactoryInterface $localeFactory,
        ObjectUpdaterInterface $localeUpdater,
        InMemoryLocaleRepository $localeRepository,
        ValidatorInterface $validator
    ) {
        $this->localeFactory = $localeFactory;
        $this->localeUpdater = $localeUpdater;
        $this->localeRepository = $localeRepository;
        $this->validator = $validator;
    }

    /**
     * @Given /^the following locales? "([^"]*)"$/
     */
    public function theFollowingLocale(string $localeCodes)
    {
        $localeCodes = explode(',', $localeCodes);
        foreach ($localeCodes as $localeCode) {
            $localeCode = trim($localeCode);

            $locale = $this->localeFactory->create();
            $this->localeUpdater->update($locale, ['code' => $localeCode]);
            $errors = $this->validator->validate($locale);

            if (0 !== $errors->count()) {
                foreach ($errors as $error) {
                    throw new \Exception(
                        sprintf(
                            'An error occurred on fixtures installation: path: %s, message: %s',
                            $error->getPropertyPath(),
                            $error->getMessage()
                        )
                    );
                }
            } else {
                $this->localeRepository->save($locale);
            }
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
