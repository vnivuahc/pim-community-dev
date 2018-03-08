<?php

declare(strict_types=1);

namespace Pim\Component\Connector\Job;

use Akeneo\Component\Batch\Item\InitializableInterface;
use Akeneo\Component\Batch\Item\InvalidItemException;
use Akeneo\Component\Batch\Item\ItemReaderInterface;
use Akeneo\Component\Batch\Job\JobRepositoryInterface;
use Akeneo\Component\Batch\Model\StepExecution;
use Akeneo\Component\StorageUtils\Cache\CacheClearerInterface;
use Akeneo\Component\StorageUtils\Cursor\CursorInterface;
use Akeneo\Component\StorageUtils\Detacher\ObjectDetacherInterface;
use Akeneo\Component\StorageUtils\Saver\BulkSaverInterface;
use Pim\Component\Catalog\Model\FamilyInterface;
use Pim\Component\Catalog\Model\ProductInterface;
use Pim\Component\Catalog\Query\Filter\Operators;
use Pim\Component\Catalog\Query\ProductQueryBuilderFactoryInterface;
use Pim\Component\Catalog\Repository\FamilyRepositoryInterface;
use Pim\Component\Connector\Step\TaskletInterface;

/**
 * Foreach line of the file to import we will:
 * - fetch the corresponding family object
 * - fetch all the products of this family
 * - save this product
 * This way on family import the family's product completeness will be computed
 *
 * @author    Olivier Soulet <olivier.soulet@akeneo.com>
 * @copyright 2018 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ComputeDataRelatedToFamilyProductsTasklet implements TaskletInterface, InitializableInterface
{
    /** @var StepExecution */
    private $stepExecution;

    /** @var ItemReaderInterface */
    private $familyReader;

    /** @var FamilyRepositoryInterface */
    private $familyRepository;

    /** @var BulkSaverInterface */
    private $productSaver;

    /** @var CacheClearerInterface */
    private $cacheClearer;

    /** @var ProductQueryBuilderFactoryInterface */
    private $productQueryBuilderFactory;

    /** @var JobRepositoryInterface */
    private $jobRepository;

    /** @var ObjectDetacherInterface */
    private $objectDetacher;

    /**
     * @param FamilyRepositoryInterface           $familyRepository
     * @param ProductQueryBuilderFactoryInterface $productQueryBuilderFactory
     * @param ItemReaderInterface                 $familyReader
     * @param BulkSaverInterface                  $productSaver
     * @param CacheClearerInterface               $cacheClearer
     * @param JobRepositoryInterface              $jobRepository
     */
    public function __construct(
        FamilyRepositoryInterface $familyRepository,
        ProductQueryBuilderFactoryInterface $productQueryBuilderFactory,
        ItemReaderInterface $familyReader,
        BulkSaverInterface $productSaver,
        ObjectDetacherInterface $objectDetacher,
        CacheClearerInterface $cacheClearer,
        JobRepositoryInterface $jobRepository
    ) {
        $this->familyReader = $familyReader;
        $this->familyRepository = $familyRepository;
        $this->productQueryBuilderFactory = $productQueryBuilderFactory;
        $this->productSaver = $productSaver;
        $this->cacheClearer = $cacheClearer;
        $this->jobRepository = $jobRepository;
        $this->objectDetacher = $objectDetacher;
    }

    /**
     * @param StepExecution $stepExecution
     */
    public function setStepExecution(StepExecution $stepExecution)
    {
        $this->stepExecution = $stepExecution;
    }

    /**
     * Execute the tasklet
     */
    public function execute()
    {
        $this->initialize();

        while (true) {
            try {
                $familyItem = $this->familyReader->read();
                if (null === $familyItem) {
                    break;
                }
            } catch (InvalidItemException $e) {
                continue;
            }

            $family = $this->familyRepository->findOneByIdentifier($familyItem['code']);
            if (null === $family) {
                $this->stepExecution->incrementSummaryInfo('skip');
                continue;
            }

            $products = $this->getProductsForFamily($family);
            foreach ($products as $product) {
                $this->saveEntity($product);
                $this->stepExecution->incrementSummaryInfo('process');
                $this->updateStepExecution();

                $this->objectDetacher->detach($product);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function initialize()
    {
        $this->cacheClearer->clear();
    }

    /**
     * @param FamilyInterface $family
     *
     * @return CursorInterface
     */
    private function getProductsForFamily(FamilyInterface $family): CursorInterface
    {
        $pqb = $this->productQueryBuilderFactory->create();
        $pqb->addFilter('family', Operators::IN_LIST, [$family->getCode()]);

        return $pqb->execute();
    }

    /**
     * @param ProductInterface $product
     */
    private function saveEntity(ProductInterface $product): void
    {
        $this->productSaver->saveAll([$product]);
    }

    /**
     * Update the step execution to make sure the progress is shown in the UI.
     */
    private function updateStepExecution(): void
    {
        $this->jobRepository->updateStepExecution($this->stepExecution);
    }
}
