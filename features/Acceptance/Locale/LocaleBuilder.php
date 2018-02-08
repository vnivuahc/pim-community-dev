<?php

declare(strict_types=1);

namespace Pim\Acceptance\Locale;

use Akeneo\Component\StorageUtils\Factory\SimpleFactoryInterface;
use Akeneo\Component\StorageUtils\Updater\ObjectUpdaterInterface;
use Pim\Component\Catalog\Model\LocaleInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class LocaleBuilder
{
    /**
     * @var SimpleFactoryInterface
     */
    private $localeFactory;
    /**
     * @var ObjectUpdaterInterface
     */
    private $localeUpdater;
    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @param SimpleFactoryInterface $localeFactory
     * @param ObjectUpdaterInterface $localeUpdater
     * @param ValidatorInterface     $validator
     */
    public function __construct(SimpleFactoryInterface $localeFactory, ObjectUpdaterInterface $localeUpdater, ValidatorInterface $validator)
    {
        $this->localeFactory = $localeFactory;
        $this->localeUpdater = $localeUpdater;
        $this->validator = $validator;
    }

    /**
     * @param array $localeData
     * @return LocaleInterface
     * @throws \Exception
     */
    public function build(array $localeData): LocaleInterface
    {
        $locale = $this->localeFactory->create();
        $this->localeUpdater->update($locale, $localeData);
        $errors = $this->validator->validate($locale);

        if (0 !== $errors->count()) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = sprintf(
                    "\n- property path: %s\n- message: %s",
                    $error->getPropertyPath(),
                    $error->getMessage()
                );
            }

            throw new \Exception("An error occurred on locale creation:" . implode("\n", $errorMessages));
        }

        return $locale;
    }
}