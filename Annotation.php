<?php


namespace RHarink\Doctrine\DiscriminatorEntry;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\SimpleAnnotationReader;

class Annotation
{
    /**
     * @var SimpleAnnotationReader
     */
    public static $reader;

    /**
     * @param string $className
     * @return array
     * @throws \ReflectionException
     */
    public static function getAnnotationsForClass(string $className) {
        $class = new \ReflectionClass($className);
        return Annotation::$reader->getClassAnnotations($class);
    }
}

Annotation::$reader = new AnnotationReader();
