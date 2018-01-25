<?php

namespace Acme\Bundle\AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckPersistentCollectionCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('acme:doctrine:check-persistent-collection');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $productRepo = $this->getContainer()->get('pim_catalog.repository.product');
        $entityManager = $this->getContainer()->get('doctrine.orm.default_entity_manager');

        $productA = $productRepo->findOneById(1);

        $output->writeln("Product A".$productA->getIdentifier());
        $output->writeln("First value ".$productA->getValues()->first()->getAttribute()->getCode());

        $productB = $productRepo->findOneById(1238);

        $entityManager->clear();

        $output->writeln("Product B".$productB->getIdentifier());
        $output->writeln("First value ".$productB->getValues()->first()->getAttribute()->getCode());
    }

}
