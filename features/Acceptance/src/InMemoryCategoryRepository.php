<?php

namespace Pim\Acceptance\src;

use Akeneo\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface;
use Akeneo\Component\StorageUtils\Saver\SaverInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class InMemoryCategoryRepository implements IdentifiableObjectRepositoryInterface, SaverInterface
{
    /** @var Collection */
    private $categories;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
    }

    public function getIdentifierProperties()
    {
        return ['code'];
    }

    public function findOneByIdentifier($code)
    {
        return $this->categories->get($code);
    }

    public function save($category, array $options = [])
    {
        $this->categories->set($category->getCode(), $category);
    }
}
