<?php

declare(strict_types=1);

namespace Akeneo\Bundle\BatchBundle\EventListener;

use Akeneo\Component\Batch\Event\EventInterface;
use Akeneo\Component\Batch\Event\StepExecutionEvent;
use Akeneo\Component\Batch\Item\ItemProcessorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reset the processed items saved into the job execution context after each batch executed during a step.
 *
 * @author    Laurent Petard <laurent.petard@akeneo.com>
 * @copyright 2018 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ResetProcessedItemsBatchSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            EventInterface::ITEM_STEP_AFTER_BATCH => 'reset',
        ];
    }

    public function reset(StepExecutionEvent $event)
    {
        $event->getStepExecution()->getExecutionContext()->remove(ItemProcessorInterface::PROCESSED_ITEMS_BATCH_CONTEXT_KEY);
    }
}

