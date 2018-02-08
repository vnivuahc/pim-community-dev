<?php

declare(strict_types=1);

namespace Pim\Acceptance\Channel;

use Akeneo\Component\StorageUtils\Factory\SimpleFactoryInterface;
use Akeneo\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Behat\Behat\Context\Context as BehatContext;
use Pim\Acceptance\src\InMemoryCategoryRepository;
use Pim\Acceptance\src\InMemoryChannelRepository;
use Pim\Acceptance\src\InMemoryLocaleRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ChannelContext implements BehatContext
{
    protected $localeRepository;
    protected $channelFactory;
    protected $channelUpdater;
    protected $channelRepository;
    protected $validator;

    public function __construct(
        InMemoryLocaleRepository $localeRepository,
        InMemoryCategoryRepository $categoryRepository,
        SimpleFactoryInterface $channelFactory,
        ObjectUpdaterInterface $channelUpdater,
        InMemoryChannelRepository $channelRepository,
        ValidatorInterface $validator
    ) {
        $this->localeRepository = $localeRepository;
        $this->channelFactory = $channelFactory;
        $this->channelUpdater = $channelUpdater;
        $this->channelRepository = $channelRepository;
        $this->validator = $validator;
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
            $this->channelRepository->save($channel);
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
        $this->channelRepository->save($channel);
    }
}
