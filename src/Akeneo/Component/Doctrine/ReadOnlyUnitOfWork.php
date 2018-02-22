<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Akeneo\Component\Doctrine\Orm;

use Doctrine\Common\Persistence\Mapping\RuntimeReflectionService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Internal\HydrationCompleteHandler;
use Doctrine\ORM\Mapping\Reflection\ReflectionPropertiesGetter;
use Exception;
use InvalidArgumentException;
use UnexpectedValueException;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\NotifyPropertyChanged;
use Doctrine\Common\PropertyChangedListener;
use Doctrine\Common\Persistence\ObjectManagerAware;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Proxy\Proxy;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\ListenersInvoker;

use Doctrine\ORM\Cache\Persister\CachedPersister;
use Doctrine\ORM\Persisters\Entity\BasicEntityPersister;
use Doctrine\ORM\Persisters\Entity\SingleTablePersister;
use Doctrine\ORM\Persisters\Entity\JoinedSubclassPersister;
use Doctrine\ORM\Persisters\Collection\OneToManyPersister;
use Doctrine\ORM\Persisters\Collection\ManyToManyPersister;
use Doctrine\ORM\Utility\IdentifierFlattener;
use Doctrine\ORM\Cache\AssociationCacheEntry;

/**
 * The UnitOfWork is responsible for tracking changes to objects during an
 * "object-level" transaction and for writing out changes to the database
 * in the correct order.
 *
 * Internal note: This class contains highly performance-sensitive code.
 *
 * @since       2.0
 * @author      Benjamin Eberlei <kontakt@beberlei.de>
 * @author      Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author      Jonathan Wage <jonwage@gmail.com>
 * @author      Roman Borschel <roman@code-factory.org>
 * @author      Rob Caiger <rob@clocal.co.uk>
 */
class UnitOfWork implements PropertyChangedListener
{
    /**
     * The EntityManager that "owns" this UnitOfWork instance.
     *
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * The EventManager used for dispatching events.
     *
     * @var \Doctrine\Common\EventManager
     */
    private $evm;

    /**
     * The ListenersInvoker used for dispatching events.
     *
     * @var \Doctrine\ORM\Event\ListenersInvoker
     */
    private $listenersInvoker;

    /**
     * The IdentifierFlattener used for manipulating identifiers
     *
     * @var \Doctrine\ORM\Utility\IdentifierFlattener
     */
    private $identifierFlattener;

    /**
     * Map of Entity Class-Names and corresponding IDs that should eager loaded when requested.
     *
     * @var array
     */
    private $eagerLoadingEntities = array();

    /**
     * @var boolean
     */
    protected $hasCache = false;

    /**
     * Helper for handling completion of hydration
     *
     * @var HydrationCompleteHandler
     */
    private $hydrationCompleteHandler;

    /**
     * @var ReflectionPropertiesGetter
     */
    private $reflectionPropertiesGetter;

    /**
     * Initializes a new UnitOfWork instance, bound to the given EntityManager.
     *
     * @param EntityManagerInterface $em
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em                         = $em;
        $this->evm                        = $em->getEventManager();
        $this->listenersInvoker           = new ListenersInvoker($em);
        $this->hasCache                   = $em->getConfiguration()->isSecondLevelCacheEnabled();
        $this->identifierFlattener        = new IdentifierFlattener($this, $em->getMetadataFactory());
        $this->hydrationCompleteHandler   = new HydrationCompleteHandler($this->listenersInvoker, $em);
        $this->reflectionPropertiesGetter = new ReflectionPropertiesGetter(new RuntimeReflectionService());
    }

    /**
     * @param ClassMetadata $class
     *
     * @return \Doctrine\Common\Persistence\ObjectManagerAware|object
     */
    private function newInstance($class)
    {
        $entity = $class->newInstance();

        if ($entity instanceof \Doctrine\Common\Persistence\ObjectManagerAware) {
            $entity->injectObjectManager($this->em, $class);
        }

        return $entity;
    }

    /**
     * INTERNAL:
     * Creates an entity. Used for reconstitution of persistent entities.
     *
     * Internal note: Highly performance-sensitive method.
     *
     * @ignore
     *
     * @param string $className The name of the entity class.
     * @param array  $data      The data for the entity.
     * @param array  $hints     Any hints to account for during reconstitution/lookup of the entity.
     *
     * @return object The managed entity instance.
     *
     * @todo Rename: getOrCreateEntity
     */
    public function createEntity($className, array $data, &$hints = array())
    {
        $class = $this->em->getClassMetadata($className);
        //$isReadOnly = isset($hints[Query::HINT_READ_ONLY]);

        $id = $this->identifierFlattener->flattenIdentifier($class, $data);
        $idHash = implode(' ', $id);

        if (isset($this->identityMap[$class->rootEntityName][$idHash])) {
            $entity = $this->identityMap[$class->rootEntityName][$idHash];
            $oid = spl_object_hash($entity);

            if (
                isset($hints[Query::HINT_REFRESH])
                && isset($hints[Query::HINT_REFRESH_ENTITY])
                && ($unmanagedProxy = $hints[Query::HINT_REFRESH_ENTITY]) !== $entity
                && $unmanagedProxy instanceof Proxy
                && $this->isIdentifierEquals($unmanagedProxy, $entity)
            ) {
                // DDC-1238 - we have a managed instance, but it isn't the provided one.
                // Therefore we clear its identifier. Also, we must re-fetch metadata since the
                // refreshed object may be anything

                foreach ($class->identifier as $fieldName) {
                    $class->reflFields[$fieldName]->setValue($unmanagedProxy, null);
                }

                return $unmanagedProxy;
            }

            if ($entity instanceof Proxy && ! $entity->__isInitialized()) {
                $entity->__setInitialized(true);

                $overrideLocalValues = true;

                if ($entity instanceof NotifyPropertyChanged) {
                    $entity->addPropertyChangedListener($this);
                }
            } else {
                $overrideLocalValues = isset($hints[Query::HINT_REFRESH]);

                // If only a specific entity is set to refresh, check that it's the one
                if (isset($hints[Query::HINT_REFRESH_ENTITY])) {
                    $overrideLocalValues = $hints[Query::HINT_REFRESH_ENTITY] === $entity;
                }
            }

            if ($overrideLocalValues) {
                // inject ObjectManager upon refresh.
                if ($entity instanceof ObjectManagerAware) {
                    $entity->injectObjectManager($this->em, $class);
                }

                $this->originalEntityData[$oid] = $data;
            }
        } else {
            $entity = $this->newInstance($class);
            $oid    = spl_object_hash($entity);

            $this->entityIdentifiers[$oid]  = $id;
            $this->entityStates[$oid]       = self::STATE_MANAGED;
            $this->originalEntityData[$oid] = $data;

            $this->identityMap[$class->rootEntityName][$idHash] = $entity;

            if ($entity instanceof NotifyPropertyChanged) {
                $entity->addPropertyChangedListener($this);
            }

            $overrideLocalValues = true;
        }

        if ( ! $overrideLocalValues) {
            return $entity;
        }

        foreach ($data as $field => $value) {
            if (isset($class->fieldMappings[$field])) {
                $class->reflFields[$field]->setValue($entity, $value);
            }
        }

        // Loading the entity right here, if its in the eager loading map get rid of it there.
        unset($this->eagerLoadingEntities[$class->rootEntityName][$idHash]);

        if (isset($this->eagerLoadingEntities[$class->rootEntityName]) && ! $this->eagerLoadingEntities[$class->rootEntityName]) {
            unset($this->eagerLoadingEntities[$class->rootEntityName]);
        }

        // Properly initialize any unfetched associations, if partial objects are not allowed.
        if (isset($hints[Query::HINT_FORCE_PARTIAL_LOAD])) {
            return $entity;
        }

        foreach ($class->associationMappings as $field => $assoc) {
            // Check if the association is not among the fetch-joined associations already.
            if (isset($hints['fetchAlias']) && isset($hints['fetched'][$hints['fetchAlias']][$field])) {
                continue;
            }

            $targetClass = $this->em->getClassMetadata($assoc['targetEntity']);

            switch (true) {
                case ($assoc['type'] & ClassMetadata::TO_ONE):
                    if ( ! $assoc['isOwningSide']) {

                        // use the given entity association
                        if (isset($data[$field]) && is_object($data[$field]) && isset($this->entityStates[spl_object_hash($data[$field])])) {

                            $this->originalEntityData[$oid][$field] = $data[$field];

                            $class->reflFields[$field]->setValue($entity, $data[$field]);
                            $targetClass->reflFields[$assoc['mappedBy']]->setValue($data[$field], $entity);

                            continue 2;
                        }

                        // Inverse side of x-to-one can never be lazy
                        $class->reflFields[$field]->setValue($entity, $this->getEntityPersister($assoc['targetEntity'])->loadOneToOneEntity($assoc, $entity));

                        continue 2;
                    }

                    // use the entity association
                    if (isset($data[$field]) && is_object($data[$field]) && isset($this->entityStates[spl_object_hash($data[$field])])) {
                        $class->reflFields[$field]->setValue($entity, $data[$field]);
                        $this->originalEntityData[$oid][$field] = $data[$field];

                        continue;
                    }

                    $associatedId = array();

                    // TODO: Is this even computed right in all cases of composite keys?
                    foreach ($assoc['targetToSourceKeyColumns'] as $targetColumn => $srcColumn) {
                        $joinColumnValue = isset($data[$srcColumn]) ? $data[$srcColumn] : null;

                        if ($joinColumnValue !== null) {
                            if ($targetClass->containsForeignIdentifier) {
                                $associatedId[$targetClass->getFieldForColumn($targetColumn)] = $joinColumnValue;
                            } else {
                                $associatedId[$targetClass->fieldNames[$targetColumn]] = $joinColumnValue;
                            }
                        } elseif ($targetClass->containsForeignIdentifier
                            && in_array($targetClass->getFieldForColumn($targetColumn), $targetClass->identifier, true)
                        ) {
                            // the missing key is part of target's entity primary key
                            $associatedId = array();
                            break;
                        }
                    }

                    if ( ! $associatedId) {
                        // Foreign key is NULL
                        $class->reflFields[$field]->setValue($entity, null);
                        $this->originalEntityData[$oid][$field] = null;

                        continue;
                    }

                    if ( ! isset($hints['fetchMode'][$class->name][$field])) {
                        $hints['fetchMode'][$class->name][$field] = $assoc['fetch'];
                    }

                    // Foreign key is set
                    // Check identity map first
                    // FIXME: Can break easily with composite keys if join column values are in
                    //        wrong order. The correct order is the one in ClassMetadata#identifier.
                    $relatedIdHash = implode(' ', $associatedId);

                    switch (true) {
                        case (isset($this->identityMap[$targetClass->rootEntityName][$relatedIdHash])):
                            $newValue = $this->identityMap[$targetClass->rootEntityName][$relatedIdHash];

                            // If this is an uninitialized proxy, we are deferring eager loads,
                            // this association is marked as eager fetch, and its an uninitialized proxy (wtf!)
                            // then we can append this entity for eager loading!
                            if ($hints['fetchMode'][$class->name][$field] == ClassMetadata::FETCH_EAGER &&
                                isset($hints[self::HINT_DEFEREAGERLOAD]) &&
                                !$targetClass->isIdentifierComposite &&
                                $newValue instanceof Proxy &&
                                $newValue->__isInitialized__ === false) {

                                $this->eagerLoadingEntities[$targetClass->rootEntityName][$relatedIdHash] = current($associatedId);
                            }

                            break;

                        case ($targetClass->subClasses):
                            // If it might be a subtype, it can not be lazy. There isn't even
                            // a way to solve this with deferred eager loading, which means putting
                            // an entity with subclasses at a *-to-one location is really bad! (performance-wise)
                            $newValue = $this->getEntityPersister($assoc['targetEntity'])->loadOneToOneEntity($assoc, $entity, $associatedId);
                            break;

                        default:
                            switch (true) {
                                // We are negating the condition here. Other cases will assume it is valid!
                                case ($hints['fetchMode'][$class->name][$field] !== ClassMetadata::FETCH_EAGER):
                                    $newValue = $this->em->getProxyFactory()->getProxy($assoc['targetEntity'], $associatedId);
                                    break;

                                // Deferred eager load only works for single identifier classes
                                case (isset($hints[self::HINT_DEFEREAGERLOAD]) && ! $targetClass->isIdentifierComposite):
                                    // TODO: Is there a faster approach?
                                    $this->eagerLoadingEntities[$targetClass->rootEntityName][$relatedIdHash] = current($associatedId);

                                    $newValue = $this->em->getProxyFactory()->getProxy($assoc['targetEntity'], $associatedId);
                                    break;

                                default:
                                    // TODO: This is very imperformant, ignore it?
                                    $newValue = $this->em->find($assoc['targetEntity'], $associatedId);
                                    break;
                            }

                            // PERF: Inlined & optimized code from UnitOfWork#registerManaged()
                            $newValueOid = spl_object_hash($newValue);
                            $this->entityIdentifiers[$newValueOid] = $associatedId;
                            $this->identityMap[$targetClass->rootEntityName][$relatedIdHash] = $newValue;

                            if (
                                $newValue instanceof NotifyPropertyChanged &&
                                ( ! $newValue instanceof Proxy || $newValue->__isInitialized())
                            ) {
                                $newValue->addPropertyChangedListener($this);
                            }
                            $this->entityStates[$newValueOid] = self::STATE_MANAGED;
                            // make sure that when an proxy is then finally loaded, $this->originalEntityData is set also!
                            break;
                    }

                    $this->originalEntityData[$oid][$field] = $newValue;
                    $class->reflFields[$field]->setValue($entity, $newValue);

                    if ($assoc['inversedBy'] && $assoc['type'] & ClassMetadata::ONE_TO_ONE) {
                        $inverseAssoc = $targetClass->associationMappings[$assoc['inversedBy']];
                        $targetClass->reflFields[$inverseAssoc['fieldName']]->setValue($newValue, $entity);
                    }

                    break;

                default:
                    // Ignore if its a cached collection
                    if (isset($hints[Query::HINT_CACHE_ENABLED]) && $class->getFieldValue($entity, $field) instanceof PersistentCollection) {
                        break;
                    }

                    // use the given collection
                    if (isset($data[$field]) && $data[$field] instanceof PersistentCollection) {

                        $data[$field]->setOwner($entity, $assoc);

                        $class->reflFields[$field]->setValue($entity, $data[$field]);
                        $this->originalEntityData[$oid][$field] = $data[$field];

                        break;
                    }

                    // Inject collection
                    $pColl = new PersistentCollection($this->em, $targetClass, new ArrayCollection);
                    $pColl->setOwner($entity, $assoc);
                    $pColl->setInitialized(false);

                    $reflField = $class->reflFields[$field];
                    $reflField->setValue($entity, $pColl);

                    if ($assoc['fetch'] == ClassMetadata::FETCH_EAGER) {
                        $this->loadCollection($pColl);
                        $pColl->takeSnapshot();
                    }

                    $this->originalEntityData[$oid][$field] = $pColl;
                    break;
            }
        }

        if ($overrideLocalValues) {
            // defer invoking of postLoad event to hydration complete step
            $this->hydrationCompleteHandler->deferPostLoadInvoking($class, $entity);
        }

        return $entity;
    }

    /**
     * @return void
     */
    public function triggerEagerLoads()
    {
        if ( ! $this->eagerLoadingEntities) {
            return;
        }

        // avoid infinite recursion
        $eagerLoadingEntities       = $this->eagerLoadingEntities;
        $this->eagerLoadingEntities = array();

        foreach ($eagerLoadingEntities as $entityName => $ids) {
            if ( ! $ids) {
                continue;
            }

            $class = $this->em->getClassMetadata($entityName);

            $this->getEntityPersister($entityName)->loadAll(
                array_combine($class->identifier, array(array_values($ids)))
            );
        }
    }

    /**
     * Initializes (loads) an uninitialized persistent collection of an entity.
     *
     * @param \Doctrine\ORM\PersistentCollection $collection The collection to initialize.
     *
     * @return void
     *
     * @todo Maybe later move to EntityManager#initialize($proxyOrCollection). See DDC-733.
     */
    public function loadCollection(PersistentCollection $collection)
    {
        $assoc     = $collection->getMapping();
        $persister = $this->getEntityPersister($assoc['targetEntity']);

        switch ($assoc['type']) {
            case ClassMetadata::ONE_TO_MANY:
                $persister->loadOneToManyCollection($assoc, $collection->getOwner(), $collection);
                break;

            case ClassMetadata::MANY_TO_MANY:
                $persister->loadManyToManyCollection($assoc, $collection->getOwner(), $collection);
                break;
        }

        $collection->setInitialized(true);
    }

    /**
     * Gets the EntityPersister for an Entity.
     *
     * @param string $entityName The name of the Entity.
     *
     * @return \Doctrine\ORM\Persisters\Entity\EntityPersister
     */
    public function getEntityPersister($entityName)
    {
        if (isset($this->persisters[$entityName])) {
            return $this->persisters[$entityName];
        }

        $class = $this->em->getClassMetadata($entityName);

        switch (true) {
            case ($class->isInheritanceTypeNone()):
                $persister = new BasicEntityPersister($this->em, $class);
                break;

            case ($class->isInheritanceTypeSingleTable()):
                $persister = new SingleTablePersister($this->em, $class);
                break;

            case ($class->isInheritanceTypeJoined()):
                $persister = new JoinedSubclassPersister($this->em, $class);
                break;

            default:
                throw new \RuntimeException('No persister found for entity.');
        }

        if ($this->hasCache && $class->cache !== null) {
            $persister = $this->em->getConfiguration()
                ->getSecondLevelCacheConfiguration()
                ->getCacheFactory()
                ->buildCachedEntityPersister($this->em, $persister, $class);
        }

        $this->persisters[$entityName] = $persister;

        return $this->persisters[$entityName];
    }

    /**
     * Gets a collection persister for a collection-valued association.
     *
     * @param array $association
     *
     * @return \Doctrine\ORM\Persisters\Collection\CollectionPersister
     */
    public function getCollectionPersister(array $association)
    {
        $role = isset($association['cache'])
            ? $association['sourceEntity'] . '::' . $association['fieldName']
            : $association['type'];

        if (isset($this->collectionPersisters[$role])) {
            return $this->collectionPersisters[$role];
        }

        $persister = ClassMetadata::ONE_TO_MANY === $association['type']
            ? new OneToManyPersister($this->em)
            : new ManyToManyPersister($this->em);

        if ($this->hasCache && isset($association['cache'])) {
            $persister = $this->em->getConfiguration()
                ->getSecondLevelCacheConfiguration()
                ->getCacheFactory()
                ->buildCachedCollectionPersister($this->em, $persister, $association);
        }

        $this->collectionPersisters[$role] = $persister;

        return $this->collectionPersisters[$role];
    }

    /**
     * Helper method to initialize a lazy loading proxy or persistent collection.
     *
     * @param object $obj
     *
     * @return void
     */
    public function initializeObject($obj)
    {
        if ($obj instanceof Proxy) {
            $obj->__load();

            return;
        }

        if ($obj instanceof PersistentCollection) {
            $obj->initialize();
        }
    }

    /**
     * Helper method to show an object as string.
     *
     * @param object $obj
     *
     * @return string
     */
    private static function objToStr($obj)
    {
        return method_exists($obj, '__toString') ? (string)$obj : get_class($obj).'@'.spl_object_hash($obj);
    }

    /**
     * Verifies if two given entities actually are the same based on identifier comparison
     *
     * @param object $entity1
     * @param object $entity2
     *
     * @return bool
     */
    private function isIdentifierEquals($entity1, $entity2)
    {
        if ($entity1 === $entity2) {
            return true;
        }

        $class = $this->em->getClassMetadata(get_class($entity1));

        if ($class !== $this->em->getClassMetadata(get_class($entity2))) {
            return false;
        }

        $oid1 = spl_object_hash($entity1);
        $oid2 = spl_object_hash($entity2);

        $id1 = isset($this->entityIdentifiers[$oid1])
            ? $this->entityIdentifiers[$oid1]
            : $this->identifierFlattener->flattenIdentifier($class, $class->getIdentifierValues($entity1));
        $id2 = isset($this->entityIdentifiers[$oid2])
            ? $this->entityIdentifiers[$oid2]
            : $this->identifierFlattener->flattenIdentifier($class, $class->getIdentifierValues($entity2));

        return $id1 === $id2 || implode(' ', $id1) === implode(' ', $id2);
    }

    /**
     * This method called by hydrators, and indicates that hydrator totally completed current hydration cycle.
     * Unit of work able to fire deferred events, related to loading events here.
     *
     * @internal should be called internally from object hydrators
     */
    public function hydrationComplete()
    {
        $this->hydrationCompleteHandler->hydrationComplete();
    }
}
