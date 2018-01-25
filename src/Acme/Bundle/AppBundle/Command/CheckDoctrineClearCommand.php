<?php

namespace Acme\Bundle\AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckDoctrineClearCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('acme:doctrine:check-clear');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $productRepo = $this->getContainer()->get('pim_catalog.repository.product');
        $entityManager = $this->getContainer()->get('doctrine.orm.default_entity_manager');

        $productA = $productRepo->findOneById(1);
        $output->writeln("Product A".$productA->getIdentifier());

        $entityManager->clear();

        $productB = $productRepo->findOneById(1238);
        $output->writeln("Product B".$productB->getIdentifier());
        $output->writeln("Product B First value ".$productB->getParent()->getCode());

        $output->writeln("Product A First value ".$productA->getParent()->getCode());
    }

}
