<?php

declare(strict_types=1);

namespace Pim\Behat\Context;

use Akeneo\Component\StorageUtils\Factory\SimpleFactoryInterface;
use Akeneo\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface;
use Akeneo\Component\StorageUtils\Saver\SaverInterface;
use Akeneo\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Behat\Behat\Context\Context as BehatContext;
use PHPUnit\Framework\Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AcceptanceContext implements BehatContext
{
    /** @var SimpleFactoryInterface */
    private $localeFactory;
    /** @var ObjectUpdaterInterface */
    private $localeUpdater;
    /** @var SaverInterface */
    private $localeSaver;
    /** @var ValidatorInterface */
    private $validator;
    /** @var SimpleFactoryInterface */
    private $channelFactory;
    /** @var ObjectUpdaterInterface */
    private $channelUpdater;
    /** @var SaverInterface */
    private $channelSaver;
    /** @var IdentifiableObjectRepositoryInterface */
    private $localeRepository;
    /** @var IdentifiableObjectRepositoryInterface */
    private $channelRepository;

    public function __construct(
        SimpleFactoryInterface $localeFactory,
        ObjectUpdaterInterface $localeUpdater,
        SaverInterface $localeSaver,
        SimpleFactoryInterface $channelFactory,
        ObjectUpdaterInterface $channelUpdater,
        SaverInterface $channelSaver,
        IdentifiableObjectRepositoryInterface $localeRepository,
        IdentifiableObjectRepositoryInterface $channelRepository,
        ValidatorInterface $validator
    ) {
        $this->localeFactory = $localeFactory;
        $this->localeUpdater = $localeUpdater;
        $this->localeSaver = $localeSaver;
        $this->channelFactory = $channelFactory;
        $this->channelUpdater = $channelUpdater;
        $this->channelSaver = $channelSaver;
        $this->localeRepository = $localeRepository;
        $this->channelRepository = $channelRepository;
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
                $this->localeSaver->save($locale);
            }
        }
    }

    /**
     * @Given /^the following "([^"]*)" channel with locales? "([^"]*)"$/
     */
    public function theFollowingChannel(string $channelCode, string $localeCodes)
    {
        $channelData = [
            'code' => $channelCode,
            'locales' => explode(',', $localeCodes)
        ];

        $channel = $this->channelFactory->create();
        $this->channelUpdater->update($channel, $channelData);
        $errors = $this->validator->validate($channel);

        if (0 !== $errors->count()) {
            foreach ($errors as $error) {
                throw new \Exception(
                    sprintf(
                        "An error occurred on fixtures installation:\n- property path: %s\n- message: %s",
                        $error->getPropertyPath(),
                        $error->getMessage()
                    )
                );
            }
        } else {
            $this->channelSaver->save($channel);
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

    /**
     * @Then /^I remove the locale "([^"]*)" from the "([^"]*)" channel$/
     */
    public function iRemoveTheLocaleFromTheChannel(string $localeCode, string $channelCode)
    {
        $channel = $this->channelRepository->findOneByIdentifier($channelCode);
        if (null === $channel) {
            throw new \Exception(sprintf('Channel "%s" not found', $channelCode));
        }

        $locale = $this->localeRepository->findOneByIdentifier($localeCode);
        if (null === $locale) {
            throw new \Exception(sprintf('Locale "%s" not found', $localeCode));
        }

        $channel->removeLocale($locale);
        $this->channelSaver->save($channel);
    }
}
