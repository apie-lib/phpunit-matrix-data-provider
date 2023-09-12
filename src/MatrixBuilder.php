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
                $returnType = trim((string) $returnType, '?');
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
        $typeString = trim((string) $type, '?');
        if (!isset($this->resolvedTypes[$typeString])) {
            $this->resolvedTypes[$typeString] = [];
            foreach ($this->resolvedMethods[$typeString] ?? [] as $method) {
                foreach ($this->createMatrix($method) as $names => $arguments) {
                    $methodResult = $this->runMethod($method, $arguments);
                    if ($methodResult !== null) {
                        $this->resolvedTypes[$typeString][$names] = $methodResult;
                    }
                }
            }
        }
        return $this->resolvedTypes[$typeString];
    }

    private function runMethod(ReflectionMethod $method, array $arguments): mixed
    {
        $declaringClass = $method->getDeclaringClass();
        if ($declaringClass->isInstance($this->objectFactory)) {
            return $method->invokeArgs($this->objectFactory, $arguments);
        }
        if ($declaringClass->isInstance($this)) {
            return $method->invokeArgs($this, $arguments);
        }
        return $method->invokeArgs(null, $arguments);
    }

    public function createMatrix(ReflectionMethod $method): array
    {
        $parameters = $method->getParameters();
        if (empty($parameters)) {
            $singleMethodOutput = $this->runMethod($method, []);
            return $singleMethodOutput === null ? [] : [($method->name . "()") => [$singleMethodOutput]];
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
                $variations['(default)'] = $parameter->getDefaultValue();
            }
            $combinations["$type"] = $variations;
        }
        return $this->buildCombinations($combinations, $method->name . '(', ')');
    }

    private function buildCombinations(array $combinations, string $prefix = '', string $postfix = ''): array
    {
        if (empty($combinations)) {
            return [];
        }
        $result = [];
        $firstCombination = array_shift($combinations);
        if (empty($combinations)) {
            $result = [];
            foreach ($firstCombination as $combinationName => $combinationOption) {
                $result[$prefix . $combinationName . $postfix] = [$combinationOption];
            }
            return $result;
        }
        foreach ($firstCombination as $firstCombinationName => $combinationOption) {
            foreach ($this->buildCombinations($combinations, $prefix, $postfix) as $combinationName => $combinationVariation) {
                $result[$firstCombinationName . ', ' . $combinationName] = [$combinationOption, ...$combinationVariation];
            }
        }


        return $result;
    }
}