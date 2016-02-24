<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Mapbender\ManagerBundle\Component;

use Doctrine\Common\Persistence\Mapping\MappingException;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use Mapbender\CoreBundle\Utils\ArrayUtil;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * ExchangeNormalizer class normalizes objects to array.
 *
 * @author Paul Schmidt
 */
class ExchangeNormalizer extends ExchangeSerializer
{

    protected $em;

    protected $export;

    protected $inProcess;

    /**
     *
     * @param ContainerInterface $container container
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->em = $this->container->get('doctrine')->getManager();
        $this->export = array();
        $this->inProcess = array();
    }

    /**
     * @return array
     */
    public function getExport()
    {
        return $this->export;
    }

    /**
     * @param array $objectData
     * @param       ClassMetadata $classMeta
     * @return bool
     */
    private function isInProcess(array $objectData, ClassMetadata $classMeta)
    {
        $className = $classMeta->getName();
        if (!isset($this->inProcess[ $className ])) {
            return false;
        }

        $identifiers    = $classMeta->getIdentifier();
        foreach ($this->inProcess[ $className ] as &$idKey) {
            $found = true;
            foreach ($identifiers as &$ident) {
                $found = $found && $idKey[ $ident ] === $objectData[ $ident ];
            }
            if ($found) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array         $objectData
     * @param ClassMetadata $classMeta
     */
    private function addInProcess(array $objectData, ClassMetadata $classMeta)
    {
        $class = $classMeta->getName();
        if (!isset($this->inProcess[$class])) {
            $this->inProcess[$class] = array();
        }
        if (!$this->isInProcess($objectData, $classMeta)) {
            $this->inProcess[$class][] = $objectData;
        }
    }

    /**
     * @param array         $objectData
     * @param ClassMetadata $classMeta
     * @return bool
     */
    private function isExported(array $objectData, ClassMetadata $classMeta)
    {
        $class = $classMeta->getName();
        if (!isset($this->export[$class])) {
            return false;
        }
        $idents = $classMeta->getIdentifier();
        foreach ($this->export[$class] as $array) {
            $subfound = true;
            foreach ($idents as $ident) {
                $subfound = $subfound && $array[$ident] === $objectData[$ident];
            }
            if ($subfound) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array         $objectData
     * @param ClassMetadata $classMeta
     */
    private function addExport(array $objectData, ClassMetadata $classMeta)
    {
        $class = $classMeta->getName();
        if (!isset($this->export[$class])) {
            $this->export[$class] = array();
        }
        if (!$this->isExported($objectData, $classMeta)) {
            $this->export[$class][] = $objectData;
        }
    }

    /**
     * @param                    $object
     * @param ClassMetadata|null $classMeta
     * @return array
     */
    private function handleIdentParams($object, ClassMetadata $classMeta = null)
    {
        if (!$classMeta) {
            $realClass = $this->getRealClass($object);
            $classMeta = $this->em->getClassMetadata($realClass);
        }
        $params = $this->getIdentCriteria($object, $classMeta);
        return $this->createInstanceIdent($object, $params);
    }

    /**
     * Normalizes an array.
     *
     * @param array $array array
     * @return array normalized array
     */
    private function handleArray($array)
    {
        $result = array();
        if (ArrayUtil::isAssoc($array)) {
            foreach ($array as $key => $item) {
                $result[$key] = $this->handleValue($item);
            }
        } else {
            while (list($idx, $item) = each($array)) {
                $result[$idx] = $this->handleValue($item);
            }
        }
        return $result;
    }

    /**
     * @param $value
     * @return array|string
     */
    public function handleValue($value)
    {
        if (is_scalar($value)) {
            return $value;
        } elseif (is_array($value)) {
            return $this->handleArray($value);
        } elseif (is_object($value)) {
            $realClass = $this->getRealClass($value);
            try {
                $this->em->getRepository($realClass);
                return $this->normalizeEntity($value, $this->em->getClassMetadata($realClass));
            } catch (MappingException $e) {
                return $this->normalizeObject($value, new \ReflectionClass($realClass));
            }
        } else {
            return 'unsupported';
        }
    }

    /**
     * @param               $object
     * @param ClassMetadata $classMeta
     * @return array
     */
    public function normalizeEntity($object, ClassMetadata $classMeta)
    {
        $this->em->refresh($object);
        $data = $this->handleIdentParams($object, $classMeta);
        $this->addInProcess($data, $classMeta);
        foreach ($classMeta->getFieldNames() as $fieldName) {
            if (!in_array($fieldName, $classMeta->getIdentifier())
                && $getMethod = $this->getReturnMethod($fieldName, $classMeta->getReflectionClass())
            ) {
                $data[ $fieldName ] = $this->handleValue($getMethod->invoke($object));
            }
        }
        foreach ($classMeta->getAssociationMappings() as $assocItem) {
            $fieldName = $assocItem['fieldName'];
            if ($getMethod = $this->getReturnMethod($fieldName, $classMeta->getReflectionClass())) {
                $subObject = $getMethod->invoke($object);
                if (!$subObject) {
                    $data[ $fieldName ] = null;
                } elseif ($subObject instanceof PersistentCollection) {
                    $data[ $fieldName ] = array();
                    foreach ($subObject as $item) {
                        $ident                = $this->handleIdentParams($item);
                        $data[ $fieldName ][] = $ident;
                        $itemRealClass        = $this->getRealClass($item);
                        $itemClassMeta        = $this->em->getClassMetadata($itemRealClass);
                        if (!$this->isInProcess($ident, $itemClassMeta)) {
                            $this->normalizeEntity($item, $itemClassMeta);
                        }
                    }
                } else {
                    $data[ $fieldName ] = $this->handleIdentParams($subObject);
                    $subObjectRealClass = $this->getRealClass($subObject);
                    $subObjectClassMeta = $this->em->getClassMetadata($subObjectRealClass);
                    if (!$this->isInProcess($data[ $fieldName ], $subObjectClassMeta)) {
                        $this->normalizeEntity($subObject, $subObjectClassMeta);
                    }
                }
            }
        }
        $this->addExport($data, $classMeta);
        return $data;
    }

    /**
     * @param                  $object
     * @param \ReflectionClass $class
     * @return array
     */
    public function normalizeObject($object, \ReflectionClass $class)
    {
        $data = $this->createInstanceIdent($object);
        foreach ($class->getProperties() as $property) {
            if ($getMethod = $this->getReturnMethod($property->getName(), $class)) {
                $data[$property->getName()] = $this->handleValue($getMethod->invoke($object));
            }
        }
        return $data;
    }
}
