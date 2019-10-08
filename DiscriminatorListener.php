<?php


namespace RHarink\Doctrine\DiscriminatorEntry;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\Common\EventSubscriber;

/**
 * This Listener listens to the loadClassMetadata event. Upon this event
 * it hooks into Doctrine to update discriminator maps. Adding entries
 * to the discriminator map at parent level is just not nice. We turn this
 * around with this mechanism. In the subclass you will be able to give an
 * entry for the discriminator map. In this listener we will retrieve the
 * load metadata event to update the parent with a good discriminator map,
 * collecting all entries from the subclasses.
 */
class DiscriminatorListener implements EventSubscriber
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var array
     */
    private $map;

    /**
     * @var array
     */
    private $cachedMap;

    /**
     * Annotation class
     */
    const ENTRY_ANNOTATION = DiscriminatorEntry::class;

    /**
     * @return array|string[]
     */
    public function getSubscribedEvents()
    {
        return [\Doctrine\ORM\Events::loadClassMetadata];
    }

    /**
     * DiscriminatorListener constructor.
     * @param EntityManagerInterface $em
     * @throws \Doctrine\ORM\ORMException
     */
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em->getConfiguration()->getMetadataDriverImpl();
        $this->cachedMap = [];
    }

    /**
     *
     * @param $class
     * @return array
     * @throws \ReflectionException
     */
    public static function getSubClasses($class)
    {
        $subclasses = array();
        foreach(get_declared_classes() as $potentialSubclass)
        {
            $reflection = new \ReflectionClass($potentialSubclass);
            if($reflection ->isSubclassOf($class)){
                $subclasses[] = $potentialSubclass;
            }
        }
        return $subclasses;
    }

    /**
     * @param LoadClassMetadataEventArgs $event
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $event )
    {
        // Reset the temporary calculation map and get the classname
        $this->map  = [];
        $class      = $event->getClassMetadata()->name;

        // TODO(rharink): Measure impact.
        // Limit it to the entities we have control over, this should shave off some load time.
        //$reflection = new \ReflectionClass($class);
        //if(!$reflection->isSubclassOf(<SOME CLASS HERE>)){
            //return;
        //}

        // Did we already calculate the map for this element?
        if(array_key_exists($class, $this->cachedMap)) {
            $this->overrideMetadata($event, $class);
            return;
        }

        // Do we have to process this class?
        if(count($event->getClassMetadata()->discriminatorMap) && $this->extractEntry($class)) {
            $this->checkFamily($class);
        } else {
            return;
        }

        // Create the lookup entries
        $dMap = array_flip( $this->map );
        foreach( $this->map as $cName => $discr ) {
            $this->cachedMap[$cName]['map']     = $dMap;
            $this->cachedMap[$cName]['discr']   = $this->map[$cName];
        }

        // Override the data for this class
        $this->overrideMetadata($event, $class);
    }

    private function overrideMetadata( LoadClassMetadataEventArgs $event, $class )
    {
        // Set the discriminator map and value
        $event->getClassMetadata()->discriminatorMap    = $this->cachedMap[$class]['map'];
        $event->getClassMetadata()->discriminatorValue  = $this->cachedMap[$class]['discr'];

        // If we are the top-most parent, set subclasses!
        if(isset($this->cachedMap[$class]['isParent']) && $this->cachedMap[$class]['isParent'] === true) {
            $subclasses = $this->cachedMap[$class]['map'];
            unset( $subclasses[$this->cachedMap[$class]['discr']] );
            if(!is_array($subclasses)){
                $subclasses = [];
            }
            $event->getClassMetadata()->subClasses = array_values( $subclasses );
        }
    }

    private function checkFamily($class)
    {
        $ref = new \ReflectionClass($class);
        $parent = $ref->getParentClass();

        if ($parent) {
            $this->checkFamily($parent->name);
        } else {
            // This is the top-most parent, used in overrideMetadata
            $this->cachedMap[$class]['isParent'] = true;
            // Find all the children of this class
            $this->checkChildren($class);
        }
    }

    private function checkChildren($class)
    {
        /*
        Because $this->driver->getAllClassNames() did not work, implemented own method to get subclasses
        attention, getSubClasses returns all Child Classes, not only the direct Child classes
        */
        foreach(static::getSubClasses($class) as $name) {
            $cRc = new \ReflectionClass($name);
            $cParent = $cRc->getParentClass()->name;
            // Haven't done this class yet? Go for it.
            //removed the check if it is a direct child. It does not really matter (and didn't work somehow)
            if(!array_key_exists($name, $this->map)  && $this->extractEntry($name))  {
                $this->checkChildren($name);
            }
        }
    }

    private function extractEntry($class)
    {
        $annotations = Annotation::getAnnotationsForClass($class);
        $success = false;
        foreach($annotations as $key => $annotation){
            if(get_class($annotation) == self::ENTRY_ANNOTATION){
                //TODO check for duplicates
                $this->map[$class] = $annotation->getValue();
                $success = true;
            }
        }

        return $success;
    }
}