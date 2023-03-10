<?php

namespace Smile\Ibexa\Gally\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Yaml\Parser as YamlParser;
use Symfony\Component\Yaml\Yaml;

class IbexaGallyExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container)
    {
        $yamlParser ??= new YamlParser();
        $container->prependExtensionConfig(
            'ibexa_gally',
            $yamlParser->parseFile(
                __DIR__ . '/../Resources/config/ibexa_gally.yaml',
                Yaml::PARSE_CONSTANT
            )['ibexa_gally'] ?? []
        );
    }

    /**
     * @inheritDoc
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../')
        );

        $loader->load('Resources/config/services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $container->setParameter('ibexa_gally.credentials', $config['credentials'] ?? []);
        $container->setParameter('ibexa_gally.curl_options', $config['curl_options'] ?? []);
        $container->setParameter('ibexa_gally.debug', $config['debug'] ?? false);
        $container->setParameter('ibexa_gally.indexable_content', $config['indexable_content'] ?? false);
        $container->setParameter('ibexa_gally.source_field_mapping', $config['source_field_mapping'] ?? false);
    }
}
