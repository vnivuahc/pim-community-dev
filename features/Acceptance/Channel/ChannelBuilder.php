<?php

declare(strict_types=1);

namespace Pim\Acceptance\Channel;

use Akeneo\Component\StorageUtils\Factory\SimpleFactoryInterface;
use Akeneo\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Pim\Component\Catalog\Model\ChannelInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ChannelBuilder
{
    /**
     * @var SimpleFactoryInterface
     */
    private $channelFactory;
    /**
     * @var ObjectUpdaterInterface
     */
    private $channelUpdater;
    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @param SimpleFactoryInterface $channelFactory
     * @param ObjectUpdaterInterface $channelUpdater
     * @param ValidatorInterface     $validator
     */
    public function __construct(SimpleFactoryInterface $channelFactory, ObjectUpdaterInterface $channelUpdater, ValidatorInterface $validator)
    {
        $this->channelFactory = $channelFactory;
        $this->channelUpdater = $channelUpdater;
        $this->validator = $validator;
    }

    /**
     * @param array $channelData
     * @return ChannelInterface
     * @throws \Exception
     */
    public function build(array $channelData): ChannelInterface
    {
        $channel = $this->channelFactory->create();
        $this->channelUpdater->update($channel, $channelData);
        $errors = $this->validator->validate($channel);

        if (0 !== $errors->count()) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = sprintf(
                    "\n- property path: %s\n- message: %s",
                    $error->getPropertyPath(),
                    $error->getMessage()
                );
            }

            throw new \Exception("An error occurred on channel creation:" . implode("\n", $errorMessages));
        }

        return $channel;
    }
}