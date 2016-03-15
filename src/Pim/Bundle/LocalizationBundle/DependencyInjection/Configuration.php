<?php

namespace Pim\Bundle\LocalizationBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Pim LocalizationBundle configuration class
 * Load a tree config for system locale registering
 *
 * @author    Philippe Mossière <philippe.mossiere@akeneo.com>
 * @copyright 2016 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();

        $treeBuilder
            ->root('pim_localization')
            ->children()
                ->arrayNode('settings')
                ->children()
                    ->arrayNode('language')
                    ->children()
                        ->scalarNode('value')->defaultNull()->end()
                        ->scalarNode('scope')->defaultValue('app')->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
