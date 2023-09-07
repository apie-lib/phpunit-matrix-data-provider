<?php
namespace Apie\PhpunitMatrixDataProvider;

use ReflectionClass;
use ReflectionMethod;
use ReflectionType;
use RuntimeException;

final class MatrixBuilder
{
    private array $resolvedTypes = [];
    private array $resolvedMethods = [];
    public function __construct(private readonly object $objectFactory)
    {
        $refl = new ReflectionClass($objectFactory);
        $this->resolvedMethods[$refl->name] = [new ReflectionMethod($this, 'getObjectFactory')];
        foreach ($refl->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $returnType = $method->getReturnType();
            if ($returnType) {
                $returnType = (string) $returnType;
                $this->resolvedMethods[$returnType] ??= [];
                $this->resolvedMethods[$returnType][] = $method;
            }
        }
    }

    public function getObjectFactory()
    {
        return $this->objectFactory;
    }

    public function getVariations(ReflectionType $type): array
    {
        $typeString = (string) $type;
        if (!isset($this->resolvedTypes[$typeString])) {
            $this->resolvedTypes[$typeString] = [];
            foreach ($this->resolvedMethods[$typeString] ?? [] as $method) {
                foreach ($this->createMatrix($method) as $arguments) {
                    $this->resolvedTypes[$typeString][] = $method->invokeArgs($this->objectFactory, $arguments);
                }
            }
        }
        return $this->resolvedTypes[$typeString];
    }

    public function createMatrix(ReflectionMethod $method): array
    {
        $parameters = $method->getParameters();
        if (empty($parameters)) {
            return [[$method->invokeArgs($this->objectFactory, [])]];
        }
        $combinations = [];
        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            if (!$type) {
                throw new RuntimeException(
                    sprintf(
                        'Method %s::%s has parameter %s with no type definition',
                        $method->getDeclaringClass()->name,
                        $method->getName(),
                        $parameter->getName()
                    )
                );
            }
            $variations = $this->getVariations($type);
            if ($parameter->isDefaultValueAvailable()) {
                $variations[] = $parameter->getDefaultValue();
            }
            $combinations[] = $variations;
        }
        return $this->buildCombinations($combinations);
    }

    private function buildCombinations(array $combinations): array
    {
        if (empty($combinations)) {
            return [];
        }
        $result = [];
        $firstCombination = array_shift($combinations);
        if (empty($combinations)) {
            $result = [];
            foreach ($firstCombination as $combinationOption) {
                $result[] = [$combinationOption];
            }
            return $result;;
        }
        foreach ($firstCombination as $combinationOption) {
            foreach ($this->buildCombinations($combinations) as $combinationVariation) {
                $result[] = [$combinationOption, ...$combinationVariation];
            }
        }


        return $result;
    }
}