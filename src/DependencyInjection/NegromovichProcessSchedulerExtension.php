<?php
declare(strict_types=1);

namespace Negromovich\ProcessSchedulerBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class NegromovichProcessSchedulerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        if ($config['process_env'] ?? null) {
            $definition = $container->getDefinition('negromovich.process_scheduler.job_process_factory');
            $definition->setArguments([$config['process_env']]);
        }
    }
}
