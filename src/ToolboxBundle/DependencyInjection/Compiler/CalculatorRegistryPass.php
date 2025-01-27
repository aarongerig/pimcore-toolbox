<?php

namespace ToolboxBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use ToolboxBundle\Registry\CalculatorRegistry;

final class CalculatorRegistryPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        $taggedServices = $container->findTaggedServiceIds('toolbox.calculator', true);
        foreach ($taggedServices as $id => $tags) {
            $definition = $container->getDefinition(CalculatorRegistry::class);
            foreach ($tags as $attributes) {
                $definition->addMethodCall('register', [$id, new Reference($id), $attributes['type']]);
            }
        }
    }
}
