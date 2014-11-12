<?php

namespace Pim\Bundle\CatalogBundle\Saver;

use Pim\Component\Resource\Model\SaverInterface;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Util\ClassUtils;

/**
 * Base saver, declared as different services for different classes
 *
 * @author    Nicolas Dupont <nicolas@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class BaseSaver implements SaverInterface
{
    /** @var ManagerRegistry */
    protected $registry;

    /** @var string */
    protected $savedClass;

    /**
     * @param ManagerRegistry $registry
     * @param string          $savedClass
     */
    public function __construct(ManagerRegistry $registry, $savedClass)
    {
        $this->registry   = $registry;
        $this->savedClass = $savedClass;
    }

    /**
     * {@inheritdoc}
     */
    public function save($object, array $options = [])
    {
        if (!$object instanceof $this->savedClass) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Expects a "%s", "%s" provided.',
                    $this->savedClass,
                    ClassUtils::getClass($object)
                )
            );
        }

        $options = array_merge(['flush' => true], $options);
        $objectManager = $this->registry->getManagerForClass($this->savedClass);
        $objectManager->persist($object);
        if (true === $options['flush']) {
            $objectManager->flush();
        }
    }
}
