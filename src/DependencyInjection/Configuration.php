<?php

namespace Smile\Ibexa\Gally\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    /**
     * @inheritDoc
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('ibexa_gally');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('credentials')
                    ->children()
                        ->scalarNode('email')->isRequired()->example('example@example.com')->end()
                        ->scalarNode('password')->isRequired()->example('changeMe!')->end()
                        ->scalarNode('host')->isRequired()->example('127.0.0.1')->end()
                    ->end()
                ->end()
                ->arrayNode('curl_options')
                    ->children()
                        ->scalarNode('curl_resolve')->example('gally.local:443:172.16.0.1')->end()
                    ->end()
                ->end()
                ->booleanNode('debug')->defaultFalse()->end()
                ->arrayNode('indexable_content')
                    ->children()
                        ->arrayNode('content_types')
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('field_types')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('source_field_mapping')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('identifier')->isRequired()->end()
                            ->booleanNode('isSearchable')->defaultTrue()->end()
                            ->integerNode('weight')->defaultValue(1)->end()
                            ->booleanNode('isSpellchecked')->defaultFalse()->end()
                            ->booleanNode('isFilterable')->defaultFalse()->end()
                            ->booleanNode('isSortable')->defaultFalse()->end()
                            ->booleanNode('isUsedForRules')->defaultFalse()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
