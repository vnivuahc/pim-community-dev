<?php

namespace Pim\Bundle\ImportExportBundle\Reader;

use Doctrine\ORM\EntityManager;
use Pim\Bundle\ConfigBundle\Manager\ChannelManager;

/**
 * Category reader
 *
 * @author    Filips Alpe <filips@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class CategoryReader extends ORMCursorReader
{
    protected $em;

    /**
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * {@inheritDoc}
     */
    public function read()
    {
        if (!$this->query) {
            $this->query = $em
                ->getRepository('PimProductBundle:Category')
                ->buildAll();
        }

        return parent::read();
    }
}
